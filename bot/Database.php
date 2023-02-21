<?php

namespace w\Bot;

use Exception;
use SQLite3;

class Database extends SQLite3
{
    const DF = 'Y-m-d H:i:s';

    function __construct()
    {
        parent::__construct(ROOT . '/data/football.db');
    }

    public function hasUser(string $channel, string $userId): bool
    {
        $query = $this->prepare('SELECT id FROM users WHERE id = :userId AND channel_id = :channelId');
        $query->bindParam('userId', $userId);
        $query->bindParam('channelId', $channel);

        $data = $query->execute()->fetchArray(SQLITE3_ASSOC);
        $query->close();

        if (!empty($data)) {
            return true;
        }

        return false;
    }

    public function hasName(string $channel, string $userId): bool
    {
        $query = $this->prepare('SELECT name FROM users WHERE id = :userId AND channel_id = :channelId');
        $query->bindParam('userId', $userId);
        $query->bindParam('channelId', $channel);

        $data = $query->execute()->fetchArray(SQLITE3_ASSOC);
        $query->close();

        if (!empty($data['name'])) {
            return true;
        }

        return false;
    }

    /**
     * @return array|bool
     */
    public function getUser(string $channel, string $userId)
    {
        $query = $this->prepare('SELECT * FROM users WHERE id = :userId AND channel_id = :channelId');
        $query->bindParam('userId', $userId);
        $query->bindParam('channelId', $channel);

        $data = $query->execute()->fetchArray(SQLITE3_ASSOC);
        $query->close();

        if (!empty($data)) {
            return $data;
        }

        return false;
    }

    public function createUser(string $channel, string $userId, ?string $name = null): bool
    {
        $query = $this->prepare('INSERT INTO users(id, games_played, games_won, name, channel_id) VALUES (:userId, 0, 0, :name, :channelId)');
        $query->bindParam('userId', $userId);
        $query->bindParam('name', $name);
        $query->bindParam('channelId', $channel);

        $result = $query->execute();

        if ($result) {
            return true;
        }

        return false;
    }

