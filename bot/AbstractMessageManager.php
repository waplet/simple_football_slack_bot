<?php

namespace w\Bot;

use Slack\Message\Message;
use Slack\Message\MessageBuilder;
use Slack\RealTimeClient;

abstract class AbstractMessageManager
{
    /**
     * @var RealTimeClient
     */
    protected $client;

    /**
     * @var FootballState
     */
    protected $state;

    /**
     * @var array
     */
    protected static $prepends = [',']; // Can be only in length of 1

    protected static $commands;

    public function __construct(FootballState $state, RealTimeClient $client)
    {
        $this->state = $state;
        $this->client = $client;
    }

    /**
     * @param Message $message
     * @return null|MessageBuilder
     */
    public function parseMessage(Message $message)
    {
        $messageText = trim($message->getText());

        if (mb_strlen($messageText) === 0) {
            return null;
        }

        if (!in_array(mb_substr($messageText, 0, 1), self::$prepends)) {
            return null;
        }

        $messageTextRaw = mb_substr($messageText, 1);
        $messageSplit = explode(' ', $messageTextRaw);
        $command = mb_strtolower(array_shift($messageSplit));

        if ($command === 'help') {
            $responseText = $this->onHelp($message);
        } else if (isset(static::$commands[$command])) {
            $responseText = call_user_func([$this, static::$commands[$command]], $message, implode(' ', $messageSplit));
        } else {
            $responseText = "Incorrect message command given, try !help";
        }

        return $this->client->getMessageBuilder()
            ->setText($responseText);
    }

    private function onHelp(Message $message = null): string
    {
        return implode(', ', array_map(function ($command) {
            return '!' . $command;
        }, array_keys(static::$commands)));
    }

}