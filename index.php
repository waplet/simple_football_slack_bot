<?php

use w\Bot\FootballState;

include_once __DIR__ . '/init.php';
$footballState = new FootballState(getenv('APP_BOT_PLAYERS_NEEDED'));
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

$controller = \w\Bot\controllers\BaseController::getController();
/** @var \w\Bot\controllers\BaseController $controllerInstance */
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
    if ($response instanceof \w\Bot\structures\HTTPResponse) {
        echo $response->message;
    } elseif (is_string($response)) {
        echo $response;
    } elseif ($response instanceof \w\Bot\structures\GameStateResponse) {
        header('Content-type: application/json');
        $messageBuilder = $footballState->getMessage();
        if (is_null($messageBuilder)) {
            return;
        }

        echo json_encode($messageBuilder, JSON_PRETTY_PRINT);
    // Event responses
    } elseif ($response instanceof \w\Bot\structures\SlackResponse) {
        $client->chatPostMessage(
            [
                'channel' => getenv('APP_BOT_CHANNEL'),
                'text' => $response->message,
                'mrkdwn' => true,
            ]
        );
    } elseif (is_array($response)) {
        $response['channel'] = getenv('APP_BOT_CHANNEL');
        if (isset($response['attachments'])) {
            $response['attachments'] = json_encode($response['attachments']);
        }
        $client->chatPostMessage($response);
    }
}