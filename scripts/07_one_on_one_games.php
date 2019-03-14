<?php

use w\Bot\FootballState;

include_once __DIR__ . './../init.php';

$footballState = new FootballState();
$db = $footballState->db;

//
$db->exec(<<<SQL
create table games_dg_tmp
(
	id INTEGER
		primary key autoincrement,
	play_id INTEGER not null,
	status VARCHAR(10) default 'inprogress' not null,
	player_1 VARCHAR(30) not null,
	player_2 VARCHAR(30) not null,
	player_3 VARCHAR(30),
	player_4 VARCHAR(30),
	who_won INT default 0 not null
);

insert into games_dg_tmp(id, play_id, status, player_1, player_2, player_3, player_4, who_won) select id, play_id, status, player_1, player_2, player_3, player_4, who_won from games;

drop table games;

alter table games_dg_tmp rename to games;
SQL
);
