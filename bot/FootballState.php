<?php

namespace w\Bot;

use Slack\Message\Attachment;
use Slack\Message\Message;
use Slack\Message\MessageBuilder;
use w\Bot\structures\Action;
use w\Bot\structures\ActionAttachment;

class FootballState
{
    private $lastPlayTimestamp = 0;

    private $isLocked = false;

    private $playersJoined = [];

    private $playersNeeded = 4;

    /**
     * @var null|\SQLite3
     */
    public $db = null;

    /**
     * FootballState constructor.
     */
    public function __construct()
    {
        $this->db = new Database();

        // Test
        $this->playersJoined = ['U7A4L7NN6', 1,2,3];
        // $this->db->clearActiveGame();
        // $this->db->createActiveGame('U7A4L7NN6');
        // $this->playersJoined = [1,2,3];
        // foreach ($this->playersJoined as $player) {
        //     $this->db->addPlayer($player);
        // }


        return $this;
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

        $this->playersJoined[] = $player;
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

        $this->playersJoined = array_values(array_filter($this->playersJoined, function ($currentPlayer) use ($player) {
            return $currentPlayer !== $player;
        }));
        return $this->db->removePlayer($player);
    }

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

        if (in_array($player, $this->getJoinedPlayers())) {
            return true;
        }

        return false;
    }

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

    public function finish()
    {
        // TODO
    }

    public function getJoinedPlayers(): array
    {
        return $this->db->getPlayers();
    }

    public function getPlayersNeeded(): int
    {
        return $this->playersNeeded;
    }

    public function clear(): bool
    {
        $this->playersJoined = [];
        $this->db->clearActiveGame();

        return true;
    }

    public function getMessage(MessageBuilder $messageBuilder)
    {
        if ($this->db->getStatus() == 'started') {
            return $this->getPostGameState($messageBuilder);
        }

        return $this->getGameState($messageBuilder);
    }

    /**
     * @param string $player
     * @return bool
     */
    public function amI($player): bool
    {
        return in_array($player, $this->getJoinedPlayers(), true);
    }

    protected function getGameState(MessageBuilder $messageBuilder)
    {
        $text = "Join to play a game!\n"
            . "Status: *" . $this->db->getStatus() . "*\n"
            . "Player joined: " . implode(' ', array_map(function ($userId) {
                return '<@' . $userId . '>';
            }, $this->getJoinedPlayers()))
        ;

        $messageBuilder->setText($text);

        $attachment = new ActionAttachment('Do you want to play a game?', 'Choose one of actions', 'You are unable to join', '#3AA3E3');
        $attachment->setCallbackId('game');

        if ($this->getPlayerCount() != $this->getPlayersNeeded()) {
            $action = new Action("game", "Join", "button", "join" , "primary");
            $attachment->addAction($action);
        }
        if ($this->db->getStatus() != 'started') {
            $action = new Action("game", "Leave", "button", "leave" , "danger");
            $attachment->addAction($action);
        }
        $action = new Action("game", "Cancel", "button", "cancel"); // Add confirm
        $attachment->addAction($action);

        if ($this->getPlayerCount() == $this->getPlayersNeeded() && $this->db->getStatus() != 'started') {
            $action = new Action("game", "Start", "button", "start");
            $attachment->addAction($action);
        }
        $action = new Action("game", "Refresh", "button", "refresh");
        $attachment->addAction($action);
        $messageBuilder->addAttachment($attachment);

        return $messageBuilder;
    }

    protected function getPostGameState(MessageBuilder $messageBuilder)
    {
        $gameInstances = $this->db->getActiveGameInstances();

        if (empty($gameInstances)) {
            return null;
        }

        $messageBuilder->setText('Update game results!');

        foreach ($gameInstances as $k => $instance) {
            if ($instance['status'] == 'deleted') {
                $attachment = new Attachment('Game #' . ($k +1), 'Done!');
            } else {
                $attachment = new ActionAttachment(
                    'Game #' . ($k + 1),
                    sprintf("Team A: <@%s> <@%s>\nTeam B: <@%s> <@%s>",
                        $instance['player_1'],
                        $instance['player_2'],
                        $instance['player_3'],
                        $instance['player_4']),
                    'You are unable to join',
                    '#3AA3E3'
                );
                $attachment->setCallbackId('update');
                $action = new Action('update_' . $instance['id'], 'A', 'button', 'A', 'primary');
                $attachment->addAction($action);
                $action = new Action('update_' . $instance['id'], 'B', 'button', 'B', 'danger');
                $attachment->addAction($action);
                $action = new Action('update_' . $instance['id'], 'Did not play', 'button', '-');
                $attachment->addAction($action);
            }
            $messageBuilder->addAttachment($attachment);
        }
        $attachment = new ActionAttachment('Actions', '', 'You are unable to join', '#AA3AE3');
        $attachment->setCallbackId('game');
        $action = new Action("game", "Refresh", "button", "refresh");
        $attachment->addAction($action);
        $action = new Action("game", "Cancel", "button", "cancel");
        $attachment->addAction($action);
        $messageBuilder->addAttachment($attachment);

        return $messageBuilder;
    }
}