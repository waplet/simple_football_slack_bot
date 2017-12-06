<?php

namespace w\Bot\controllers;

use w\Bot\structures\Action;

class ActionController extends BaseController
{
    protected $types = [
        'interactive_message' => 'processInteractiveMessage',
    ];

    protected $callbacks = [
        'game' => 'processGameActions'
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

        return call_user_func([$this, $this->callbacks[$callbackId]], [$actions]);
    }

    /**
     * @param Action[] $actions
     * @return string
     */
    public function processGameActions($actions)
    {
        return 'Joined!';
    }
}