<?php

use w\Bot\FootballState;

include_once __DIR__ . './../init.php';

$footballState = new FootballState();
$db = $footballState->db;

// Elo main elo for the user
$db->exec(<<<SQL
ALTER TABLE users
  ADD name VARCHAR(100) NULL DEFAULT NULL;
SQL
);