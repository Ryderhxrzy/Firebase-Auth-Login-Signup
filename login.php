<?php 
require_once 'includes/config.php';
session_start();

// Helper: Delete Firebase Auth User
function deleteFirebaseUser($auth, $uid) {
    try {
        $auth->deleteUser($uid);
    } catch (Exception $e) {
        error_log("Failed to delete user: " . $e->getMessage());
    }
}

// Handle email/password login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['google_auth'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        try {
            // ✅ Step 1: Check muna sa Firestore
            $usersRef = $database->getReference('users')->getValue();
            $foundUser = null;

            if ($usersRef) {
                foreach ($usersRef as $uid => $userData) {
                    if (isset($userData['email']) && strtolower($userData['email']) === strtolower($email)) {
                        $foundUser = [
                            'uid' => $uid,
                            'data' => $userData
                        ];
                        break;
                    }
                }
            }

            if (!$foundUser) {
                $error = 'No account found with this email address.';
            } elseif ($foundUser['data']['auth_provider'] === 'google') {
                $error = 'This account was created with Google. Please use "Continue with Google" to sign in.';
            } else {
                // ✅ Step 2: Try Firebase Auth login
                try {
                    $signInResult = $auth->signInWithEmailAndPassword($email, $password);
                    $user = $signInResult->data();

                    $_SESSION['user_id'] = $user['localId'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['auth_provider'] = 'email';

                    if (isset($_POST['remember'])) {
                        setcookie('remember_token', $user['idToken'], time() + (86400 * 30), '/');
                    }

                    header('Location: dashboard.php');
                    exit();
                } catch (Exception $e) {
                    // If Firebase created a ghost account, delete it
                    if (isset($foundUser['uid'])) {
                        deleteFirebaseUser($auth, $foundUser['uid']);
                    }
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $errorCode = $e->getMessage();

            switch (true) {
                case str_contains($errorCode, 'INVALID_EMAIL'):
                    $error = 'Invalid email format.';
                    break;
                case str_contains($errorCode, 'EMAIL_NOT_FOUND'):
                    $error = 'No account found with this email address.';
                    break;
                case str_contains($errorCode, 'INVALID_PASSWORD'):
                    $error = 'Incorrect password.';
                    break;
                case str_contains($errorCode, 'USER_DISABLED'):
                    $error = 'This account has been disabled.';
                    break;
                default:
                    $error = 'Login failed. Please try again.';
            }
        }
    }
}

