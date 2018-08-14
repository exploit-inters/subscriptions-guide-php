<?php

require_once "vendor/autoload.php";

// Create app
$app = new Slim\App;

// Load configuration with dotenv
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

// Get container
$container = $app->getContainer();

// Register Twig component on container to use view templates
$container['view'] = function() {
    return new Slim\Views\Twig('views');
};

// Initialize database
$container['db'] = function() {
    return new PDO('sqlite:subscribers.sqlite');
};

// Load and initialize MesageBird SDK
$container['messagebird'] = function() {
    return new MessageBird\Client(getenv('MESSAGEBIRD_API_KEY'));
};

// Handle incoming webhooks
$app->post('/webhook', function($request, $response) {
    // Read input sent from MessageBird
    $number = $request->getParsedBodyParam('originator');
    $text = strtolower(trim($request->getParsedBodyParam('payload')));

    // Find subscriber in our database
    $stmt = $this->db->prepare('SELECT * FROM subscribers WHERE number = :number');
    $stmt->execute([ 'number' => $number ]);
    $subscriber = $stmt->fetch();

    // Prepare a message object, which will be
    // updated depending on the subscriber status
    // and only sent if necessary
    $message = new MessageBird\Objects\Message;
    $message->originator = getenv('MESSAGEBIRD_ORIGINATOR');
    $message->recipients = [ $number ];
    $message->body = "";

    if ($subscriber === false && $text == "subscribe") {
        // The user has sent the "subscribe" keyword
        // and is not stored in the database yet, so
        // we add them to the database.
        $stmt = $this->db->prepare('INSERT INTO subscribers (number, subscribed) VALUES (:number, :subscribed)');
        $stmt->execute([
            'number' => $number,
            'subscribed' => (int)true
        ]);

        // Set notification text
        $message->body = "Thanks for subscribing to our list! Send STOP anytime if you no longer want to receive messages from us.";

    } else
    if ($subscriber !== false && (bool)$subscriber['subscribed'] == false && $text == "subscribe") {
        // The user has sent the "subscribe" keyword
        // and was already found in the database in an
        // unsubscribed state. We resubscribe them by
        // updating their database entry.
        $stmt = $this->db->prepare('UPDATE subscribers SET subscribed = :subscribed WHERE number = :number');
        $stmt->execute([ 'number' => $number, 'subscribed' => (int)true ]);

        // Set notification text
        $message->body = "Thanks for re-subscribing to our list! Send STOP anytime if you no longer want to receive messages from us.";
    } else
    if ($subscriber !== false && (bool)$subscriber['subscribed'] == true && $text == "stop") {
        // The user has sent the "stop" keyword, indicating
        // that they want to unsubscribe from messages.
        // They were found in the database, so we mark
        // them as unsubscribed and update the entry.
        $stmt = $this->db->prepare('UPDATE subscribers SET subscribed = :subscribed WHERE number = :number');
        $stmt->execute([ 'number' => $number, 'subscribed' => (int)false ]);

        // Set notification text
        $message->body = "Sorry to see you go! You will not receive further marketing messages from us.";
    }

    if ($message->body != "") {
        // A message body was defined, so we send the message through the API
        error_log($number." <-- ".$message->body);
        try {
            $this->messagebird->messages->create($message);
        } catch (Exception $e) {
            error_log(get_class($e).": ".$e->getMessage());
        }
    } else {
        error_log("Nothing to do for incoming message.");
    }

    // Return any response, MessageBird won't parse this
    return "OK";
});

$app->get('/', function($request, $response) {
    // Get number of subscribers to show on the form
    $stmt = $this->db->prepare('SELECT COUNT(*) FROM subscribers WHERE subscribed = :subscribed');
    $stmt->execute([ 'subscribed' => (int)true ]);
    $count = $stmt->fetchColumn();

    $this->view->render($response, 'home.html.twig',
        [ 'count' => $count ]);
});

$app->post('/send', function($request, $response) {
    // Read input from user
    $messageBody = $request->getParsedBodyParam('message');

    // Get all subscribers
    $stmt = $this->db->prepare('SELECT * FROM subscribers WHERE subscribed = :subscribed');
    $stmt->execute([ 'subscribed' => (int)true ]);
    $subscribers = $stmt->fetchAll();

    // Collect all numbers
    $recipients = [];
    $lastIndex = count($subscribers) - 1;
    for ($i = 0; $i <= $lastIndex; $i++) {
        $recipients[] = $subscribers[$i]['number'];
        if ($i == $lastIndex || ($i+1) % 50 == 0) {
            // We have reached either the end of our list or 50 numbers,
            // which is the maximum that MessageBird accepts in a single
            // API call, so we send the message and then, if any numbers
            // are remaining, start a new list
            $message = new MessageBird\Objects\Message;
            $message->originator = getenv('MESSAGEBIRD_ORIGINATOR');
            $message->recipients = $recipients;
            $message->body = $messageBody;
            try {
                $this->messagebird->messages->create($message);
            } catch (Exception $e) {
                error_log(get_class($e).": ".$e->getMessage());
            }

            $recipients = [];
        }
    }
    
    $this->view->render($response, 'sent.html.twig',
        [ 'count' => count($subscribers) ]);
});

// Start the application
$app->run();