<?php

// Listens on AMQP to queues defined in environment variables, and spits each message out in an email.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;

require 'vendor/autoload.php';

// Wait for the RabbitMQ server to boot up and be available
exec("/usr/bin/wait-for-it -h " . getenv('AMQP_SERVER') . " -p " . getenv('AMQP_PORT') . " -t 0");

$connection = new AMQPStreamConnection(
    getenv('AMQP_SERVER'),
    getenv('AMQP_PORT'),
    getenv('AMQP_USERNAME'),
    getenv('AMQP_PASSWORD'),
    $vhost = '/',
    $insist = false,
    $login_method = 'AMQPLAIN',
    $login_response = null,
    $locale = 'en_US',
    $connection_timeout = 60,
    $read_write_timeout = 60,
    $context = null,
    $keepalive = false,
    $heartbeat = 30);
$channel = $connection->channel();
$channel->exchange_declare(getenv('AMQP_EXCHANGE'), 'topic', false, true, false);

$callback = function ($msg) use ($connection) {
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_SERVER');
        $mail->Port = getenv('SMTP_SERVER_PORT');

        //Recipients
        $mail->setFrom('mqbridge' . '@' . getenv('SMTP_EMAIL_DOMAIN'));
        $mail->addAddress($msg->getRoutingKey() . '@' . getenv('SMTP_EMAIL_DOMAIN'), $msg->getRoutingKey());

        // Content
        $mail->isHTML(false);
        $mail->Subject = 'MQ message on : ' . $msg->getRoutingKey();
        $mail->Body = $msg->getBody();

        $mail->send();
        echo 'Message has been sent: ' . $mail->Subject;
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
};

foreach (getenv() as $key=>$value) {
    if (strpos($key, 'AMQP_CONSUME_QUEUES_') === 0) {
        echo("Consuming from queue: " . $value . "\n");
        $channel->queue_declare($value, false, true, false, false);
        $channel->basic_consume($value, '', false, true, false, false, $callback);
    }
}

while ($channel->is_consuming()) {
    try {
        $channel->wait();
        }
    catch(AMQPConnectionClosedException $e) {
            echo("ERROR: ". $e->getFile() . "\n");
            echo($e->getLine() . "\n");
            echo($e->getMessage() . "\n");
            echo($e->getTraceAsString() . "\n");

        $channel->reconnect();
    }
}