<?php

namespace w\Bot\controllers;

use Slack\Message\Attachment;
use Slack\Message\Message;
use Slack\Message\MessageBuilder;
use w\Bot\structures\Action;
use w\Bot\structures\ActionAttachment;
use w\Bot\structures\SlackResponse;

class EventController extends BaseController
{
    protected $types = [
        'url_verification' => 'actionUrlVerification',
        'event_callback' => 'processEvents',
    ];

    private $events = [
        'message' => ['actionMessage', Message::class],
    ];

    public function actionUrlVerification()
    {
        return $this->payload['challenge'];
    }

    public function processEvents()
    {
        $event = $this->payload['event'];

        if (!isset($this->events[$event['type']])) {
            throw new \InvalidArgumentException('Invalid payload type received!');
        }

        $class = $this->events[$event['type']][1];
        return call_user_func([$this, $this->events[$event['type']][0]], new $class($this->client, $event));
    }

    public function actionMessage(Message $message)
    {
        if ($message->data['user'] == getenv('APP_BOT_USER')) {
            return null;
        }
        // Skip changes
        if (isset($message->data['subtype']) && $message->data['subtype'] == 'message_changed') {
            return null;
        }

        if ($message->data['text'] == ',begin') {
            return $this->footballState->getMessage($this->client->getMessageBuilder());
        }

        $response = new SlackResponse();
        $response->message = $message->getText();

        return $response;
    }
}