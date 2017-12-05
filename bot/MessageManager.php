<?php

namespace w\Bot;

use Slack\Message\Message;
use Slack\Message\MessageBuilder;
use Slack\RealTimeClient;

class MessageManager extends AbstractMessageManager
{
    protected static $commands = [
        'join' => 'onJoin',
        'j' => 'onJoin',
        'leave' => 'onLeave',
        'l' => 'onLeave',
        'start' => 'onStart',
        's' => 'onStart',
        'ami' => 'onAmI',
        'clear' => 'onClear',
        'c' => 'onClear',
        'status' => 'onStatus',
        'st' => 'onStatus',
        'ping' => 'onPing',
        'top' => 'onTop',
    ];

    protected function onJoin(Message $message): string
    {
        if ($this->state->getPlayerCount() === $this->state->getPlayersNeeded()) {
            return 'Full! ' . $this->state->getPlayerCount() . '/' . $this->state->getPlayersNeeded();
        }

        if (!$this->state->join($message->data['user'])) {
            return 'You have already joined!';
        }

        return 'Joined! ' . $this->state->getPlayerCount() . '/' . $this->state->getPlayersNeeded();
    }

    protected function onLeave(Message $message): string
    {
        if ($this->state->leave($message->data['user'])) {
            return 'Left! ' . $this->state->getPlayerCount() . '/' . $this->state->getPlayersNeeded();
        } else if (!$this->amI($message)) {
            // Troll
            return 'Try /quit!';
        }

        return 'Meh! ' . $this->state->getPlayerCount() . '/' . $this->state->getPlayersNeeded();
    }

    protected function onStart(Message $message): string
    {
        $joined = $this->state->getJoinedPlayers();

        if (!$this->state->isManager($message->data['user'])) {
            return 'You are not manager!';
        } elseif ($this->state->start($message->data['user'])) {
            return 'Go go go - Play! ' . implode(' ', array_map(function ($username) {
                    return '<@' . $username . '>';
                }, $joined));
        }

        return 'Get more players! ' . $this->state->getPlayerCount() . '/' . $this->state->getPlayersNeeded();
    }

    protected function onAmI(Message $message): string
    {
        return $this->amI($message)
            ? 'Yes'
            : 'No';
    }

    protected function onClear(Message $message): string
    {
        if (!$this->state->isManager($message->data['user'])) {
            return 'You are not manager!';
        }

        $this->state->clear();

        return 'Cleared! 0/' . $this->state->getPlayersNeeded();
    }

    protected function onStatus(Message $message): string
    {
        return $this->state->getPlayerCount()
            ? sprintf('Players joined %d/%d: %s',
                $this->state->getPlayerCount(),
                $this->state->getPlayersNeeded(),
                implode(' ', array_map(function ($userId) {
                    return '<@' . $userId . '>';
                }, $this->state->getJoinedPlayers()))
            )
            : 'None. Type !join to start match!';
    }

    protected function onPing(Message $message): string
    {
        return 'Pong!';
    }

    protected function amI($message): bool
    {
        return in_array($message->data['user'], $this->state->getJoinedPlayers(), true);
    }

    protected function onTop(Message $message)
    {
        $userList = $this->state->db->getTopList();

        if (empty($userList)) {
            return 'No players in top!';
        }

        $result = '';

        foreach ($userList as $k => $user) {
            $result .= ($k + 1) . '. <@' . $user['id'] . '> - Won: *' . $user['games_won'] . '* - Games played: *' . $user['games_played'] . "*\n";
        }

        return $result;
    }

}