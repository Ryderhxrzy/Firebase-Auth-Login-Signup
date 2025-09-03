<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['idToken'])) {
    echo json_encode(['success' => false, 'message' => 'ID token is required']);
    exit();
}

try {
    // Verify the ID token
    $verifiedIdToken = $auth->verifyIdToken($input['idToken']);
    $uid = $verifiedIdToken->claims()->get('sub');
    
    // Get user data from Firebase Auth
    $user = $auth->getUser($uid);
    
    $isNewUser = isset($input['isNewUser']) ? $input['isNewUser'] : false;
    
    // Check if user already exists in Firestore with different auth provider
    $existingUserQuery = $firestore->collection('users')->where('email', '=', $user->email)->limit(1);
    $existingUsers = $existingUserQuery->documents();
    
    if (!$existingUsers->isEmpty()) {
        $existingUser = $existingUsers->rows()[0];
        $existingUserData = $existingUser->data();
        
        // If user exists with email provider, don't allow Google signup
        if ($existingUserData['auth_provider'] === 'email' && $isNewUser) {
            throw new Exception('An account with this email already exists. Please sign in with your email and password instead.');
        }
        
        // If user exists with Google provider, just sign them in
        if ($existingUserData['auth_provider'] === 'google') {
            $isNewUser = false; // Override to prevent duplicate creation
        }
    }
    
    // If this is a new user, create their document in Firestore
    if ($isNewUser) {
        $userData = [
            'auth_provider' => 'google',
            'email' => $user->email,
            'full_name' => $user->displayName ?? '',
            'profile_picture' => $user->photoUrl ?? ''
        ];
        
        // Store user data in Firestore
        $firestore->collection('users')->document($uid)->set($userData);
    } else {
        // For existing Google users, just proceed with login
        // No database update needed
    }
    
    // Store user in session
    $_SESSION['user'] = [
        'uid' => $uid,
        'email' => $user->email,
        'displayName' => $user->displayName ?? '',
        'photoUrl' => $user->photoUrl ?? ''
    ];
    
    echo json_encode([
        'success' => true, 
        'isNewUser' => $isNewUser,
        'message' => $isNewUser ? 'Account created successfully' : 'Signed in successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Google registration error: ' . $e->getMessage());
    
    $errorMessage = $e->getMessage();
    
    // Handle specific Firebase errors
    if (strpos($errorMessage, 'email-already-in-use') !== false) {
        $errorMessage = 'An account with this email already exists';
    } elseif (strpos($errorMessage, 'invalid-credential') !== false) {
        $errorMessage = 'Invalid authentication credentials';
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $errorMessage
    ]);
}
?>