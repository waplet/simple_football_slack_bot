<?php

namespace w\Bot\controllers;

class EventController extends BaseController
{
    public function actionProcess()
    {
        if (isset($_POST['type']) && $_POST['type'] == 'url_verification') {
            return $_POST['challenge'];
        }

        return 'Empty request received!';
    }
}