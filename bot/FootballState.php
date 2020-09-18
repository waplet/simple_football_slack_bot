<?php

namespace w\Bot;

use Slack\Message\Attachment;
use Slack\Message\MessageBuilder;

class FootballState
{
    protected $playersNeeded;

    /**
     * @var null|\SQLite3
     */
    public $db = null;

    /**
     * FootballState constructor.
     * @param int $playersNeeded
     */
    public function __construct($playersNeeded = null)
    {
        $this->db = new Database();
        if ($playersNeeded == 2 || $playersNeeded == 4) {
            $this->playersNeeded = $playersNeeded;
        } else {
            $this->playersNeeded = 4;
        }
    }


    /**
     * Return true if joined, false if already present
     * @param string $player
     * @return bool
     */
    public function join($player): bool
    {
        if (in_array($player, $this->getJoinedPlayers(), true)) {
            return false;
        }

        if (!$this->db->getActiveGame()) {
            return $this->db->createActiveGame($player);
        }

        return $this->db->addPlayer($player);
    }

    /**
     * Return true if removed player
     * @param string $player
     * @return bool
     */
    public function leave($player): bool
    {
        if (!in_array($player, $this->getJoinedPlayers())) {
            return false;
        }

        return $this->db->removePlayer($player);
    }

    /**
     * @return int
     */
    public function getPlayerCount(): int
    {
        return count($this->getJoinedPlayers());
    }

