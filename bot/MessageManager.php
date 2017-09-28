<?php

namespace w\Bot;

use Slack\Channel;
use Slack\Message\Message;
use Slack\Message\MessageBuilder;
use Slack\RealTimeClient;
use Slack\User;

class MessageManager
{
    private $client;
    private $state;

    // private static $prepends = ['!', '?', ','];
    private static $prepends = '!';
    private static $commands = [
        'join',
        'j',
        'leave',
        'l',
        'start',
        's',
        'ami',
        'clear',
        'c',
        'status',
        'st',
        'ping',
    ];

    public function __construct(FootballState $state, RealTimeClient $client)
    {
        $this->state = $state;
        $this->client = $client;
    }

    /**
     * @param string $username
     * @param Message $message
     * @return null|MessageBuilder
     */
    public function parseMessage($username, Message $message)
    {
        $messageText = trim($message->getText());

        if (mb_strlen($messageText) === 0) {
            return null;
        }

        if (mb_strpos($messageText, self::$prepends) !== 0) {
            return null;
        }

        $messageTextRaw = mb_substr($messageText, mb_strlen(self::$prepends));
        $messageTextRaw = mb_strtolower($messageTextRaw);

        switch ($messageTextRaw) {
            case 'j':
            case 'join':
                $responseText = $this->onJoin($message);
                break;
            case 'leave':
            case 'l':
                $responseText = $this->onLeave($message);
                break;
            case 'start':
            case 's':
                $responseText = $this->onStart($message);
                break;
            case 'ami':
                $responseText = $this->onAmI($message);
                break;
            case 'help':
                $responseText = implode(', ', array_map(function ($command) {
                    return '!' . $command;
                }, self::$commands));
                break;
            case 'clear':
            case 'c':
                $responseText = $this->onClear($message);
                break;
            case 'status':
            case 'st':
                $responseText = $this->onStatus($message);
                break;
            case 'ping':
                $responseText = $this->onPing($message);
                break;
            default:
                $responseText = "Incorrect message command given, try !help";
                break;
        }

        return $this->client->getMessageBuilder()
            ->setText($responseText);
    }

    private function onJoin(Message $message): string
    {
        if ($this->state->getPlayerCount() === $this->state->getPlayersNeeded()) {
            return 'Full! ' . $this->state->getPlayerCount() . '/' . $this->state->getPlayersNeeded();
        }

        if (!$this->state->join($message->data['user'])) {
            return 'You have already joined!';
        }

        return 'Joined! ' . $this->state->getPlayerCount() . '/' . $this->state->getPlayersNeeded();
    }

    private function onLeave(Message $message): string
    {
        if ($this->state->leave($message->data['user'])) {
            return 'Done! ' . $this->state->getPlayerCount() . '/' . $this->state->getPlayersNeeded();
        } else if ($this->state->getPlayerCount() === 0) {
            // Troll
            return 'Try /quit!';
        }

        return 'Meh! ' . $this->state->getPlayerCount() . '/' . $this->state->getPlayersNeeded();
    }

    private function onStart(Message $message): string
    {
        $joined = $this->state->getJoinedPlayers();

        if ($this->state->start($message->data['user'])) {
            return 'Go go go - Play! ' . implode(' ', array_map(function ($username) {
                    return '<@' . $username . '>';
                }, $joined));
        }

        return 'Get more players! ' . $this->state->getPlayerCount() . '/' . $this->state->getPlayersNeeded();
    }

    private function onAmI(Message $message): string
    {
        return in_array($message->data['user'], $this->state->getJoinedPlayers(), true)
            ? 'Yes'
            : 'No';
    }

    private function onClear(Message $message): string
    {
        return $this->state->clear($message->data['user'])
            ? 'Cleared! 0/' . $this->state->getPlayersNeeded()
            : 'You are not manager!';
    }

    private function onStatus(Message $message): string
    {
        return $this->state->getPlayerCount()
            ? 'Players joined: ' . implode(' ', array_map(function ($userId) {
                return '<@' . $userId . '>';
            }, $this->state->getJoinedPlayers()))
            : 'None!';
    }

    private function onPing(Message $message): string
    {
        return 'Pong!';
    }
}