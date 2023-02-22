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
drop table users;
SQL
);

$db->exec(<<<SQL
create table users
(
    id           VARCHAR(30) not null,
    games_played INT          default 0 not null,
    games_won    INT          default 0 not null,
    current_elo  INT          default 1000 not null,
    temp_elo     INT          default 0 not null,
    name         VARCHAR(100) default NULL,
    previous_elo INT          default 0 not null,
    last_played  DATETIME,
    channel_id   varchar(30)
);
SQL
);

$db->exec(<<<SQL
create unique index users_id_channel_id_uindex
	on users (id, channel_id);
SQL
);

