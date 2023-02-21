<?php

namespace w\Bot\controllers;

use Exception;
use InvalidArgumentException;
use w\Bot\structures\GameStateResponse;
use w\Bot\structures\SlackResponse;

class ActionController extends BaseController
{
    protected array $types = [
        /** {@see ActionController::processInteractiveMessage()} */
        'interactive_message' => 'processInteractiveMessage',
    ];

    protected array $callbacks = [
        'game' => [
            /** {@see ActionController::processGameActions()} */
            'method' => 'processGameActions',
            'actions' => [
                /** {@see ActionController::onGameJoin()} */
                /** {@see ActionController::onGameLeave()} */
                /** {@see ActionController::onGameStart()} */
                /** {@see ActionController::onGameCancel()} */
                /** {@see ActionController::onGameRefresh()} */
                /** {@see ActionController::onGameNotify()} */
                'join' => 'onGameJoin',
                'leave' => 'onGameLeave',
                'start' => 'onGameStart',
                'cancel' => 'onGameCancel',
                'refresh' => 'onGameRefresh',
                'notify' => 'onGameNotify',
            ],
        ],
        'update' => [
            /** {@see ActionController::processUpdateActions()} */
            'method' => 'processUpdateActions',
        ],
    ];

    public function processInteractiveMessage()
    {
        $callbackId = $this->payload['callback_id'];
        if (!isset($this->callbacks[$callbackId])) {
            throw new InvalidArgumentException('Invalid payload callback received!');
        }

        $actions = $this->payload['actions'];

        return call_user_func([$this, $this->callbacks[$callbackId]['method']], $actions);
    }

    /**
     * @param array[] $actions
     */
    public function processGameActions(array $actions)
    {
        $player = $this->payload['user']['id'];
        $channel = $this->payload['channel']['id'];
        $action = reset($actions);

        $actions = $this->callbacks['game']['actions'];
        if (!isset($actions[$action['value']])) {
            throw new InvalidArgumentException('Invalid game action received!');
        }

        return call_user_func([$this, $actions[$action['value']]], $channel, $player);
    }

    protected function onGameJoin(string $channel, string $player): ?GameStateResponse
    {
        if ($this->footballState->amI($channel, $player)) {
            return null;
        }

        if ($this->footballState->getPlayerCount($channel) === $this->footballState->getPlayersNeeded()) {
            // Full!
            return null;
        }

        if (!$this->footballState->join($channel, $player)) {
            // You have already joined!
            return null;
        }

        $usersInfo = $this->client->usersInfo(['user' => $player]);
        $user = $usersInfo ? $usersInfo->getUser() : null;
        if ($user) {
            if (!$this->db->hasUser($channel, $user->getId())) {
                $this->db->createUser($channel, $user->getId(), $user->getRealName() ?? $user->getName());
            } elseif (!$this->db->hasName($channel, $user->getId())) {
                $this->db->updateName($channel, $user->getId(), $user->getRealName() ?? $user->getName());
            }
        }

        $gameStateResponse = new GameStateResponse;
        $gameStateResponse->channel = $channel;

        return $gameStateResponse;
    }

    /**
     * @return string|GameStateResponse
     */
    protected function onGameLeave(string $channel, string $player)
    {
        if (!$this->footballState->amI($channel, $player)) {
            return null;
        }

        if (!$this->footballState->leave($channel, $player)) {
            return null;
        }

        if ($this->footballState->getPlayerCount($channel) == 0) {
            $this->db->clearActiveGame($channel);

            return 'Cancelled!';
        }

        $gameStateResponse = new GameStateResponse;
        $gameStateResponse->channel = $channel;

        return $gameStateResponse;
    }

    protected function onGameCancel(string $channel, string $player): ?string
    {
        if (!$this->footballState->isManager($channel, $player)) {
            return null;
        }

        $this->footballState->clear($channel);

        return 'Cancelled!';
    }

    /**
     * @param string $channel
     * @param string $player
     * @return null|GameStateResponse
     */
    protected function onGameStart(string $channel, string $player): ?GameStateResponse
    {
        if (!$this->footballState->isManager($channel, $player)) {
            return null;
        }

        if (!$this->footballState->start($channel, $player)) {
            return null;
        }

        $gameStateResponse = new GameStateResponse;
        $gameStateResponse->channel = $channel;

        return $gameStateResponse;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function onGameRefresh(string $channel, string $player): GameStateResponse
    {
        $gameStateResponse = new GameStateResponse;
        $gameStateResponse->channel = $channel;

        return $gameStateResponse;
    }

    /**
     * Writes a Slack message triggering notifications for all current players
     */
    protected function onGameNotify(string $channel, string $player): ?SlackResponse
    {
        if (!$this->footballState->isManager($channel, $player)) {
            return null;
        }

        if (!$this->footballState->isFirst($channel, $player)) {
            // Only first should notify
            return null;
        }

        $response = new SlackResponse();
        $response->channel = $channel;
        $response->message = "Ready? " . implode(' ', array_map(function ($userId) {
                return '<@' . $userId . '>';
            }, $this->footballState->getJoinedPlayers($channel)));

        return $response;
    }

    /**
     * @param array[] $actions
     * @return null
     * @throws Exception
     */
    protected function processUpdateActions(array $actions)
    {
        $player = $this->payload['user']['id'];
        $channel = $this->payload['channel']['id'];
        $gameStateResponse = new GameStateResponse();
        $gameStateResponse->channel = $channel;
        $action = reset($actions);

        if (!$this->footballState->isManager($channel, $player)) {
            return null;
        }

        $name = $action['name'];
        error_log(print_r($action, true));
        $nameSplit = explode('_', $name);
        if (count($nameSplit) !== 2) {
            return null;
        }

        $won = $action['value'];

        // Team names
        if (!in_array($won, ['A', 'B', '-'])) {
            return null;
        }

        $gameId = $nameSplit[1];
        $teamAWon = $won === 'A';
        $didNotPlay = $won === '-';

        if ($didNotPlay) {
            $this->db->markGameAsDeleted((int)$gameId);

            if ($this->footballState->isFinishedGame($channel)) {
                $this->footballState->db->clearActiveGame($channel);
                $this->footballState->db->summarizeElo($channel);

                return 'Finished';
            }

            return $gameStateResponse;
        }

        if (!$this->db->updateGame($channel, (int)$gameId, $this->footballState->getPlayersNeeded(), $teamAWon)) {
            return null;
        }

        if ($this->footballState->isFinishedGame($channel)) {
			$deltas = $this->footballState->db->getEloDeltas($channel);
			$response = "Finished";
			if ($deltas) {
				foreach ($deltas as $delta) {
					$response .= "\n<@".$delta['id'].'> ('. $delta['current_elo'].') '.($delta['temp_elo']<0?'':'+').$delta['temp_elo'];
				}
			}

			$this->footballState->db->clearActiveGame($channel);
			$this->footballState->db->summarizeElo($channel);

			return $response;
        }

        return $gameStateResponse;
    }
}