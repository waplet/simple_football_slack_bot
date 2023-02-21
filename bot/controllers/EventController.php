<?php

namespace w\Bot\controllers;

use InvalidArgumentException;
use w\Bot\MessageManager;

class EventController extends BaseController
{
    protected array $types = [
        /** {@see EventController::actionUrlVerification()} */
        /** {@see EventController::processEvents()} */
        'url_verification' => 'actionUrlVerification',
        'event_callback' => 'processEvents',
    ];

    private array $events = [
        /** {@see EventController::actionMessage()} */
        'message' => 'actionMessage',
    ];

    public function actionUrlVerification(): string
    {
        return $this->payload['challenge'];
    }

    /**
     * @return null|string|array
     */
    public function processEvents()
    {
        $event = $this->payload['event'];

        if (!isset($this->events[$event['type']])) {
            throw new InvalidArgumentException('Invalid payload type received!');
        }

        return call_user_func([$this, $this->events[$event['type']]], $event);
    }

    /**
     * @param array $message
     * @return null|string|array
     */
    public function actionMessage(array $message)
    {
        // Skip changes
        if (isset($message['subtype']) && $message['subtype'] === 'message_changed') {
            return null;
        }

        if (isset($message['bot_id']) && $message['bot_id'] === getenv('APP_BOT_USER')) {
            return null;
        }

        if (isset($message['event_time']) && (time() - (int)$message['event_time']) > 10) {
            echo "Skipping event, it is from past!";
            return null;
        }

        $messageManager = new MessageManager($this->footballState, $this->client);
        return $messageManager->parseMessage($message);
    }
}