<?php

use Kreait\Firebase\Factory;
use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get values
$credentialsJson = $_ENV['FIREBASE_CREDENTIALS_JSON'];
$databaseUri = $_ENV['FIREBASE_DATABASE_URI'];

// Convert JSON string to temporary file
$tempFile = tmpfile();
fwrite($tempFile, $credentialsJson);
$meta = stream_get_meta_data($tempFile);
$credentialsPath = $meta['uri'];

// Initialize Firebase
$factory = (new Factory)
    ->withServiceAccount($credentialsPath)
    ->withDatabaseUri($databaseUri);

$database = $factory->createDatabase();
