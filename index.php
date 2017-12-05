<?php

use React\EventLoop\Factory;
use Slack\ChannelInterface;
use Slack\ClientObject;
use Slack\DirectMessageChannel;
use w\Bot\FootballState;

include_once __DIR__ . '/init.php';
$footballState = new FootballState();
$loop = Factory::create();

$client = new Slack\RealTimeClient($loop);
$client->setToken(getenv('BOT_TOKEN', 'ADMIN'));
$client->connect()->then(function () use ($client) {
    $client->getDMByUserId(getenv('ADMIN'))->then(function ($channel) use ($client) {
        $client->postMessage(
            $client->getMessageBuilder()
                ->setText('Bot started! ' . date('Y-m-d H:i:s'))
                ->setChannel($channel)
                ->create()
        );
    });
});
$messageManager = new \w\Bot\MessageManager($footballState, $client);
$privateMessageManager = new \w\Bot\PrivateMessageManager($footballState, $client);

$client->on('message', function (\Slack\Payload $data) use ($client, $messageManager, $privateMessageManager) {
    var_dump($data);
    $message = new \Slack\Message\Message($client, $data->getData());
    if (isset($data['subtype']) && $data['subtype'] == 'message_changed') {
        // Skip change events;
        return;
    }

    $client->getChannelGroupOrDMByID($message->data['channel'])->then(function (ChannelInterface $channel)  use (
        $client,
        $messageManager,
        $privateMessageManager,
        $message
    ) {
        if ($channel instanceof \Slack\Channel) {
            $responseBuilder = $messageManager->parseMessage($message);
        } else if ($channel instanceof DirectMessageChannel) {
            $responseBuilder = $privateMessageManager->parseMessage($message);
        } else {
            echo "Exiting... Incorrect channel given!";
            return;
        }

        if (is_null($responseBuilder)) {
            return;
        }

        $response = $responseBuilder->setChannel($channel)->create();
        $client->postMessage($response);
    });
});

$client->on('game_started', function (\Slack\Message\Message $message) use ($client, $privateMessageManager) {
    $client->getDMByUserId($message->data['user'])->then(function (DirectMessageChannel $channel) use ($client, $privateMessageManager, $message) {
        $message = clone $message;
        $message->data['text'] = ',list';

        $responseBuilder = $privateMessageManager->parseMessage($message);

        if (is_null($responseBuilder)) {
            return;
        }

        $response = $responseBuilder->setChannel($channel)->create();
        $client->postMessage($response);
    });
});
echo "Started and running...\n";
$loop->run();