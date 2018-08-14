# SMS Marketing Subscriptions
### ⏱ 30 min build time 

## Why build SMS marketing subscriptions? 

SMS makes it incredibly easy for businesses to reach consumers everywhere at any time, directly on their mobile devices. For many people, these messages are a great way to discover things like discounts and special offers from a company, while others might find them annoying. For this reason, it is  important and also required by law in many countries, to provide clear opt-in and opt-out mechanisms for SMS broadcast lists. To make these work independently of a website it's useful to assign a programmable [virtual mobile number](https://www.messagebird.com/en/numbers) to your SMS campaign and handle incoming messages programmatically so users can control their subscription with basic command keywords.

In this MessageBird Developer Guide, we'll show you how to implement an SMS marketing campaign subscription tool built as a sample application in PHP.

This application implements the following:
* A person can send the keyword _SUBSCRIBE_ to a specific VMN, that the company includes in their advertising material, to opt in to messages, which is immediately confirmed.
* If the person no longer wants to receive messages they can send the keyword _STOP_ to the same number. Opt-out is also confirmed.
* An administrator can enter a message in a form on a website. Then they can send this message to all confirmed subscribers immediately.

## Getting Started

To get the sample application running, you need to have PHP installed on your machine. If you're using a Mac, PHP is already installed. For Windows, you can [get it from windows.php.net](https://windows.php.net/download/). Linux users, please check your system's default package manager. You also need Composer, which is available from [getcomposer.org](https://getcomposer.org/download/), to install application dependencies like the [MessageBird SDK for PHP](https://github.com/messagebird/php-rest-api).

We've provided the source code of the sample application in the [MessageBird Developer Guides GitHub repository](https://github.com/messagebirdguides/subscriptions-guide-php), which you can either clone with git or from where you can download a ZIP file with the source code to your computer.

Then, open a console pointed at the directory into which you've placed the sample application and run the following command:

````bash
composer install
````

Apart from the MessageBird SDK, Composer installs a few additional libraries: the [Slim framework](https://packagist.org/packages/slim/slim), the [Twig templating engine](https://packagist.org/packages/slim/twig-view), and the [Dotenv configuration library](https://packagist.org/packages/vlucas/phpdotenv). Using these libraries, we keep our controller, view, and configuration separated without having to set up a full-blown framework.

To store the subscriber list, our sample application uses a relational database. It is configured to use a single-file [SQLite](https://www.sqlite.org/) database, which is natively supported by PHP through PDO so that it works out of the box without the need to configure an external RDBMS like MySQL. Run the following helper command to initialize an empty SQLite database:

````bash
php init.php
````

## Prerequisites for Receiving Messages

### Overview

This guide describes receiving messages using MessageBird. From a high-level viewpoint, receiving is relatively simple: your application defines a _webhook URL_, which you assign to a number purchased on the MessageBird Dashboard using [Flow Builder](https://dashboard.messagebird.com/en/flow-builder). Whenever someone sends a message to that number, MessageBird collects it and forwards it via HTTP to the webhook URL, where you can process it.

### Exposing your Development Server with ngrok

One small roadblock when working with webhooks is the fact that MessageBird needs to access your application, so it needs to be available on a public URL. During development, you're typically working in a local development environment that is not publicly available. Thankfully this is not a big deal since various tools and services allow you to quickly expose your development environment to the Internet by providing a tunnel from a public URL to your local machine. One of the most popular tools is [ngrok](https://ngrok.com).

You can [download ngrok here for free](https://ngrok.com/download) as a single-file binary for almost every operating system, or optionally sign up for an account to access additional features.

You can start a tunnel by providing a local port number on which your application runs. We will run our PHP server on port 8080, so you can launch your tunnel with this command:

````bash
ngrok http 8080
````

After you've launched the tunnel, ngrok displays your temporary public URL along with some other information. We'll need that URL in a minute.

![ngrok](ngrok.png)

Another common tool for tunneling your local machine is [localtunnel.me](https://localtunnel.me), which you can have a look at if you're facing problems with ngrok. It works in virtually the same way but requires you to install [NPM](https://www.npmjs.com/) first.

### Get an Inbound Number

An obvious requirement for receiving messages is an inbound number. Virtual mobile numbers look and work similar to regular mobile numbers, however, instead of being attached to a mobile device via a SIM card, they live in the cloud, i.e., a data center, and can process incoming SMS and voice calls. Explore our low-cost programmable and configurable numbers [here](https://www.messagebird.com/en/numbers).

Here's how to purchase one:

1. Go to the [Numbers](https://dashboard.messagebird.com/en/numbers) section of your MessageBird account and click **Buy a number**.
2. Choose the country in which you and your customers are located and make sure the _SMS_ capability is selected.
3. Choose one number from the selection and the duration for which you want to prepay the amount. ![Buy a number screenshot](buy-a-number.png)
4. Confirm by clicking **Buy Number**.

Congratulations, you have set up your first virtual mobile number!

### Connect Number to the Webhook

So you have a number now, but MessageBird has no idea what to do with it. That's why you need to define a _Flow_ next that links your number to your webhook. Here is how you do it:

1. On the [Numbers](https://dashboard.messagebird.com/en/numbers) section of your MessageBird account, click the "add new flow" icon next to the number you purchased in the previous step. ![Create Flow, Step 1](create-flow-1.png)
2. Click **Templates** to build your flow from a template. ![Create Flow, Step 2](create-flow-2.png)
3. Select **SMS** and choose **Call HTTP endpoint with SMS** to use this template. Confirm with **Next** to create the flow. ![Create Flow, Step 3](create-flow-3.png)
4. Your new flow has two steps: a trigger and an action. The SMS trigger is already selected. Confirm that the right number is selected and click **Save**. ![Create Flow, Step 4](create-flow-4.png)
5. Now click on the **Forward to URL** action. Choose _POST_ as the method, copy the ngrok URL from the previous step and add `/webhook` to it - this is the name of the route we use to handle incoming messages in our sample application. Click **Save** for the step first and then **Publish** to finalize the entire flow. ![Create Flow, Step 5](create-flow-5.png)

## Configuring the MessageBird SDK

The SDK is listed as a dependency in `composer.json`:

````json
{
    "require" : {
        "messagebird/php-rest-api" : "^1.9.4"
        ...
    }
}
````

An application can access the SDK, which is made available through Composer autoloading, by creating an instance of the `MessageBird\Client` class. The constructor takes a single argument, your API key. For frameworks like Slim you can add the SDK to the dependency injection container:

````php
// Load and initialize MesageBird SDK
$container['messagebird'] = function() {
    return new MessageBird\Client(getenv('MESSAGEBIRD_API_KEY'));
};
````

As it's a bad practice to keep credentials in the source code, we load the API key from an environment variable using `getenv()`. To make the key available in the environment variable we need to initialize Dotenv and then add the key to a `.env` file.

Apart from `MESSAGEBIRD_API_KEY` we use another environment variable called `MESSAGEBIRD_ORIGINATOR` which contains the phone number used in our system, i.e., the VMN you just registered.

You can copy the `env.example` file provided in the repository to `.env` and then add your API key and phone number like this:

````env
MESSAGEBIRD_API_KEY=YOUR-API-KEY
MESSAGEBIRD_ORIGINATOR=+31970XXXXXXX
````

You can retrieve or create an API key from the [API access (REST) tab](https://dashboard.messagebird.com/en/developers/access) in the _Developers_ section of your MessageBird account.

## Receiving Messages

Now that we're fully prepared for receiving inbound messages, let's have a look at the actual implementation of our `/webhook` route:

````php
// Handle incoming webhooks
$app->post('/webhook', function($request, $response) {
    // Read input sent from MessageBird
    $number = $request->getParsedBodyParam('originator');
    $text = strtolower(trim($request->getParsedBodyParam('payload')));
````

The webhook receives multiple request parameters from MessageBird; however, we're only interested in two of them: the _originator_, i.e., the number of the user who sent the message, and the _payload_, i.e., the text of the message. The content is trimmed and converted into lower case so we can easily do case-insensitive command detection.

````php
    // Find subscriber in our database
    $stmt = $this->db->prepare('SELECT * FROM subscribers WHERE number = :number');
    $stmt->execute([ 'number' => $number ]);
    $subscriber = $stmt->fetch();
````

This SQL SELECT query searches for the originator number in a database table named _subscribers_.

We're looking at three potential cases:
* The user has sent _SUBSCRIBE_ and the number does not exist. The subscriber should be added and opted in.
* The user has submitted _SUBSCRIBE_ and the number exists but has opted out. In that case, it should be opted in (again).
* The user has sent _STOP_ and the number exists and has opted in. In that case, it should be opted out.

For each of those cases, a differently worded confirmation message should be sent. All incoming messages that don't fit any of these cases are ignored and don't get a reply. You can optimize this behavior, for example by sending a help message with all supported commands.

Sending a message through the MessageBird SDK for PHP is a two-step process. First, you create an instance of the `MessageBird\Objects\Message` class and set all message parameters on that object. Then, you call a method on the SDK object and pass the message as a parameter. As the parameters, except for the message body, are the same in each case, we can prepare the object before any conditional checks:

````php
    // Prepare a message object, which will be
    // updated depending on the subscriber status
    // and only sent if necessary
    $message = new MessageBird\Objects\Message;
    $message->originator = getenv('MESSAGEBIRD_ORIGINATOR');
    $message->recipients = [ $number ];
    $message->body = "";
````

As the implementation of each case is similar we'll only look at one here, but you can check the others in the source code:

````php
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

    }
````

If no `$subscriber` exists and the text matches "subscribe", the script executes an SQL INSERT query that stores a new row with the number and the field `subscribed` set to `true` (cast to an integer because SQLite doesn't support boolean fields). As confirmation, we assign a message to the message's body attribute.

After the code for all cases our incoming message endpoint should handle, we can call `messages->create()` to send the message. The call is only necessary if one of the cases applied, and the message body was assigned.

````php
    if ($message->body != "") {
        // A message body was defined, so we send the message through the API
        error_log($number." <-- ".$message->body);
        try {
            $this->messagebird->messages->create($message);
        } catch (Exception $e) {
            error_log(get_class($e).": ".$e->getMessage());
        }
    }
````

Sending the message is contained in a try-catch block which sends exceptions thrown by the MessageBird SDK to the server's default error log.

## Sending Messages

### Showing Form

We've defined a simple form with a single textarea and a submit button, and stored it as a Twig template in `views/home.html.twig`. It is rendered for a GET request on the root of the application. As a small hint for the admin, we're also showing the number of subscribers in the database.

### Processing Input

The form submits its content as a POST request to the `/send` route. The implementation of this route fetches all subscribers that have opted in from the database and then uses the MessageBird SDK to send a message to them. It is possible to send a message to up to 50 receivers in a single API call, so the script splits a list of subscribers that is longer than 50 numbers (highly unlikely during testing unless you have amassed an impressive collection of phones) into blocks of 50 numbers each. Sending encompasses creating a `MessageBird\Objects\Message` object and then calling the `messages->create()` SDK method which you've already seen in the previous section.

Here's the full code block:

````php
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
````

### Testing the Application

Double-check that you have set up your number correctly with a flow that forwards incoming messages to a ngrok URL and that the tunnel is still running. You can restart the tunnel with the `ngrok` command, but this will change your URL, so you have to update the flow as well.

To start the sample application you have to enter another command, but your existing console window is now busy running your tunnel. Therefore you need to open another one. On a Mac you can press _Command_ + _Tab_ to open a second tab that's already pointed to the correct directory. With other operating systems you may have to resort to open another console window manually. Once you've got a command prompt, type the following to start the application:

````bash
php -S 0.0.0.0:8080 index.php
````

While keeping the console open, take out your phone, launch the SMS app and send a message to your virtual mobile number with the keyword "subscribe". A few seconds later, you should see some output in the console from both the PHP runtime and the ngrok proxy. Also, the confirmation message should arrive shortly. Point your browser to http://localhost:8080/ (or your tunnel URL), and you should also see that there's one subscriber. Try sending yourself a message now. Voilá, your marketing system is ready!

## Nice work!

You can adapt the sample application for production by replying mongo-mock with a real MongoDB client, deploying the application to a server and providing that server's URL to your flow. Of course, you should add some authorization to the web form. Otherwise, anybody could send messages to your subscribers.

Don't forget to download the code from the [MessageBird Developer Guides GitHub repository](https://github.com/messagebirdguides/subscriptions-guide-php).

## Next steps

Want to build something similar but not quite sure how to get started? Please feel free to let us know at support@messagebird.com, we'd love to help!