<?php

namespace w\Bot\controllers;

use Slack\User;
use w\Bot\structures\Action;
use w\Bot\structures\GameStateResponse;
use w\Bot\structures\SlackResponse;

class ActionController extends BaseController
{
    protected $types = [
        'interactive_message' => 'processInteractiveMessage',
    ];

    protected $callbacks = [
        'game' => [
            'method' => 'processGameActions',
            'actions' => [
                'join' => 'onGameJoin',
                'leave' => 'onGameLeave',
                'start' => 'onGameStart',
                'cancel' => 'onGameCancel',
                'refresh' => 'onGameRefresh',
                'notify' => 'onGameNotify',
            ],
        ],
        'update' => [
            'method' => 'processUpdateActions',
        ],
    ];

    public function processInteractiveMessage()
    {
        $callbackId = $this->payload['callback_id'];
        if (!isset($this->callbacks[$callbackId])) {
            throw new \InvalidArgumentException('Invalid payload callback received!');
        }

        $actions = array_map(function (array $action) {
            return Action::fromData($action);
        }, $this->payload['actions']);

        return call_user_func([$this, $this->callbacks[$callbackId]['method']], $actions);
    }

    /**
     * @param Action[] $actions
     * @return string
     */
    public function processGameActions($actions)
    {
        $player = $this->payload['user']['id'];
        $action = reset($actions);

        $actions = $this->callbacks['game']['actions'];
        if (!isset($actions[$action->getValue()])) {
            throw new \InvalidArgumentException('Invalid game action received!');
        }

        return call_user_func([$this, $actions[$action->getValue()]], $player);
    }

    /**
     * @param string $player
     * @return null|GameStateResponse
     */
    protected function onGameJoin($player)
    {
        if ($this->footballState->amI($player)) {
            return null;
        }

        if ($this->footballState->getPlayerCount() === $this->footballState->getPlayersNeeded()) {
            // Full!
            return null;
        }

        if (!$this->footballState->join($player)) {
            // You have already joined!
            return null;
        }

        $this->client->getUserById($player)->then(function (User $user) {
            $db = $this->db;
            if (!$db->hasUser($user->getId())) {
                $db->createUser($user->getId(), $user->getRealName() ?? $user->getUsername());
            } else if (!$db->hasName($user->getId())) {
                $db->updateName($user->getId(), $user->getRealName() ?? $user->getUsername());
            }
        });

        return new GameStateResponse;
    }

    /**
     * @param string $player
     * @return string|GameStateResponse|SlackResponse
     */
    protected function onGameLeave($player)
    {
        if (!$this->footballState->amI($player)) {
            return null;
        }

        if (!$this->footballState->leave($player)) {
            return null;
        }

        if ($this->footballState->getPlayerCount() == 0) {
            $this->db->clearActiveGame();
            return 'Cancelled!';
        }

        return new GameStateResponse;
    }

    /**
     * @param string $player
     * @return null|string
     */
    protected function onGameCancel($player)
    {
        if (!$this->footballState->isManager($player)) {
            return null;
        }

        $this->footballState->clear();

        return 'Cancelled!';
    }

    /**
     * @param string $player
     * @return null|GameStateResponse
     */
    protected function onGameStart($player)
    {
        if (!$this->footballState->isManager($player)) {
            return null;
        }

        if (!$this->footballState->start($player)) {
            return null;
        }

        return new GameStateResponse;
    }

    /**
     * @param string $player
     * @return GameStateResponse
     */
    protected function onGameRefresh($player)
    {
        return new GameStateResponse;
    }

    /**
     * Writes a slack message triggering notifications for all current players
     * @param $player
     * @return null
     */
    protected function onGameNotify($player) {
        if (!$this->footballState->isManager($player)) {
            return null;
        }

        if (!$this->footballState->isFirst($player)) {
            // Only first should notify
            return null;
        }

        $response = new SlackResponse();
        $response->message = "Ready? " . implode(' ', array_map(function ($userId) {
                return '<@' . $userId . '>';
            }, $this->footballState->getJoinedPlayers()));

        return $response;
    }

    /**
     * @param Action[] $actions
     * @return null
     * @throws \Exception
     */
    protected function processUpdateActions($actions)
    {
        $player = $this->payload['user']['id'];
        $action = reset($actions);

        if (!$this->footballState->isManager($player)) {
            return null;
        }

        $name = $action->getName();
        error_log(print_r($action, true));
        $nameSplit = explode('_', $name);
        if (count($nameSplit) != 2) {
            return null;
        }

        $won = $action->getValue();

        // Team names
        if (!in_array($won, ['A', 'B', '-'])) {
            return null;
        }

        $gameId = $nameSplit[1];
        $teamAWon = $won == 'A';
        $didNotPlay = $won == '-';

        if ($didNotPlay) {
            $this->db->markGameAsDeleted($gameId);

            if ($this->footballState->isFinishedGame()) {
                $this->footballState->db->clearActiveGame();
                $this->footballState->db->summarizeElo();
                return 'Finished';
            }

            return new GameStateResponse;
        }

        if (!$this->db->updateGame($gameId, $this->footballState->getPlayersNeeded(), $teamAWon)) {
            return null;
        }

        if ($this->footballState->isFinishedGame()) {
            $this->footballState->db->clearActiveGame();
            $this->footballState->db->summarizeElo();
            return 'Finished';
        }

        return new GameStateResponse;
    }
}