<?php

use w\Bot\FootballState;

include_once __DIR__ . './../init.php';

$footballState = new FootballState();
$db = $footballState->db;
//
// $db->exec(<<<SQL
// CREATE TABLE users (
//   id VARCHAR(30) PRIMARY KEY UNIQUE NOT NULL,
//   games_played INT NOT NULL DEFAULT 0,
//   games_won INT NOT NULL DEFAULT 0
// );
//SQL
// );
// $db->exec('DROP TABLE games');
// $db->exec(<<<SQL
// CREATE TABLE games (
//   id INTEGER PRIMARY KEY AUTOINCREMENT,
//   play_id INTEGER NOT NULL,
//   status VARCHAR(10) NOT NULL DEFAULT 'inprogress',
//   player_1 VARCHAR(30) NOT NULL,
//   player_2 VARCHAR(30) NOT NULL,
//   player_3 VARCHAR(30) NOT NULL,
//   player_4 VARCHAR(30) NOT NULL
// );
// SQL
// );

// $db->exec('DROP TABLE plays');
// $db->exec(<<<SQL
// CREATE TABLE plays (
//   id INTEGER PRIMARY KEY AUTOINCREMENT,
//   owner VARCHAR(30) NOT NULL
// );
//SQL
// );
// $db->exec('DROP TABLE active_game');
// $db->exec(<<<SQL
//    CREATE TABLE active_game (
//      players TEXT DEFAULT '',
//      status VARCHAR(10) DEFAULT 'pending',
//      play_id INTEGER NULL DEFAULT NULL
//    );
// SQL
// );

//$user = getenv('ADMIN');
// $db->clearDatabase();
// $db->hasUser($user);
// $db->createUser($user);
// $db->createUser($user . '1');
//print_r($db->getStats());
// $db->incrementUserGames($user, 5, 3);
//print_r($db->getStats());
// $db->createGameInstances([1,2,3,4]);
//print_r($db->getGames());

$db->clearActiveGame();
$db->createActiveGame('U7A4L7NN6');
$playersJoined = [1,2,3];
foreach ($playersJoined as $player) {
    $db->addPlayer($player);
}