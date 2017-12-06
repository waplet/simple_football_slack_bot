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

        if ($message->data['text'] == ',begin') {
            $messageBuilder = $this->client->getMessageBuilder();
            $messageBuilder->setText('Join to play a game');

            $attachment = new ActionAttachment('Do you want to play a game?', 'Join', 'You are unable to join', '#3AA3E3');
            $attachment->setCallbackId('game');

            $action = new Action("game", "Join", "button", "join" , "success");
            $attachment->addAction($action);
            $messageBuilder->addAttachment($attachment);

            return $messageBuilder;
        }

        $response = new SlackResponse();
        $response->message = $message->getText();

        return $response;
    }
}