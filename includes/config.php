<?php

use Kreait\Firebase\Factory;
use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

// Load .env file from project root
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Verify required environment variables
$dotenv->required([
    'FIREBASE_API_KEY',
    'FIREBASE_AUTH_DOMAIN',
    'FIREBASE_PROJECT_ID',
    'FIREBASE_DATABASE_URI',
    'FIREBASE_TYPE',
    'FIREBASE_PRIVATE_KEY_ID',
    'FIREBASE_PRIVATE_KEY',
    'FIREBASE_CLIENT_EMAIL',
    'FIREBASE_CLIENT_ID'
]);

// Build credentials array from environment variables
$credentials = [
    'type' => $_ENV['FIREBASE_TYPE'],
    'project_id' => $_ENV['FIREBASE_PROJECT_ID'],
    'private_key_id' => $_ENV['FIREBASE_PRIVATE_KEY_ID'],
    'private_key' => str_replace('\\n', "\n", $_ENV['FIREBASE_PRIVATE_KEY']),
    'client_email' => $_ENV['FIREBASE_CLIENT_EMAIL'],
    'client_id' => $_ENV['FIREBASE_CLIENT_ID'],
    'auth_uri' => $_ENV['FIREBASE_AUTH_URI'] ?? 'https://accounts.google.com/o/oauth2/auth',
    'token_uri' => $_ENV['FIREBASE_TOKEN_URI'] ?? 'https://oauth2.googleapis.com/token',
    'auth_provider_x509_cert_url' => $_ENV['FIREBASE_AUTH_PROVIDER_CERT_URL'] ?? 'https://www.googleapis.com/oauth2/v1/certs',
    'client_x509_cert_url' => $_ENV['FIREBASE_CLIENT_CERT_URL'] ?? ''
];

// Initialize Firebase
$factory = (new Factory)
    ->withServiceAccount($credentials)
    ->withDatabaseUri($_ENV['FIREBASE_DATABASE_URI']);

// Create Firebase services
$auth = $factory->createAuth();
$database = $factory->createDatabase();

// Frontend Firebase config
$firebaseConfig = [
    'apiKey' => $_ENV['FIREBASE_API_KEY'],
    'authDomain' => $_ENV['FIREBASE_AUTH_DOMAIN'],
    'projectId' => $_ENV['FIREBASE_PROJECT_ID'],
    'databaseURL' => $_ENV['FIREBASE_DATABASE_URI'],
    'storageBucket' => $_ENV['FIREBASE_STORAGE_BUCKET'] ?? null,
    'messagingSenderId' => $_ENV['FIREBASE_MESSAGING_SENDER_ID'] ?? null,
    'appId' => $_ENV['FIREBASE_APP_ID'] ?? null
];
?>

<!-- Firebase SDK for client-side -->
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-auth.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>

<script>
    // Initialize Firebase
    const firebaseConfig = <?php echo json_encode($firebaseConfig, JSON_UNESCAPED_SLASHES); ?>;
    
    if (!firebase.apps.length) {
        firebase.initializeApp(firebaseConfig);
    }
</script>