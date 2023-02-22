<?php

use w\Bot\controllers\BaseController;
use w\Bot\FootballState;
use w\Bot\structures\GameStateResponse;
use w\Bot\structures\SlackResponse;

include_once __DIR__ . '/init.php';
$footballState = new FootballState((int)getenv('APP_BOT_PLAYERS_NEEDED'));
$client = JoliCode\Slack\ClientFactory::create(getenv('APP_BOT_OAUTH_TOKEN'));

$payload = null;
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (is_null($payload)) {
    $payload = json_decode($_POST['payload'], true);
}
if (empty($payload)) {
    $payload = [];
}

$controller = BaseController::getController();
/** @var BaseController $controllerInstance */
$controllerInstance = new $controller($client, $footballState->db, $footballState, $payload);

try {
    $response = $controllerInstance->process();
} catch (InvalidArgumentException $e) {
    echo $e->getMessage();
    die;
}
// error_log(print_r($response, true));
if (!is_null($response)) {
    // Action Responses
    if (is_string($response)) {
        header("Content-Type: text/plain");
        echo $response;
        return;
    } elseif ($response instanceof GameStateResponse) {
        header('Content-type: application/json');
        $messageBuilder = $footballState->getGameStateResponse($response->channel);
        if (is_null($messageBuilder)) {
            return;
        }

        echo json_encode($messageBuilder, JSON_PRETTY_PRINT);
    // Event responses
    } elseif ($response instanceof SlackResponse) {
        $client->chatPostMessage(
            [
                'channel' => $response->channel,
                'text' => $response->message,
                'mrkdwn' => true,
            ]
        );
    } elseif (is_array($response)) {
        if (!isset($response['channel'])) {
            $response['channel'] = getenv('APP_BOT_CHANNEL');
        }
        if (isset($response['attachments'])) {
            $response['attachments'] = json_encode($response['attachments']);
        }
        $client->chatPostMessage($response);
    }
}