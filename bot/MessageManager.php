<?php

namespace w\Bot;

use Slack\Message\Message;

class MessageManager extends AbstractMessageManager
{
    protected static $commands = [
        'ping' => 'onPing',
        'top' => 'onTop',
        'begin' => 'onBegin',
    ];

    protected function onPing(Message $message): string
    {
        return 'Pong!';
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

    /**
     * @param Message $message
     * @return \Slack\Message\MessageBuilder
     */
    protected function onBegin(Message $message)
    {
        if ($this->state->getPlayerCount() > 0) {
            return $this->state->getMessage($this->client->getMessageBuilder());
        }

        $this->state->join($message->data['user']);
        return $this->state->getMessage($this->client->getMessageBuilder());
    }
}