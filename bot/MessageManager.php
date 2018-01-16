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

    /**
     * @param Message $message
     * @return string
     */
    protected function onPing(Message $message): string
    {
        return 'Pong!';
    }

    /**
     * @param Message $message
     * @return string
     */
    protected function onTop(Message $message)
    {
        $rankByWonGames = $this->state->db->getTopList();

        if (empty($rankByWonGames)) {
            return 'No players in top!';
        }

        $rankByWinRate = $this->state->getRankByWinRate($rankByWonGames); // Arrays are copied instead of referenced
        $topList = $this->state->mergeRanks($rankByWonGames, $rankByWinRate);

        $result = '';
        foreach ($topList as $k => $user) {
            $score = $user['games_played'] ? number_format((($user['games_won'] * 100) / $user['games_played']),0) : 0;
            $result .= ($k + 1) . '. <@' . $user['id'] . '> - Won: *' . $user['games_won'] . '* - Games played: *' . $user['games_played'] . "* - Win rate: *" . $score . "%*\n";
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

        if ($this->state->join($message->data['user'])) {
            return $this->state->getMessage($this->client->getMessageBuilder());
        }

        return null;
    }
}