    /**
     * @param string $player
     * @return bool
     */
    public function isManager($player): bool
    {
        if ($player === getenv('ADMIN')) {
            return true;
        }

        if ($this->getPlayerCount() == 0) {
            return false;
        }

        if ($this->amI($player)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $player
     * @return bool
     */
    public function isFirst($player): bool
    {
        $players = $this->getJoinedPlayers();

        if (empty($players)) {
            return false;
        }

        if ($players[0] == $player) {
            return true;
        }

        return false;
    }

    /**
     * @param $player
     * @return bool
     */
    public function start($player): bool
    {
        if ($this->getPlayerCount() != $this->playersNeeded) {
            return false;
        }

        $playId = $this->db->createGameInstances($player, $this->getJoinedPlayers());
        if ($playId === -1) {
            return false;
        }

        $this->db->startGame($playId);

        return true;
    }

    /**
     * @return array
     */
    public function getJoinedPlayers(): array
    {
        return $this->db->getPlayers();
    }

    /**
     * @return int
     */
    public function getPlayersNeeded(): int
    {
        return $this->playersNeeded;
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $this->db->clearActiveGame();

        return true;
    }

    /**
     * @return array|null
     */
    public function getMessage()
    {
        if ($this->db->getStatus() == 'started') {
            return $this->getPostGameState();
        }

        return $this->getGameState();
    }

    /**
     * @param string $player
     * @return bool
     */
    public function amI($player): bool
    {
        return in_array($player, $this->getJoinedPlayers(), true);
    }

    /**
     * @return array|null
     */
    protected function getGameState()
    {
        $response = [
            'mrkdwn' => true,
        ];

        $status = $this->db->getStatus();
        if ($status === 'none') {
            $response['text'] = 'Cancelled!';

            return $response;
        }

        $text = sprintf("Join to play a game!\n"
            . "Status: *%s*\n"
            . "Players joined (%d/%d): %s",
            $status,
            count($this->getJoinedPlayers()),
            $this->getPlayersNeeded(),
            implode(' ', array_map(function ($userId) {
                return '<@' . $userId . '>';
            }, $this->getJoinedPlayers()))
        );

        $response['text'] = $text;
        $response['attachments'] = [];

        $attachment = [
            'title' => 'Do you want to play a game?',
            'text' => 'Choose of of actions',
            'fallback' => 'You are unable to join',
            'color' => '#3AA3E3',
            'callback_id' => 'game',
            'actions' => [],
        ];


        if ($this->getPlayerCount() !== $this->getPlayersNeeded()) {
            $action = [
                'name' => 'game',
                'text' => 'Join',
                'type' => 'button',
                'value' => 'join',
                'style' => 'primary',
            ];
            $attachment['actions'][] = $action;
        }
        if ($status !== 'started') {
            $action = [
                'name' => 'game',
                'text' => 'Leave',
                'type' => 'button',
                'value' => 'leave',
                'style' => 'danger',
            ];
            $attachment['actions'][] = $action;
        }

        $action = [
            'name' => 'game',
            'text' => 'Cancel',
            'type' => 'button',
            'value' => 'cancel',
        ];
        $attachment['actions'][] = $action;

        if ($status !== 'started' && $this->getPlayerCount() === $this->getPlayersNeeded()) {
            $action = [
                'name' => 'game',
                'text' => 'Start',
                'type' => 'button',
                'value' => 'start',
            ];
            $attachment['actions'][] = $action;
        }

        $action = [
            'name' => 'game',
            'text' => 'Refresh',
            'type' => 'button',
            'value' => 'refresh',
        ];
        $attachment['actions'][] = $action;
        $action = [
            'name' => 'game',
            'text' => 'Notify',
            'type' => 'button',
            'value' => 'notify',
        ];
        $attachment['actions'][] = $action;

        $response['attachments'][] = $attachment;

        return $response;
    }

    /**
     * @return array|null
     */
    protected function getPostGameState()
    {
        $gameInstances = $this->db->getActiveGameInstances();

        if (empty($gameInstances)) {
            return null;
        }

        $response = [
            'mrkdwn' => true,
            'text' => 'Update game results!',
            'attachments' => [],
        ];

        foreach ($gameInstances as $k => $instance) {
            if ($instance['status'] === 'deleted') {
                $attachment = [
                    'title' => 'Game #' . ($k + 1),
                    'text' => 'Done!',
                ];
            } else {
                if (is_null($instance['player_3']) && is_null($instance['player_4'])) {
                    $attachment = [
                        'title' => 'Game #' . ($k + 1),
                        'text' => sprintf(
                            "Team A: <@%s>\nTeam B: <@%s>",
                            $instance['player_1'],
                            $instance['player_2']
                        ),
                        'fallback' => 'You are unable to join',
                        'color' => '#3AA3E3',
                        'actions' => [],
                    ];
                } else {
                    $attachment = [
                        'title' => 'Game #' . ($k + 1),
                        'text' => sprintf(
                            "Team A: <@%s> <@%s>\nTeam B: <@%s> <@%s>",
                            $instance['player_1'],
                            $instance['player_2'],
                            $instance['player_3'],
                            $instance['player_4']
                        ),
                        'fallback' => 'You are unable to join',
                        'color' => '#3AA3E3',
                        'actions' => [],
                    ];
                }
                $attachment['callback_id'] = 'update';

                $action = [
                    'name' => 'update_' . $instance['id'],
                    'text' => 'A',
                    'type' => 'button',
                    'value' => 'A',
                    'style' => 'primary',
                ];
                $attachment['actions'][] = $action;
                $action = [
                    'name' => 'update_' . $instance['id'],
                    'text' => 'B',
                    'type' => 'button',
                    'value' => 'B',
                    'style' => 'danger',
                ];
                $attachment['actions'][] = $action;
                $action = [
                    'name' => 'update_' . $instance['id'],
                    'text' => 'Did not play',
                    'type' => 'button',
                    'value' => '-',
                ];
                $attachment['actions'][] = $action;
            }
            $response['attachments'][] = $attachment;
        }

        $attachment = [
            'title' => 'Game #' . ($k + 1),
            'text' => sprintf(
                "Team A: <@%s> <@%s>\nTeam B: <@%s> <@%s>",
                $instance['player_1'],
                $instance['player_2'],
                $instance['player_3'],
                $instance['player_4']
            ),
            'fallback' => 'You are unable to join',
            'color' => '#3AA3E3',
            'callback_id' => 'game',
            'actions' => [],
        ];

        $action = [
            'name' => 'game',
            'text' => 'Refresh',
            'type' => 'button',
            'value' => 'refresh',
        ];
        $attachment['actions'][] = $action;
        $action = [
            'name' => 'game',
            'text' => 'Cancel',
            'type' => 'button',
            'value' => 'cancel',
        ];
        $attachment['actions'][] = $action;

        $response['attachments'][] = $attachment;

        return $response;
    }

    /**
     * @return bool
     */
    public function isFinishedGame()
    {
        $gameInstances = $this->db->getActiveGameInstances();
        $deletedGameInstances = array_filter($gameInstances, function ($instance) {
            return $instance['status'] === 'deleted';
        });
        if (count($gameInstances) === count($deletedGameInstances)) {
            return true;
        }

        return false;
    }

    /**
     * @param array $rankByWonGames
     * @return array
     */
    public function getRankByWinRate(array $rankByWonGames = [])
    {
        usort($rankByWonGames, function ($userA, $userB) {
            $scoreA = $userA['games_played'] ? $userA['games_won'] / $userA['games_played'] : 0;
            $scoreB = $userB['games_played'] ? $userB['games_won'] / $userB['games_played'] : 0;

            if ($scoreA > $scoreB) {
                return -1;
            } else if ($scoreA < $scoreB) {
                return 1;
            } else {
                if ($userA['games_won'] > $userB['games_won']) {
                    return -1;
                } else if ($userA['games_won'] < $userB['games_won']) {
                    return 1;
                }
            }

            return 0;
        });

        return $rankByWonGames;
    }

    /**
     * @param array $rankByWonGames
     * @param array $rankByWinRate
     * @return array
     */
    public function mergeRanks(array $rankByWonGames, array $rankByWinRate)
    {
        $userIdDictionary = [];
        foreach ($rankByWinRate as $index => $user) {
            $userIdDictionary[$user['id']] = [
                'user' => $user,
                'rank' => $index,
            ];
        }

        foreach ($rankByWonGames as $index => $user) {
            $userIdDictionary[$user['id']]['rank'] += $index;
            $userIdDictionary[$user['id']]['rank'] /= 2.0;
        }

        usort($userIdDictionary, function ($a, $b) {
            return $a['rank'] > $b['rank'];
        });

        $rank = [];

        foreach ($userIdDictionary as $userId => $rankData) {
            $rank[] = $rankData['user'];
        }

        return $rank;
    }
}