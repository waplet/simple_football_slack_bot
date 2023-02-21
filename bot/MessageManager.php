<?php

namespace w\Bot;

class MessageManager extends AbstractMessageManager
{
    protected static array $commands = [
        /**
         * {@see MessageManager::onPing()}
         * {@see MessageManager::onTop()}
         * {@see MessageManager::onBegin()}
         * {@see MessageManager::onUrl()}
         */
        'ping' => 'onPing',
        'top' => 'onTop',
        'begin' => 'onBegin',
        'url' => 'onUrl',
    ];

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    protected function onPing(array $message): string
    {
        return 'Pong!';
    }

    /**
     * @param array $message
     * @return string
     */
    protected function onTop(array $message): string
    {
        $channel = $message['channel'];
        $topList = $this->state->db->getTopList($channel);

        if (empty($topList)) {
            return 'No players in top!';
        }

        $result = '';
        foreach ($topList as $k => $user) {
            $score = $user['games_played'] ? number_format((($user['games_won'] * 100) / $user['games_played'])) : 0;
            if (!empty($user['name'])) {
                $result .= ($k + 1) . '. *' . $user['name'] . '*';
            } else {
                $result .= ($k + 1) . '. <@' . $user['id'] . '>';
            }
            $result .= ' - Won: *' . $user['games_won'] . '* - Games played: *'
                . $user['games_played'] . "* - Win rate: *" . $score . "%* - ELO: *"
                . $user['current_elo'] . "* \n";
        }

        return $result;
    }

    /**
     * @param array $message
     * @return array|null
     */
    protected function onBegin(array $message): ?array
    {
        $channel = $message['channel'];

        if ($this->state->getPlayerCount($channel) > 0) {
            return $this->state->getGameStateResponse($channel);
        }

        if (!$this->state->join($channel, $message['user'])) {
            return null;
        }

        $usersInfo = $this->client->usersInfo(['user' => $message['user']]);
        $user = $usersInfo ? $usersInfo->getUser() : null;
        if ($user) {
            if (!$this->state->db->hasUser($channel, $user->getId())) {
                $this->state->db->createUser($channel, $user->getId(), $user->getRealName() ?? $user->getName());
            } elseif (!$this->state->db->hasName($channel, $user->getId())) {
                $this->state->db->updateName($channel, $user->getId(), $user->getRealName() ?? $user->getName());
            }
        }

        return $this->state->getGameStateResponse($channel);
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function onUrl(array $message): string
    {
        return 'https://github.com/waplet/simple_football_slack_bot/';
    }
}