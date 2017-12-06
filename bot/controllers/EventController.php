<?php

namespace w\Bot\controllers;

class EventController extends BaseController
{
    public function actionProcess()
    {
        $rawJson = file_get_contents('php://input');
        $json = json_decode($rawJson, JSON_OBJECT_AS_ARRAY);

        if (isset($json['type']) && $json['type'] == 'url_verification') {
            return $json['challenge'];
        }

        file_put_contents(__DIR__ . '/events.txt', json_encode(json_decode($rawJson), JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        return 'Empty request received!';
    }
}