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
$apiKey = $_ENV['FIREBASE_API_KEY'];
$projectId = $_ENV['FIREBASE_PROJECT_ID'];
$authDomain = $_ENV['FIREBASE_AUTH_DOMAIN'];

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
$auth = $factory->createAuth();

// Make Firebase config available to frontend
$firebaseConfig = [
    'apiKey' => $apiKey,
    'authDomain' => $authDomain,
    'projectId' => $projectId,
    'databaseURL' => $databaseUri
];
?>

<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-auth.js"></script>
<script>
    // Initialize Firebase with your config
    const firebaseConfig = <?php echo json_encode($firebaseConfig); ?>;
    firebase.initializeApp(firebaseConfig);
</script>