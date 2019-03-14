<?php

use React\EventLoop\Factory;
use w\Bot\FootballState;

include_once __DIR__ . '/init.php';
$footballState = new FootballState(getenv('APP_BOT_PLAYERS_NEEDED'));
$loop = Factory::create();

$client = new Slack\ApiClient($loop);
$client->setToken(getenv('APP_BOT_OAUTH_TOKEN'));

$payload = null;
$input = false;
try {
    $input = file_get_contents('php://input');
    $payload = \Slack\Payload::fromJSON($input);
} catch (UnexpectedValueException $e) {
    if (!isset($_POST['payload'])) {
        error_log($e->getMessage());
        return;
    }

    $payload = \Slack\Payload::fromJSON($_POST['payload']);
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
    if ($response instanceof \w\Bot\structures\HTTPResponse) {
        echo $response->message;
    } else if ($response instanceof \w\Bot\structures\GameStateResponse) {
        header('Content-type: application/json');
        $messageBuilder = $footballState->getMessage($client->getMessageBuilder());
        if (is_null($messageBuilder)) {
            return;
        }
        echo json_encode($footballState->getMessage($client->getMessageBuilder())->create()->jsonSerialize(), JSON_PRETTY_PRINT);
    } else if (is_string($response)) {
        echo $response;
    } else if ($response instanceof \w\Bot\structures\SlackResponse) {
        $client->getChannelById(getenv('APP_BOT_CHANNEL'))->then(function (\Slack\Channel $channel) use ($client, $response) {
            $client->send($response->message, $channel);
        });
    } else if ($response instanceof \Slack\Message\MessageBuilder) {
        $client->getChannelById(getenv('APP_BOT_CHANNEL'))->then(function (\Slack\Channel $channel) use ($client, $response) {
            $client->postMessage($response->setChannel($channel)->create());
        });
    }
}
$loop->run();