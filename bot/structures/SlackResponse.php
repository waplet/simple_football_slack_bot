<?php

namespace w\Bot\structures;

/**
 * Response should be posted to Channel
 * Class SlackResponse
 * @package w\Bot\structures
 */
class SlackResponse
{
    public string $message;

    /**
     * Should be responded to same channel as incoming message.
     */
    public string $channel;
}