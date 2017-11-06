<?php

namespace w\Bot;

class FootballState
{
    private $lastPlayTimestamp = 0;

    private $isLocked = false;

    private $playersJoined = [];

    private $playersNeeded = 4;

    /**
     * FootballState constructor.
     * @param int $playersNeeded
     */
    public function __construct($playersNeeded = 4)
    {
        if ($playersNeeded < 0 || $playersNeeded > 6) {
            $playersNeeded = 4;
        }

        $this->playersNeeded = $playersNeeded;

        return $this;
    }


    /**
     * Return true if joined, false if already present
     * @param string $player
     * @return bool
     */
    public function join($player): bool
    {
        if (in_array($player, $this->playersJoined, true)) {
            return false;
        }

        $this->playersJoined[] = $player;

        return true;
    }

    /**
     * Return true if removed player
     * @param string $player
     * @return bool
     */
    public function leave($player): bool
    {
        if (!in_array($player, $this->playersJoined)) {
            return false;
        }

        $this->playersJoined = array_values(array_filter($this->playersJoined, function ($currentPlayer) use ($player) {
            return $currentPlayer !== $player;
        }));

        return true;
    }

    public function getPlayerCount(): int
    {
        return count($this->playersJoined);
    }

    /**
     * @param string $player
     * @return bool
     */
    public function isManager($player): bool
    {
        if ($player === getenv('ADMIN')) {
            return true;
        }

        if ($this->getPlayerCount() == 0) {
            return false;
        }

        if ($player === $this->playersJoined[0]) {
            return true;
        }

        return false;
    }

    public function start($player): bool
    {
        if ($this->getPlayerCount() != $this->playersNeeded) {
            return false;
        }

        $this->lastPlayTimestamp = time();
        $this->playersJoined = [];

        return true;
    }

    public function getJoinedPlayers(): array
    {
        return $this->playersJoined;
    }

    public function getPlayersNeeded(): int
    {
        return $this->playersNeeded;
    }

    public function clear($username): bool
    {
        $this->playersJoined = [];

        return true;
    }
}