    public function updateName(string $channel, string $userId, ?string $name = null): bool
    {
        $query = $this->prepare('UPDATE users SET name = :name WHERE id = :userId AND channel_id = :channelId');
        $query->bindParam('userId', $userId);
        $query->bindParam('name', $name);
        $query->bindParam('channelId', $channel);

        $result = $query->execute();

        if ($result) {
            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function incrementUserGames(string $channel, string $userId, int $gamesPlayed, int $gamesWon): bool
    {
        if (!$this->hasUser($channel, $userId)) {
            if (!$this->createUser($channel, $userId)) {
                throw new Exception('Could not create user!');
            }
        }

        $query = $this->prepare('
  UPDATE users
  SET 
    games_played = games_played + :gamesPlayed,
    games_won = games_won + :gamesWon, 
    last_played = :lastPlayed
  WHERE id = :userId
     AND channel_id = :channelId
;'
        );
        $query->bindParam('userId', $userId);
        $query->bindParam('gamesPlayed', $gamesPlayed);
        $query->bindParam('gamesWon', $gamesWon);
        $query->bindParam('channelId', $channel);
        $query->bindValue('lastPlayed', gmdate(self::DF));

        $result = $query->execute();

        if ($result) {
            return true;
        }

        return false;
    }

    public function getTopList(string $channel): array
    {
        $query = $this->prepare('
            SELECT * FROM users
            WHERE last_played BETWEEN DATE("now", "-1 month") AND CURRENT_TIMESTAMP
                AND channel_id = :channelId
            ORDER BY current_elo DESC, games_won DESC, games_played ASC
        ');
        $query->bindParam('channelId', $channel);
        $result = $query->execute();

        $data = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }
        $query->close();

        return $data;
    }

    public function createGameInstances(string $owner, array $players): int
    {
        $countPlayers = count($players);

        if (!in_array($countPlayers, [2, 4])) {
            return -1;
        }

        $playId = $this->createPlay($owner);

        if ($countPlayers == 4) {
            $this->createGameInstance($playId, $players[0], $players[1], $players[2], $players[3]); // AB CD
            $this->createGameInstance($playId, $players[0], $players[2], $players[1], $players[3]); // AC BD
            $this->createGameInstance($playId, $players[0], $players[3], $players[1], $players[2]); // AD BC
        } elseif ($countPlayers == 2) {
            $this->createGameInstance($playId, $players[0], $players[1]); // A B
        }

        return $playId;
    }

    /**
     * @param string $owner
     * @return int
     */
    private function createPlay(string $owner): int
    {
        $query = $this->prepare('INSERT INTO plays(owner) VALUES(:owner)');
        $query->bindParam('owner', $owner);
        $query->execute();

        return $this->lastInsertRowID();
    }

    private function createGameInstance(
        int $playId,
        string $player1,
        string $player2,
        ?string $player3 = null,
        ?string $player4 = null
    ): void {
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

    /**
     * @param int $gameId
     * @return bool
     */
    public function markGameAsDeleted(int $gameId): bool
    {
        $query = $this->prepare('UPDATE games SET status = :status WHERE id = :gameId');
        $query->bindParam('gameId', $gameId);
        $query->bindValue('status', 'deleted');

        $query->execute();

        return true;
    }

    public function markGameAsWon(int $gameId, bool $teamAWon): bool
    {
        $query = $this->prepare('UPDATE games SET who_won = :whoWon WHERE id = :gameId');
        $query->bindValue('gameId', $gameId);
        $query->bindValue('whoWon', $teamAWon ? 1 : 2);

        $query->execute();

        return true;
    }

    /**
     * @throws Exception
     * @noinspection PhpIfWithCommonPartsInspection
     */
    public function updateGame(string $channel, int $gameId, int $playersNeeded, bool $teamAWon = true): bool
    {
        $game = $this->getGame($gameId);

        if (!$game) {
            return false;
        }

        if ($playersNeeded == 2) {
            // 1 v 1
            $this->incrementUserGames($channel, $game['player_1'], 1, $teamAWon ? 1 : 0);
            $this->incrementUserGames($channel, $game['player_2'], 1, $teamAWon ? 0 : 1);
        } else {
            // 2 v 2
            $this->incrementUserGames($channel, $game['player_1'], 1, $teamAWon ? 1 : 0);
            $this->incrementUserGames($channel, $game['player_2'], 1, $teamAWon ? 1 : 0);
            $this->incrementUserGames($channel, $game['player_3'], 1, $teamAWon ? 0 : 1);
            $this->incrementUserGames($channel,$game['player_4'], 1, $teamAWon ? 0 : 1);
        }

        $this->markGameAsWon((int)$game['id'], $teamAWon);
        $this->markGameAsDeleted((int)$game['id']);

        /**
         * Add ELOs
         */
        $this->updateTemporaryElo($channel, $game, $playersNeeded, $teamAWon);


        return true;
    }

    public function getGame(int $gameId): array
    {
        $query = $this->prepare('
            SELECT * FROM games
            WHERE id = :gameId
        ');
        $query->bindParam('gameId', $gameId);

        return $query->execute()->fetchArray(SQLITE3_ASSOC);
    }

    /**
     * @param string $channel
     * @return array|bool
     */
    public function getActiveGame(string $channel)
    {
        $query = $this->prepare('
            SELECT * FROM active_game WHERE channel_id = :channelId
        ');
        $query->bindParam('channelId', $channel);
        $result = $query
            ->execute()
            ->fetchArray();

        if (!$result) {
            return false;
        }

        return $result;
    }

    public function getStatus(string $channel): string
    {
        $game = $this->getActiveGame($channel);

        if (!$game) {
            return 'none';
        }

        return $game['status'];
    }

    public function getPlayers(string $channel): array
    {
        $game = $this->getActiveGame($channel);

        if (!$game) {
            return [];
        }

        return json_decode($game['players'], JSON_OBJECT_AS_ARRAY);
    }

    public function addPlayer(string $channel, string $player): bool
    {
        $game = $this->getActiveGame($channel);

        if (!$game) {
            return false;
        }

        $players = (array)json_decode($game['players'], JSON_OBJECT_AS_ARRAY);
        $players = array_values($players);
        $players[] = $player;

        $query = $this->prepare('UPDATE active_game SET players = :players WHERE channel_id = :channelId');
        $players = json_encode($players);
        $query->bindParam('players', $players);
        $query->bindParam('channelId', $channel);
        $query->execute();

        return true;
    }

    public function removePlayer(string $channel, string $player): bool
    {
        $game = $this->getActiveGame($channel);

        if (!$game) {
            return false;
        }

        $players = (array)json_decode($game['players'], JSON_OBJECT_AS_ARRAY);
        $playersAfter = array_values(array_diff($players, [$player]));

        if (count($players) == count($playersAfter)) {
            return false;
        }

        $query = $this->prepare('UPDATE active_game SET players = :players WHERE channel_id = :channelId');
        $playersAfter = json_encode($playersAfter);
        $query->bindParam('players', $playersAfter);
        $query->bindParam('channelId', $channel);
        $query->execute();

        return true;
    }

    public function clearActiveGame(string $channel): bool
    {
        $query = $this->prepare('DELETE FROM active_game WHERE channel_id = :channelId');
        $query->bindParam('channelId', $channel);

        $query->execute();

        return true;
    }

    public function createActiveGame(string $channel, string $player): bool
    {
        $query = $this->prepare('
            INSERT INTO active_game(players, status, channel_id) 
            VALUES(:players, :status, :channelId)
        ');
        $players = json_encode([$player]);
        $query->bindParam('players', $players);
        $query->bindParam('channelId', $channel);
        $query->bindValue('status', 'pending');
        $query->execute();

        return true;
    }

    public function startGame(string $channel, int $playId): bool
    {
        $query = $this->prepare('
            UPDATE active_game
              SET status = :status,
              play_id = :playId
            WHERE channel_id = :channelId
        ');

        $query->bindParam('playId', $playId);
        $query->bindParam('channelId', $channel);
        $query->bindValue('status', 'started');
        $query->execute();

        return true;
    }

    public function getActiveGameInstances(string $channel): array
    {
        $game = $this->getActiveGame($channel);

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

    /**
     * Summarizes total ELO of players, which were earned in games
     */
    public function summarizeElo(string $channel)
    {
        $params = [
            'channelId' => $channel,
        ];

        $this->execWithParams('UPDATE users SET previous_elo = current_elo WHERE channel_id = :channelId', $params);
        $this->execWithParams('UPDATE users SET current_elo = current_elo + temp_elo  WHERE channel_id = :channelId', $params);
        $this->execWithParams('UPDATE users SET temp_elo = 0 WHERE channel_id = :channelId', $params); // Reset temporary elo for all users
    }

    private function execWithParams(string $query, array $params = []): void
    {
        $query = $this->prepare($query);

        foreach ($params as $key => $value) {
            $query->bindParam($key, $value);
        }

        $query->execute();
    }

    private function updateUserElo(string $channel, string $userId, int $tempElo): void
    {
       $query = $this->prepare('UPDATE users SET temp_elo = temp_elo + :tempElo WHERE id = :userId AND channel_id = :channelId');

       $query->bindParam('tempElo', $tempElo);
       $query->bindParam('userId', $userId);
       $query->bindParam('channelId', $channel);
       $query->execute();
    }

    /**
     * @param string $channel
     * @param array $game data from table games
     * @param int $playersNeeded
     * @param bool $teamAWon
     */
    private function updateTemporaryElo(string $channel, array $game, int $playersNeeded, bool $teamAWon): void
    {
        $gamePlayers = [];
        for ($i = 1; $i <= $playersNeeded; $i++) {
            $gamePlayers[] = $this->getUser($channel, $game['player_' . $i]);
        }

        if ($playersNeeded == 2) {
            $this->updateUserElo(
                $channel,
                $gamePlayers[0]['id'],
                EloManager::getTempElo(
                    $gamePlayers[0]['current_elo'],
                    $gamePlayers[1]['current_elo'],
                    $teamAWon
                )
            );

            $this->updateUserElo(
                $channel,
                $gamePlayers[1]['id'],
                EloManager::getTempElo(
                    $gamePlayers[1]['current_elo'],
                    $gamePlayers[0]['current_elo'],
                    !$teamAWon
                )
            );
        } else {
            $this->updateUserElo(
                $channel,
                $gamePlayers[0]['id'],
                EloManager::getTempElo(
                    ($gamePlayers[0]['current_elo'] + $gamePlayers[1]['current_elo']) / 2,
                    ($gamePlayers[2]['current_elo'] + $gamePlayers[3]['current_elo']) / 2,
                    $teamAWon
                )
            );
            $this->updateUserElo(
                $channel,
                $gamePlayers[1]['id'],
                EloManager::getTempElo(
                    ($gamePlayers[0]['current_elo'] + $gamePlayers[1]['current_elo']) / 2,
                    ($gamePlayers[2]['current_elo'] + $gamePlayers[3]['current_elo']) / 2,
                    $teamAWon
                )
            );
            $this->updateUserElo(
                $channel,
                $gamePlayers[2]['id'],
                EloManager::getTempElo(
                    ($gamePlayers[2]['current_elo'] + $gamePlayers[3]['current_elo']) / 2,
                    ($gamePlayers[0]['current_elo'] + $gamePlayers[1]['current_elo']) / 2,
                    !$teamAWon
                )
            );
            $this->updateUserElo(
                $channel,
                $gamePlayers[3]['id'],
                EloManager::getTempElo(
                    ($gamePlayers[2]['current_elo'] + $gamePlayers[3]['current_elo']) / 2,
                    ($gamePlayers[0]['current_elo'] + $gamePlayers[1]['current_elo']) / 2,
                    !$teamAWon
                )
            );
        }
    }

	public function getEloDeltas(string $channel): array
    {
		$query = $this->prepare('SELECT `id`, `name`, `temp_elo`, `current_elo` FROM users WHERE `temp_elo` and channel_id = :channelId');
        $query->bindParam('channelId', $channel);
		$result = $query->execute();
		$data = [];

		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			$data[] = $row;
		}
		$query->close();

		return $data;
	}
}
