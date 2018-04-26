<?php

use w\Bot\FootballState;

include_once __DIR__ . './../init.php';

$footballState = new FootballState();
$db = $footballState->db;

// Elo main elo for the user
$db->exec(<<<SQL
ALTER TABLE games
  ADD who_won INT NOT NULL DEFAULT 0;
SQL
);

// 0 - Do not count
// 1 - A
// 2 - B