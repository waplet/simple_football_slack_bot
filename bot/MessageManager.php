<?php

namespace w\Bot;

class MessageManager extends AbstractMessageManager
{
    protected static $commands = [
        'ping' => 'onPing',
        'top' => 'onTop',
        'begin' => 'onBegin',
        'url' => 'onUrl',
        'name' => 'onUpdateName',
    ];

    /**
     * @param array $message
     * @return string
     */
    protected function onPing(array $message): string
    {
        return 'Pong!';
    }

    /**
     * @param array $message
     * @return string
     */
    protected function onTop(array $message)
    {
        $topList = $this->state->db->getTopList();

        if (empty($topList)) {
            return 'No players in top!';
        }

        $result = '';
        foreach ($topList as $k => $user) {
            $score = $user['games_played'] ? number_format((($user['games_won'] * 100) / $user['games_played']),0) : 0;
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
    protected function onBegin(array $message)
    {
        if ($this->state->getPlayerCount() > 0) {
            return $this->state->getMessage();
        }

        if (!$this->state->join($message['user'])) {
            return null;
        }

        $usersInfo = $this->client->usersInfo(['user' => $message['user']]);
        $user = $usersInfo ? $usersInfo->getUser() : null;
        if ($user) {
            if (!$this->state->db->hasUser($user->getId())) {
                $this->state->db->createUser($user->getId(), $user->getRealName() ?? $user->getName());
            } elseif (!$this->state->db->hasName($user->getId())) {
                $this->state->db->updateName($user->getId(), $user->getRealName() ?? $user->getName());
            }
        }

        return $this->state->getMessage();
    }

    private function eventSort(array $eventA, array $eventB)
    {
        return (int)$eventA['start'] <=> (int)$eventB['start'];
    }

    protected function onUrl(array $message)
    {
        return 'https://github.com/waplet/simple_football_slack_bot/';
    }

    protected function onUpdateName(array $message)
    {
        $usersInfo = $this->client->usersInfo(['user' => $message['user']]);
        $user = $usersInfo ? $usersInfo->getUser() : null;
        if ($user) {
            if (!$this->state->db->hasUser($user->getId())) {
                return null;
            }

            $this->state->db->updateName($user->getId(), $user->getRealName() ?? $user->getName());
        }

        return null;
    }
}