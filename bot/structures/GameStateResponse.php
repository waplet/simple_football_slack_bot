<?php

namespace w\Bot\structures;

/**
 * Response indicating that final response should respond with FootballState::getMessage()
 * Class GameStateResponse
 * @package w\Bot\structures
 */
class GameStateResponse
{
    /**
     * Same as incoming payload's
     * @var string
     */
    public string $channel;
}