<?php

namespace w\Bot;

use Slack\Message\Message;
use Slack\User;

class MessageManager extends AbstractMessageManager
{
    protected static $commands = [
        'ping' => 'onPing',
        'top' => 'onTop',
        'begin' => 'onBegin',
        'status' => 'onRoomStatus',
        'url' => 'onUrl',
        'name' => 'onUpdateName',
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
     * @param Message $message
     * @return \Slack\Message\MessageBuilder
     */
    protected function onBegin(Message $message)
    {
        if ($this->state->getPlayerCount() > 0) {
            return $this->state->getMessage($this->client->getMessageBuilder());
        }

        if (!$this->state->join($message->data['user'])) {
            return null;
        }

        $message->getUser()->then(function (User $user) {
            $db = $this->state->db;
            if (!$db->hasUser($user->getId())) {
                $db->createUser($user->getId(), $user->getRealName() ?? $user->getUsername());
            } else if (!$db->hasName($user->getId())) {
                $db->updateName($user->getId(), $user->getRealName() ?? $user->getUsername());
            }
        });


        return $this->state->getMessage($this->client->getMessageBuilder());
    }

    /**
     * @param Message $message
     * @return string|null
     */
    protected function onRoomStatus(Message $message)
    {
        $cookieString = getenv('ONE_WORK_COOKIE');
        $rooms = getenv('ONE_WORK_ROOMS');

        if (empty($cookieString) || empty($rooms)) {
            error_log("Empty room");
            return null;
        }

        $timezoneReal = new \DateTimeZone('Europe/Riga');
        $now = new \DateTime('now', $timezoneReal);

        $oneWorkUrl = 'https://1work.com/dashboard/booking';
        $oneWorkData = [
            'rooms' => $rooms,
            'start' => (clone $now)->setTime(0, 0, 0)->getTimestamp(),
            'end' => (clone $now)->setTime(23, 59, 58)->getTimestamp(),
        ];

        $query = http_build_query($oneWorkData);

        $ch = curl_init($oneWorkUrl . "?" . $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Cookie: ' . $cookieString,
            'X-Requested-With: XMLHttpRequest'
        ]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // error_log($oneWorkUrl . "?" . $query);

        if ($status !== 200) {
            error_log($response);
            error_log("Status != 200");
            return null;
        }

        if (empty($response)) {
            error_log("Empty response");
            return null;
        }

        // error_log($response);

        $events = json_decode($response, true);

        if (json_last_error()) {
            // error_log("JSON error");
            return null;
        }

        if (empty($events)) {
            return 'Istaba nav rezervēta!';
        }

        $eventsPrinted = [];

        usort($events, [$this, 'eventSort']);
        foreach ($events as $eventData) {
            $eventStartRaw = (int)$eventData['start'];
            $eventEndRaw = (int)$eventData['end'];

            $eventStart = (new \DateTime('@' . $eventStartRaw))->setTimezone($timezoneReal);
            $eventEnd = (new \DateTime('@' . $eventEndRaw))->setTimezone($timezoneReal);

            if ($now->getTimestamp() > $eventEnd->getTimestamp()) {
                // Event ended
                continue;
            }

            // Later than 2 hours
            if (($eventStart->getTimestamp() - $now->getTimestamp()) > 2 * 60 * 60) {
                continue;
            }

            if ($eventStart->getTimestamp() > $now->getTimestamp()) {
                $diff = $eventStart->diff($now);
                $eventsPrinted[] = sprintf("*%s* sāksies *%s* (%d)", $eventData["body"] ?: $eventData['title'], $eventStart->format('H:i'), $diff->h * 60 + $diff->i);
            } else if ($eventEnd->getTimestamp() > $now->getTimestamp()) {
                $diff = $now->diff($eventEnd);
                $eventsPrinted[] = sprintf("*%s* beigsies *%s* (%d)", $eventData["body"] ?: $eventData['title'], $eventEnd->format('H:i'), $diff->h * 60 + $diff->i);
            }
        }

        if (empty($eventsPrinted)) {
            return 'Istaba nav rezervēta!';
        }

        return "Pasākumi:\n" . implode("\n", $eventsPrinted);
    }

    private function eventSort(array $eventA, array $eventB)
    {
        return (int)$eventA['start'] <=> (int)$eventB['start'];
    }

    protected function onUrl(Message $message)
    {
        return 'https://github.com/waplet/simple_football_slack_bot/';
    }

    protected function onUpdateName(Message $message)
    {
        $message->getUser()->then(function (User $user) {
            if (!$this->state->db->hasUser($user->getId())) {
                return;
            }

            $this->state->db->updateName($user->getId(), $user->getRealName() ?? $user->getUsername());
        });

        return null;
    }
}