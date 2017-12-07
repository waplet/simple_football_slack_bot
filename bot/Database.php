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

        $data = $query->execute()->fetchArray(SQLITE3_ASSOC);
        $query->close();

        if (!empty($data)) {
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
    public function getTopList()
    {
        $query = $this->prepare('
            SELECT * from users
            ORDER BY games_won desc, games_played ASC
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
    public function createGameInstances($owner, array $players)
    {
        if (count($players) != 4) {
            return -1;
        }

        $playId = $this->createPlay($owner);

        $this->createGameInstance($playId, $players[0], $players[1], $players[2], $players[3]); // AB CD
        $this->createGameInstance($playId, $players[0], $players[2], $players[1], $players[3]); // AC BD
        $this->createGameInstance($playId, $players[0], $players[3], $players[1], $players[2]); // AD BC

        return $playId;
    }

    /**
     * @param string $owner
     * @return int
     */
    private function createPlay($owner): int
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

    public function getOwnerUnresolvedGames($ownerId)
    {
        $query = $this->prepare('
            SELECT g.* FROM games g
              INNER JOIN plays p ON p.id = g.play_id
              WHERE p.owner = :ownerId
              ORDER BY g.play_id
        ');
        $query->bindParam('ownerId', $ownerId);

        $result = $query->execute();

        $data = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * @param $gameId
     * @param $ownerId
     * @return array|bool
     */
    public function getOwnerGame($gameId, $ownerId)
    {
        $query = $this->prepare('
            SELECT g.* FROM games g
              INNER JOIN plays p ON p.id = g.play_id
              WHERE p.owner = :ownerId
                AND g.id = :gameId
              ORDER BY g.play_id
        ');
        $query->bindParam('ownerId', $ownerId);
        $query->bindParam('gameId', $gameId);

        $result = $query->execute();

        if (!$result) {
            return false;
        }

        return $result->fetchArray(SQLITE3_ASSOC);
    }

    /**
     * @param int $gameId
     */
    public function deleteGame($gameId)
    {
        $query = $this->prepare('DELETE FROM games where id = :gameId');
        $query->bindParam('gameId', $gameId);
        $query->execute();
    }

    /**
     * @param int $gameId
     * @return bool
     */
    public function markGameAsDeleted($gameId)
    {
        $query = $this->prepare('UPDATE games SET status = :status WHERE id = :gameId');
        $query->bindParam('gameId', $gameId);
        $query->bindValue('status', 'deleted');

        $query->execute();

        return true;
    }

    /**
     * @param int $gameId
     * @param string $ownerId
     * @param bool $teamAWon
     * @return bool
     * @throws \Exception
     */
    public function updateOwnerGame($gameId, $ownerId, $teamAWon = true)
    {
        $game = $this->getOwnerGame($gameId, $ownerId);

        if (!$game) {
            return false;
        }

        $this->incrementUserGames($game['player_1'], 1, $teamAWon ? 1 : 0);
        $this->incrementUserGames($game['player_2'], 1, $teamAWon ? 1 : 0);
        $this->incrementUserGames($game['player_3'], 1, $teamAWon ? 0 : 1);
        $this->incrementUserGames($game['player_4'], 1, $teamAWon ? 0 : 1);

        $this->deleteGame($game['id']);

        return true;
    }

    /**
     * @param $gameId
     * @param bool $teamAWon
     * @return bool
     * @throws \Exception
     */
    public function updateGame($gameId, $teamAWon = true)
    {
        $game = $this->getGame($gameId);

        if (!$game) {
            return false;
        }

        $this->incrementUserGames($game['player_1'], 1, $teamAWon ? 1 : 0);
        $this->incrementUserGames($game['player_2'], 1, $teamAWon ? 1 : 0);
        $this->incrementUserGames($game['player_3'], 1, $teamAWon ? 0 : 1);
        $this->incrementUserGames($game['player_4'], 1, $teamAWon ? 0 : 1);

        $this->markGameAsDeleted($game['id']);

        return true;
    }

    /**
     * @param int $gameId
     * @return array
     */
    public function getGame($gameId)
    {
        $query = $this->prepare('
            SELECT * FROM games
            where id = :gameId
        ');
        $query->bindParam('gameId', $gameId);

        return $query->execute()->fetchArray(SQLITE3_ASSOC);
    }

    /**
     * @return array|bool
     */
    public function getActiveGame()
    {
        $result = $this->prepare('
            SELECT * FROM active_game
        ')->execute()->fetchArray();

        if (!$result) {
            return false;
        }

        return $result;
    }

    public function getStatus()
    {
        $game = $this->getActiveGame();

        if (!$game) {
            return 'none';
        }

        return $game['status'];
    }

    /**
     * @return string[]
     */
    public function getPlayers()
    {
        $game = $this->getActiveGame();

        if (!$game) {
            return [];
        }

        return json_decode($game['players'], JSON_OBJECT_AS_ARRAY);
    }

    public function addPlayer($player)
    {
        $game = $this->getActiveGame();

        if (!$game) {
            return false;
        }

        $players = (array)json_decode($game['players'], JSON_OBJECT_AS_ARRAY);
        $players = array_values($players);
        $players[] = $player;

        $query = $this->prepare('UPDATE active_game set players = :players');
        $players = json_encode($players);
        $query->bindParam('players', $players);
        $query->execute();

        return true;
    }

    public function removePlayer($player)
    {
        $game = $this->getActiveGame();

        if (!$game) {
            return false;
        }

        $players = (array)json_decode($game['players'], JSON_OBJECT_AS_ARRAY);
        $playersAfter = array_values(array_diff($players, [$player]));

        if (count($players) == count($playersAfter)) {
            return false;
        }

        $query = $this->prepare('UPDATE active_game SET players = :players');
        $playersAfter = json_encode($playersAfter);
        $query->bindParam('players', $playersAfter);
        $query->execute();

        return true;
    }

    /**
     * @param int $ownerId
     */
    public function deleteAllOwnerGames($ownerId)
    {
        $query = $this->prepare('
          DELETE FROM games
          WHERE play_id IN (SELECT id FROM plays WHERE owner = :ownerId)
        ');
        $query->bindParam('ownerId', $ownerId);
        $query->execute();
    }

    public function clearActiveGame()
    {
        $this->exec('DELETE FROM active_game');

        return true;
    }

    public function createActiveGame($player)
    {
        $query = $this->prepare('
            INSERT INTO active_game(players, status) 
            VALUES(:players, \'pending\')
        ');
        $players = json_encode([$player]);
        $query->bindParam('players', $players);
        $query->execute();

        return true;
    }

    /**
     * @param int $playId
     * @return bool
     */
    public function startGame($playId)
    {
        $query = $this->prepare('
            UPDATE active_game
              SET status = \'started\',
              play_id = :playId
        ');

        $query->bindParam('playId', $playId);
        $query->execute();

        return true;
    }

    /**
     * @return array
     */
    public function getActiveGameInstances()
    {
        $game = $this->getActiveGame();

        if (!$game) {
            return [];
        }

        $query = $this->prepare('
            SELECT g.* FROM games g
              INNER JOIN plays p ON p.id = g.play_id
              WHERE p.id = :playId
        ');
        $query->bindParam('playId', $game['play_id']);
        $result = $query->execute();

        $data = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }
}