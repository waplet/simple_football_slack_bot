<?php

namespace w\Bot;

use Slack\Message\Message;
use Slack\Message\MessageBuilder;
use Slack\RealTimeClient;

class PrivateMessageManager extends AbstractMessageManager
{
    protected static $commands = [
        'mygames' => 'onMyGames',
        'mg' => 'onMyGames',
        'list' => 'onList',
        'update' => 'onUpdate',
    ];

    /**
     * @param Message $message
     * @param string $argumentString
     * @return string
     */
    protected function onMyGames(Message $message, $argumentString = null)
    {
        $gamesArray = $this->state->db->getGames();

        $result = '';

        foreach ($gamesArray as $game) {
            $result = 'Play ID: ' . $game['play_id'] . ' Player 1: ' . $game['player_1'] . "\n";
        }

        return $result;
    }

    protected function onList(Message $message, $argumentString = null)
    {
        $unresolvedGames = $this->state->db->getOwnerUnresolvedGames($message->data['user']);

        if (empty($unresolvedGames)) {
            return 'Great! You have updated your games.';
        }

        $result = '';

        foreach ($unresolvedGames as $game) {
            $result .= 'Game ID: *#' . $game['id'] . "*\n"
                . "\t`,update " . $game['id'] . " A`\n"
                . "\tTeam *A*: <@" . $game['player_1'] . "> <@" . $game['player_2'] . "> \n"
                . "\t`,update " . $game['id'] . " B`\n"
                . "\tTeam *B*: <@" . $game['player_3'] . "> <@" . $game['player_4'] . "> `,update " . $game['id'] . " B`\n"
                . "==========================="
                . "\n";
        }

        return $result;
    }

    protected function onUpdate(Message $message, $argumentString = null)
    {
        if (is_null($argumentString)) {
            return 'Please provide some data';
        }

        $args = explode(" ", trim($argumentString));

        if (count($args) != 2) {
            return 'Check if you provided both game id and team which won';
        }

        // Team names
        if (!in_array($args[1], ['A', 'B' , '-'])) {
            return 'Please specify team: A or B or \'-\' (Did not play)!';
        }

        $gameId = $args[0];
        $userId = $message->data['user'];
        $teamAWon = $args[1] == 'A';
        $didNotPlay = $args[1] == '-';

        $game = $this->state->db->getOwnerGame($gameId, $userId);

        if (!$game) {
            return 'Game not found for specified ID';
        }

        if ($didNotPlay) {
            $this->state->db->deleteGame($gameId);

            return 'Game *#' . $gameId . '* deleted!';
        }

        $result = $this->state->db->updateOwnerGame($gameId, $userId, $teamAWon);

        if ($result) {
            return 'Game *#' . $gameId . '* updated!';
        }

        return 'Could not update game - *' . $gameId . '*!';
    }
}