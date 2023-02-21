<?php

namespace w\Bot;

use SQLite3;

class FootballState
{
    protected int $playersNeeded;

    /**
     * @var null|SQLite3
     */
    public $db = null;

    public function __construct(int $playersNeeded = null)
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
     */
    public function join(string $channel, string $player): bool
    {
        if (in_array($player, $this->getJoinedPlayers($channel), true)) {
            return false;
        }

        if (!$this->db->getActiveGame($channel)) {
            return $this->db->createActiveGame($channel, $player);
        }

        return $this->db->addPlayer($channel, $player);
    }

    public function leave(string $channel, string $player): bool
    {
        if (!in_array($player, $this->getJoinedPlayers($channel))) {
            return false;
        }

        return $this->db->removePlayer($channel, $player);
    }

    public function getPlayerCount(string $channel): int
    {
        return count($this->getJoinedPlayers($channel));
    }

    public function isManager(string $channel, string $player): bool
    {
        if ($player === getenv('ADMIN')) {
            return true;
        }

        if ($this->getPlayerCount($channel) == 0) {
            return false;
        }

        if ($this->amI($channel, $player)) {
            return true;
        }

        return false;
    }

    public function isFirst(string $channel, string $player): bool
    {
        $players = $this->getJoinedPlayers($channel);

        if (empty($players)) {
            return false;
        }

        if ($players[0] == $player) {
            return true;
        }

        return false;
    }

    public function start(string $channel, string $player): bool
    {
        if ($this->getPlayerCount($channel) != $this->playersNeeded) {
            return false;
        }

        $playId = $this->db->createGameInstances($player, $this->getJoinedPlayers($channel));
        if ($playId === -1) {
            return false;
        }

        $this->db->startGame($channel, $playId);

        return true;
    }

    public function getJoinedPlayers(string $channel): array
    {
        return $this->db->getPlayers($channel);
    }

    public function getPlayersNeeded(): int
    {
        return $this->playersNeeded;
    }

    public function clear(string $channel): bool
    {
        $this->db->clearActiveGame($channel);

        return true;
    }

    public function getGameStateResponse(string $channel): ?array
    {
        if ($this->db->getStatus($channel) === 'started') {
            return $this->getPostGameState($channel);
        }

        return $this->getGameState($channel);
    }

    public function amI(string $channel, string $player): bool
    {
        return in_array($player, $this->getJoinedPlayers($channel), true);
    }

    protected function getGameState(string $channel): ?array
    {
        $response = [
            'channel' => $channel,
            'mrkdwn' => true,
        ];

        $status = $this->db->getStatus($channel);
        if ($status === 'none') {
            $response['text'] = 'Cancelled!';

            return $response;
        }

        $text = sprintf("Join to play a game!\n"
            . "Status: *%s*\n"
            . "Players joined (%d/%d): %s",
            $status,
            count($this->getJoinedPlayers($channel)),
            $this->getPlayersNeeded(),
            implode(' ', array_map(function ($userId) {
                return '<@' . $userId . '>';
            }, $this->getJoinedPlayers($channel)))
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


        if ($this->getPlayerCount($channel) !== $this->getPlayersNeeded()) {
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

        if ($status !== 'started' && $this->getPlayerCount($channel) === $this->getPlayersNeeded()) {
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

    protected function getPostGameState(string $channel): ?array
    {
        $gameInstances = $this->db->getActiveGameInstances($channel);

        if (empty($gameInstances)) {
            return null;
        }

        $response = [
            'channel' => $channel,
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
            'title' => 'Actions',
            'text' => '',
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

    public function isFinishedGame(string $channel): bool
    {
        $gameInstances = $this->db->getActiveGameInstances($channel);
        $deletedGameInstances = array_filter($gameInstances, function ($instance) {
            return $instance['status'] === 'deleted';
        });
        if (count($gameInstances) === count($deletedGameInstances)) {
            return true;
        }

        return false;
    }
}