<?php 
require_once 'includes/config.php';
session_start();

// Handle email/password login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    try {
        $signInResult = $auth->signInWithEmailAndPassword($email, $password);
        $idToken = $signInResult->idToken();
        
        // Verify the ID token
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
        
        // Redirect to dashboard or home page
        header('Location: dashboard.php');
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Store error in session to show via SweetAlert
        $_SESSION['login_error'] = $error;
        header('Location: login.php');
        exit();
    }
}

// Check for error from redirect
$showErrorAlert = isset($_SESSION['login_error']);
$errorMessage = $showErrorAlert ? $_SESSION['login_error'] : '';
unset($_SESSION['login_error']);
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

        <form class="login-form" id="loginForm" method="post">
            <button type="button" class="btn btn-google" id="google-login-btn" onclick="signInWithGoogle()">
                <div class="google-icon"></div>
                Continue with Google
            </button>

            <div class="divider"><span>or</span></div>

            <div class="form-group">
                <label for="email" class="form-label">Email address</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" required>
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
                    <input type="checkbox" name="remember" id="remember">
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
        // Show error alert if there's an error message
        <?php if ($showErrorAlert): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: '<?php echo addslashes($errorMessage); ?>',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK'
            });
        });
        <?php endif; ?>

        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Google Sign-In
        function signInWithGoogle() {
            const provider = new firebase.auth.GoogleAuthProvider();
            
            firebase.auth().signInWithPopup(provider)
                .then((result) => {
                    // This gives you a Google Access Token
                    const credential = firebase.auth.GoogleAuthProvider.credentialFromResult(result);
                    const token = credential.accessToken;
                    
                    // The signed-in user info
                    const user = result.user;
                    
                    // Send token to server for verification
                    return fetch('includes/google-auth.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ idToken: user.getIdToken() })
                    });
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'dashboard.php';
                    } else {
                        Swal.fire('Error', data.message || 'Authentication failed', 'error');
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    Swal.fire('Error', error.message || 'Authentication failed', 'error');
                });
        }
    </script>
</body>
</html>