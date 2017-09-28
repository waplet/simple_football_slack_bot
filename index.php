<?php

use React\EventLoop\Factory;
use w\Bot\FootballState;

require __DIR__ . '/vendor/autoload.php';
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();
$dotenv->required('BOT_TOKEN');

$footballState = new FootballState();
$loop = Factory::create();

$client = new Slack\RealTimeClient($loop);
$client->setToken(getenv('BOT_TOKEN'));
$client->connect();
$messageManager = new \w\Bot\MessageManager($footballState, $client);

$client->on('message', function (\Slack\Payload $data) use ($client, $messageManager) {
    var_dump($data);
    $message = new \Slack\Message\Message($client, $data->getData());
    if (isset($data['subtype']) && $data['subtype'] == 'message_changed') {
        // Skip change events;
        return;
    }

    $message->getUser()->then(function (\Slack\User $user) use ($messageManager, $message, $client) {
        $responseBuilder = $messageManager->parseMessage($user->getUsername(), $message);

        if (is_null($responseBuilder)) {
            return;
        }

        $message->getChannel()->then(function (\Slack\Channel $channel) use ($client, $responseBuilder) {
            $response = $responseBuilder->setChannel($channel)->create();

            $client->postMessage($response);
        });
    });
});

echo "Started and running...\n";
$loop->run();