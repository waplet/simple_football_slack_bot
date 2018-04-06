<?php

use w\Bot\FootballState;

include_once __DIR__ . './../init.php';

$footballState = new FootballState();
$db = $footballState->db;

// Elo main elo for the user
$db->exec(<<<SQL
ALTER TABLE users
  ADD current_elo INT NOT NULL DEFAULT 1000;
SQL
);

// Elo to be calculated between all three games
$db->exec(<<<SQL
ALTER TABLE users
  ADD temp_elo INT NOT NULL DEFAULT 0;
SQL
);
