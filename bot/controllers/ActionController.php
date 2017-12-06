<?php

namespace w\Bot\controllers;

use w\Bot\structures\Action;
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
            ],
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

        return call_user_func([$this, $this->callbacks[$callbackId]['method']], [$actions]);
    }

    /**
     * @param Action[] $actions
     * @return string
     */
    public function processGameActions($actions)
    {
        $player = $this->payload['user']->id;
        $action = array_pop($actions);

        $actions = $this->callbacks['game']['actions'];
        if (!isset($actions[$action->getValue()])) {
            throw new \InvalidArgumentException('Invalid game action received!');
        }

        return call_user_func([$this, $actions[$action->getValue()]], $player);
    }

    /**
     * @param string $player
     * @return null|\Slack\Message\Message
     */
    protected function onGameJoin($player)
    {
        if ($this->footballState->amI($player)) {
            return null;
        }

        if ($this->footballState->getPlayerCount() === $this->footballState->getPlayersNeeded()) {
            // 'Full! ' . $this->footballState->getPlayerCount() . '/' . $this->footballState->getPlayersNeeded()
            return null;
        }

        if (!$this->footballState->join($player)) {
            // 'You have already joined!';
            return null;
        }

        return $this->footballState->getMessage($this->client->getMessageBuilder());
    }

    /**
     * @param string $player
     * @return \Slack\Message\Message|SlackResponse
     */
    protected function onGameLeave($player)
    {
        if (!$this->footballState->amI($player)) {
            $response = new SlackResponse();
            $response->message = 'Try /quit!';
            return $response;
        }

        if (!$this->footballState->leave($player)) {
            return null;
        }

        return $this->footballState->getMessage($this->client->getMessageBuilder());
    }
}