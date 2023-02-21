<?php

namespace w\Bot\controllers;

use InvalidArgumentException;
use JoliCode\Slack\Api\Client;
use w\Bot\Database;
use w\Bot\FootballState;
use w\Bot\structures\GameStateResponse;
use w\Bot\structures\SlackResponse;

abstract class BaseController
{
    protected Database $db;
    protected Client $client;
    protected FootballState $footballState;
    protected array $payload;

    protected array $types = [];

    private static array $routes = [
        '/event' => EventController::class,
        '/default' => DefaultController::class,
        '/action' => ActionController::class,
    ];

    function __construct(
        Client $client,
        Database $db,
        FootballState $footballState,
        array $payload
    ) {
        $this->client = $client;
        $this->db = $db;
        $this->footballState = $footballState;
        $this->payload = $payload;
    }

    /**
     * Returns controller Class name
     * @return string
     */
    public static function getController(): string
    {
        $requestUrl = $_SERVER['REQUEST_URI'];
        // strip GET variables from URL
        if (($pos = strpos($requestUrl, '?')) !== false) {
            $requestUrl = substr($requestUrl, 0, $pos);
        }

        if (isset(self::$routes[$requestUrl])) {
            return self::$routes[$requestUrl];
        }

        return self::$routes['/default'];
    }

    /**
     * Returns everything a controller might return
     * @return SlackResponse|GameStateResponse|array|string|null
     */
    public function process()
    {
        // Log request
        // file_put_contents(__DIR__ . '/events.txt',
        //     static::class . "\n" . json_encode($this->payload->getData(), JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        if (!isset($this->types[$this->payload['type']])) {
            throw new InvalidArgumentException('Invalid payload type received!');
        }

        return call_user_func([$this, $this->types[$this->payload['type']]]);
    }
}