// Handle Google authentication
if (isset($_POST['google_auth']) && isset($_POST['id_token'])) {
    try {
        $idToken = $_POST['id_token'];
        $verifiedIdToken = $auth->verifyIdToken($idToken);
        $uid = $verifiedIdToken->claims()->get('sub');

        $userDoc = $database->getReference('users/' . $uid)->getValue();

        if (!$userDoc) {
            // ❌ Walang record sa Firestore → delete agad
            deleteFirebaseUser($auth, $uid);
            echo json_encode([
                'success' => false,
                'error' => 'Account not found in system. Please register first or contact administrator.',
                'signOut' => true
            ]);
            exit();
        }

        if ($userDoc['auth_provider'] === 'email') {
            // ❌ Mali ang provider → sign out + delete
            deleteFirebaseUser($auth, $uid);
            echo json_encode([
                'success' => false,
                'error' => 'This account was created with email/password. Please use the email login form instead.',
                'signOut' => true
            ]);
            exit();
        }

        // ✅ Valid Google login
        $_SESSION['user_id'] = $uid;
        $_SESSION['user_email'] = $verifiedIdToken->claims()->get('email');
        $_SESSION['auth_provider'] = 'google';

        echo json_encode([
            'success' => true,
            'redirect' => 'dashboard.php'
        ]);
        exit();
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Google authentication failed. Please try again.',
            'signOut' => true
        ]);
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Firebase SDK for client-side -->
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-auth.js"></script>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <div class="logo-icon">
                    <img src="assets/images/logo.png" class="logo-icon" alt="">
                </div>
                <span class="logo-text">Sample</span>
            </div>
            <h1 class="login-title">Welcome back</h1>
            <p class="login-subtitle">Sign in to your account to continue</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message" style="background: #fee; border: 1px solid #fcc; color: #c66; padding: 10px; margin-bottom: 20px; border-radius: 4px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form class="login-form" id="loginForm" method="post">
            <button type="button" class="btn btn-google" id="google-login-btn" onclick="signInWithGoogle()">
                <div class="google-icon"></div>
                Continue with Google
            </button>

            <div class="divider"><span>or</span></div>

            <div class="form-group">
                <label for="email" class="form-label">Email address</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="password-icon"></i>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" id="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                    Remember me
                </label>
                <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary" id="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                Sign in
            </button>

            <div class="register-link">
                <span class="texts">Don't have an account?</span> <a href="register.php">Create account</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Firebase configuration
        const firebaseConfig = <?php echo json_encode($firebaseConfig); ?>;
        
        // Initialize Firebase
        firebase.initializeApp(firebaseConfig);
        const auth = firebase.auth();

        // Google Auth Provider
        const provider = new firebase.auth.GoogleAuthProvider();
        provider.addScope('email');
        provider.addScope('profile');

        // Google Sign In function
        async function signInWithGoogle() {
            const googleBtn = document.getElementById('google-login-btn');
            const originalText = googleBtn.innerHTML;
            
            try {
                // Show loading state
                googleBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
                googleBtn.disabled = true;
                
                const result = await auth.signInWithPopup(provider);
                const user = result.user;
                const idToken = await user.getIdToken();
                
                // Send token to server for verification
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `google_auth=1&id_token=${encodeURIComponent(idToken)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show success message and redirect
                    await Swal.fire({
                        icon: 'success',
                        title: 'Login Successful!',
                        text: 'Welcome back!',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    window.location.href = data.redirect;
                } else {
                    // Sign out from Firebase Auth if signOut flag is set
                    if (data.signOut) {
                        await auth.signOut();
                    }
                    
                    // Show error message
                    await Swal.fire({
                        icon: 'error',
                        title: 'Login Failed',
                        text: data.error,
                        confirmButtonColor: '#d33'
                    });
                }
                
            } catch (error) {
                console.error('Google Sign-In Error:', error);
                
                // Force sign out in case of any error
                try {
                    await auth.signOut();
                } catch (signOutError) {
                    console.error('Error during cleanup sign out:', signOutError);
                }
                
                let errorMessage = 'An unexpected error occurred. Please try again.';
                
                switch (error.code) {
                    case 'auth/popup-closed-by-user':
                        errorMessage = 'Sign-in was cancelled. Please try again.';
                        break;
                    case 'auth/popup-blocked':
                        errorMessage = 'Pop-up was blocked by your browser. Please allow pop-ups and try again.';
                        break;
                    case 'auth/network-request-failed':
                        errorMessage = 'Network error. Please check your internet connection.';
                        break;
                    case 'auth/too-many-requests':
                        errorMessage = 'Too many requests. Please wait a moment and try again.';
                        break;
                    case 'auth/user-disabled':
                        errorMessage = 'This account has been disabled.';
                        break;
                    case 'auth/account-exists-with-different-credential':
                        errorMessage = 'An account already exists with this email using a different sign-in method.';
                        break;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Sign-in Error',
                    text: errorMessage,
                    confirmButtonColor: '#d33'
                });
            } finally {
                // Reset button state
                googleBtn.innerHTML = originalText;
                googleBtn.disabled = false;
            }
        }

        // Password visibility toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('login-btn');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
            submitBtn.disabled = true;
            
            // Allow form to submit normally, but if it fails, reset button
            setTimeout(() => {
                if (!window.location.href.includes('dashboard.php')) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }, 5000);
        });

        // Show success message if redirected from registration
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('registered') === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Registration Successful!',
                text: 'Please sign in with your new account.',
                confirmButtonColor: '#28a745'
            });
        }

        // Auto-focus on email field when page loads
        window.addEventListener('load', function() {
            document.getElementById('email').focus();
        });

        // Remember me functionality
        window.addEventListener('load', function() {
            const rememberToken = getCookie('remember_token');
            if (rememberToken) {
                // Auto-login if remember token exists (implement as needed)
                console.log('Remember token found');
            }
        });

        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
        }
    </script>
</body>
</html>