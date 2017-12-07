<?php

namespace w\Bot\controllers;

use Slack\Message\Message;
use w\Bot\MessageManager;

class EventController extends BaseController
{
    protected $types = [
        'url_verification' => 'actionUrlVerification',
        'event_callback' => 'processEvents',
    ];

    private $events = [
        'message' => ['actionMessage', Message::class],
    ];

    /**
     * @return string
     */
    public function actionUrlVerification()
    {
        return $this->payload['challenge'];
    }

    /**
     * @return null|\Slack\Message\MessageBuilder|string
     */
    public function processEvents()
    {
        $event = $this->payload['event'];

        if (!isset($this->events[$event['type']])) {
            throw new \InvalidArgumentException('Invalid payload type received!');
        }

        $class = $this->events[$event['type']][1];
        return call_user_func([$this, $this->events[$event['type']][0]], new $class($this->client, $event));
    }

    /**
     * @param Message $message
     * @return null|\Slack\Message\MessageBuilder|string
     */
    public function actionMessage(Message $message)
    {
        // Skip changes
        if (isset($message->data['subtype']) && $message->data['subtype'] == 'message_changed') {
            return null;
        }
        if ($message->data['user'] == getenv('APP_BOT_USER')) {
            return null;
        }

        $messageManager = new MessageManager($this->footballState, $this->client);
        return $messageManager->parseMessage($message);
    }
}