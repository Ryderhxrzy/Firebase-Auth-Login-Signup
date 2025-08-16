<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $idToken = $data['idToken'] ?? null;

    if (!$idToken) {
        echo json_encode(['success' => false, 'message' => 'No ID token provided']);
        exit;
    }

    try {
        $verifiedIdToken = $auth->verifyIdToken($idToken);
        $uid = $verifiedIdToken->claims()->get('sub');
        
        // Get user data
        $user = $auth->getUser($uid);
        
        // Store user in session
        $_SESSION['user'] = [
            'uid' => $uid,
            'email' => $user->email,
            'displayName' => $user->displayName ?? '',
            'photoUrl' => $user->photoUrl ?? ''
        ];
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>