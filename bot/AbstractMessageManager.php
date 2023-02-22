<?php

namespace w\Bot;

use JoliCode\Slack\Api\Client;
use w\Bot\structures\SlackResponse;

abstract class AbstractMessageManager
{
    protected Client $client;
    protected FootballState $state;

    protected static array $prepends = [',']; // Can only be in length of 1

    protected static array $commands = [];

    public function __construct(FootballState $state, Client $client)
    {
        $this->state = $state;
        $this->client = $client;
    }

    /**
     * @param array $message
     * @return null|array|string
     */
    public function parseMessage(array $message)
    {
        $messageText = trim($message['text']);

        if ($messageText === '') {
            return null;
        }

        if (!in_array(mb_substr($messageText, 0, 1), self::$prepends, true)) {
            return null;
        }

        $messageTextRaw = mb_substr($messageText, 1);
        $messageSplit = explode(' ', $messageTextRaw);
        $command = mb_strtolower(array_shift($messageSplit));

        if ($command === 'help') {
            $responseText = $this->onHelp($message);
        } else {
            if (isset(static::$commands[$command])) {
                $responseText = call_user_func([$this, static::$commands[$command]], $message, implode(' ', $messageSplit));
            } else {
                $responseText = "Incorrect message command given, try !help";
            }
        }

        // If message manager response is string, let's post is as Slack message
        if (is_string($responseText)) {
            $response = new SlackResponse();
            $response->channel = $message['channel'];
            $response->message = $responseText;
            $responseText = $response;
        }

        return $responseText;
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    private function onHelp(array $message): string
    {
        return implode(', ', array_map(function ($command) {
            return ',' . $command;
        }, array_keys(static::$commands)));
    }

}