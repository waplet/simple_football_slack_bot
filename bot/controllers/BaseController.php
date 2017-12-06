<?php

namespace w\Bot\controllers;

use Slack\ApiClient;
use w\Bot\Database;
use w\Bot\FootballState;

abstract class BaseController
{
    protected $db;
    protected $client;
    protected $footballState;

    private static $routes = [
        '/event' => EventController::class,
        '/default' => DefaultController::class,
    ];

    function __construct(ApiClient $client, Database $db, FootballState $footballState)
    {
        $this->client = $client;
        $this->db = $db;
        $this->footballState = $footballState;
    }

    /**
     * @return string
     */
    public static function getController()
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

    abstract public function actionProcess();
}