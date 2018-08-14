<?php

// Create/open the database table
$db = new PDO('sqlite:subscribers.sqlite');

// Generate schema: 1 table
$db->exec('CREATE TABLE subscribers (id INTEGER PRIMARY KEY, number TEXT, subscribed INTEGER);');