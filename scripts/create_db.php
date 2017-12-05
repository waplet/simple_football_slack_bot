<?php

include_once __DIR__ . './../init.php';

$db = new \w\Bot\Database();

// $db->exec(<<<SQL
// CREATE TABLE users (
//   id VARCHAR(30) PRIMARY KEY UNIQUE NOT NULL,
//   games_played INT NOT NULL DEFAULT 0,
//   games_won INT NOT NULL DEFAULT 0
// );
// SQL
// );
// $db->exec('DROP TABLE games');
// $db->exec(<<<SQL
// CREATE TABLE games (
//   id INTEGER PRIMARY KEY AUTOINCREMENT,
//   play_id INTEGER NOT NULL,
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
// SQL
// );

$user = getenv('ADMIN');
// $db->clearDatabase();
// $db->hasUser($user);
// $db->createUser($user);
// $db->createUser($user . '1');
print_r($db->getStats());
// $db->incrementUserGames($user, 5, 3);
print_r($db->getStats());
// $db->createGameInstances([1,2,3,4]);
print_r($db->getGames());
