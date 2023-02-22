<?php

use w\Bot\FootballState;

include_once __DIR__ . './../init.php';

$footballState = new FootballState();
$db = $footballState->db;

echo "Executing 08_multi_channel.php\n";

$db->exec(<<<SQL
alter table active_game
	add channel_id varchar(30);
SQL
);
$db->exec(<<<SQL
alter table users
	add channel_id varchar(30);
SQL
);
