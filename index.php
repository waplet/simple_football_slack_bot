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

        $client->getChannelGroupOrDMByID($message->data['channel'])->then(function (ChannelInterface $channel)  use ($client, $responseBuilder) {
            $response = $responseBuilder->setChannel($channel)->create();

            $client->postMessage($response);
        });

        // $message->getChannel()->then(function (\Slack\Channel $channel) use ($client, $responseBuilder) {
        //
        // }, function (\Slack\ApiException $e) use ($client, $responseBuilder, $message) {
        //     echo $e->getMessage() . " - Rejection!\n";
        //
        //     $client->getDMById($message->data['channel'])->then(function (DirectMessageChannel $channel) use ($client, $responseBuilder) {
        //         $response = $responseBuilder->setChannel($channel)->create();
        //
        //         $client->postMessage($response);
        //     }, function (\Slack\ApiException $e) {
        //         echo $e->getMessage() . " - rejected1\n";
        //     });
        // });
    });
});

echo "Started and running...\n";
$loop->run();