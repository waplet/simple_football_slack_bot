<?php

namespace w\Bot;

class Database extends \SQLite3
{
    function __construct()
    {
        parent::__construct(ROOT . '/data/football.db');
    }

    /**
     * @param string $userId
     * @return bool
     */
    public function hasUser($userId)
    {
        $query = $this->prepare('SELECT id FROM users WHERE id = :userId');
        $query->bindParam('userId', $userId);

        $data = $query->execute();
        $query->close();

        if ($data) {
            return true;
        }

        return false;
    }

    /**
     * @param string $userId
     * @return bool
     */
    public function createUser($userId)
    {
        $query = $this->prepare('INSERT INTO users(id, games_played, games_won) VALUES (:userId, 0, 0)');
        $query->bindParam('userId', $userId);

        $result = $query->execute();

        if ($result) {
            return true;
        }

        return false;
    }

    /**
     * @param string $userId
     * @param int $gamesPlayed
     * @param int $gamesWon
     * @return bool
     * @throws \Exception
     */
    public function incrementUserGames($userId, $gamesPlayed, $gamesWon)
    {
        if (!$this->hasUser($userId)) {
            if (!$this->createUser($userId)) {
                throw new \Exception('Could not create user!');
            }
        }

        $query = $this->prepare('
  UPDATE users
  SET 
    games_played = games_played + :gamesPlayed,
    games_won = games_won + :gamesWon 
  WHERE id = :userId;'
        );
        $query->bindParam('userId', $userId);
        $query->bindParam('gamesPlayed', $gamesPlayed);
        $query->bindParam('gamesWon', $gamesWon);

        $result = $query->execute();

        if ($result) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getStats()
    {
        $query = $this->prepare('
            SELECT * from users
        ');
        $result = $query->execute();

        $data = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }
        $query->close();

        return $data;
    }

    public function getGames()
    {
        $query = $this->prepare('
            SELECT * from games
        ');

        $result = $query->execute();

        $data = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }
        $query->close();

        return $data;
    }

    public function clearDatabase()
    {
        $this->exec('DELETE FROM users');
        $this->exec('DELETE FROM games');
        $this->exec('DELETE FROM plays');

        return true;
    }

    /**
     * @param array $players
     * @return int
     */
    public function createGameInstances(array $players)
    {
        if (count($players) != 4) {
            return -1;
        }

        $playId = $this->createPlay($players[0]);

        $this->createGameInstance($playId, $players[0], $players[1], $players[2], $players[3]); // AB CD
        $this->createGameInstance($playId, $players[0], $players[2], $players[1], $players[3]); // AC BD
        $this->createGameInstance($playId, $players[0], $players[3], $players[1], $players[2]); // AD BC

        return $playId;
    }

    /**
     * @param string $owner
     * @return int
     */
    private function createPlay($owner) :int
    {
        $query = $this->prepare('INSERT INTO plays(owner) VALUES(:owner)');
        $query->bindParam('owner', $owner);
        $query->execute();

        return $this->lastInsertRowID();
    }

    /**
     * @param int $playId
     * @param string $player1
     * @param string $player2
     * @param string $player3
     * @param string $player4
     */
    private function createGameInstance($playId, $player1, $player2, $player3, $player4)
    {
        $query = $this->prepare('
            INSERT INTO games(play_id, player_1, player_2, player_3, player_4)
            VALUES (:playId, :player1, :player2, :player3, :player4);
        ');
        $query->bindParam('playId', $playId);
        $query->bindParam('player1', $player1);
        $query->bindParam('player2', $player2);
        $query->bindParam('player3', $player3);
        $query->bindParam('player4', $player4);
        $query->execute();
    }
}