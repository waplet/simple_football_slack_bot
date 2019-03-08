<?php

use w\Bot\FootballState;

include_once __DIR__ . './../init.php';

$footballState = new FootballState();
$db = $footballState->db;

//
$db->exec(<<<SQL
ALTER TABLE users
  ADD last_played DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
SQL
);

//Update current users to current timestamp, so they dont hide for now.
$db->exec(<<<SQL
UPDATE users SET last_played = CURRENT_TIMESTAMP;
SQL
);
