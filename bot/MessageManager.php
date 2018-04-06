<?php

namespace w\Bot;

use Slack\Message\Message;

class MessageManager extends AbstractMessageManager
{
    protected static $commands = [
        'ping' => 'onPing',
        'top' => 'onTop',
        'begin' => 'onBegin',
        'status' => 'onRoomStatus',
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
            $result .= ($k + 1) . '. <@' . $user['id'] . '> - Won: *'
                . $user['games_won'] . '* - Games played: *'
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

        if ($this->state->join($message->data['user'])) {
            return $this->state->getMessage($this->client->getMessageBuilder());
        }

        return null;
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
            error_log("Status 200");
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

        $events = array_reverse($events);
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
                $eventsPrinted[] = sprintf("*%s* sāksies *%s* (%d)", $eventData["body"], $eventStart->format('H:i'), $diff->h * 60 + $diff->i);
            } else if ($eventEnd->getTimestamp() > $now->getTimestamp()) {
                $diff = $now->diff($eventEnd);
                $eventsPrinted[] = sprintf("*%s* beigsies *%s* (%d)", $eventData["body"], $eventEnd->format('H:i'), $diff->h * 60 + $diff->i);
            }
        }

        if (empty($eventsPrinted)) {
            return 'Istaba nav rezervēta!';
        }

        return "Pasākumi:\n" . implode("\n", $eventsPrinted);
    }
}