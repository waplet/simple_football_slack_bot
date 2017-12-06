<?php

namespace w\Bot\controllers;

class DefaultController extends BaseController
{
    public function process()
    {
        print_r($_GET);
        print_r($_POST);
    }
}