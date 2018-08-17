<?php

use w\Bot\FootballState;

include_once __DIR__ . './../init.php';

$footballState = new FootballState();
$db = $footballState->db;

// Elo main elo for the user
$db->exec(<<<SQL
ALTER TABLE users
  ADD previous_elo INT NOT NULL DEFAULT 0;
SQL
);