<?php 
require_once 'includes/config.php';
session_start();

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $fullName = trim($_POST['full_name']);
    $confirmPassword = $_POST['confirm_password'];
    
    // Server-side validation
    $errors = [];
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Password validation
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    // Confirm password validation
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    // Full name validation
    if (empty($fullName) || strlen($fullName) < 2) {
        $errors[] = 'Full name must be at least 2 characters long';
    }
    
    if (!empty($errors)) {
        $_SESSION['register_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header('Location: register.php');
        exit();
    }
    
    try {
        // First check if user already exists in Firestore (could be Google user)
        $existingUserQuery = $firestore->collection('users')->where('email', '=', $email)->limit(1);
        $existingUsers = $existingUserQuery->documents();
        
        if (!$existingUsers->isEmpty()) {
            $existingUser = $existingUsers->rows()[0];
            $existingUserData = $existingUser->data();
            
            if ($existingUserData['auth_provider'] === 'google') {
                throw new Exception('An account with this email already exists. Please sign in with Google or use a different email address.');
            } else {
                throw new Exception('An account with this email already exists. Please sign in instead.');
            }
        }
        
        // Create user with Firebase Authentication
        $userRecord = $auth->createUserWithEmailAndPassword($email, $password);
        $uid = $userRecord->uid;
        
        // Update user profile with display name
        $auth->updateUser($uid, [
            'displayName' => $fullName
        ]);
        
        // Store additional user data in Firestore
        $userData = [
            'email' => $email,
            'full_name' => $fullName,
            'profile_picture' => '',
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
            'auth_provider' => 'email'
        ];
        
        $firestore->collection('users')->document($uid)->set($userData);
        
        // Store success message and redirect to login
        $_SESSION['register_success'] = 'Account created successfully! Please sign in to continue.';
        header('Location: login.php');
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        
        // Handle specific Firebase errors
        if (strpos($error, 'email-already-in-use') !== false) {
            $error = 'An account with this email already exists';
        } elseif (strpos($error, 'weak-password') !== false) {
            $error = 'Password is too weak. Please choose a stronger password';
        } elseif (strpos($error, 'invalid-email') !== false) {
            $error = 'Please enter a valid email address';
        }
        
        $_SESSION['register_error'] = $error;
        $_SESSION['form_data'] = $_POST;
        header('Location: register.php');
        exit();
    }
}

// Check for errors/success from redirect
$showErrorAlert = isset($_SESSION['register_error']);
$errorMessage = $showErrorAlert ? $_SESSION['register_error'] : '';
unset($_SESSION['register_error']);

$showErrorsAlert = isset($_SESSION['register_errors']);
$errorsList = $showErrorsAlert ? $_SESSION['register_errors'] : [];
unset($_SESSION['register_errors']);

$formData = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
unset($_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <style>
        .password-strength {
            margin-top: 8px;
            display: none;
        }
        
        .strength-bar {
            width: 100%;
            height: 4px;
            background-color: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 4px;
        }
        
        .strength-fill {
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
            width: 0%;
            background-color: #ff4757;
        }
        
        .strength-text {
            font-size: 12px;
            color: #666;
        }
        
        .strength-weak .strength-fill { background-color: #ff4757; width: 25%; }
        .strength-fair .strength-fill { background-color: #ffa726; width: 50%; }
        .strength-good .strength-fill { background-color: #42a5f5; width: 75%; }
        .strength-strong .strength-fill { background-color: #66bb6a; width: 100%; }
        
        .password-requirements {
            margin-top: 8px;
            font-size: 12px;
            display: none;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
            color: #666;
        }
        
        .requirement i {
            width: 16px;
            margin-right: 8px;
            font-size: 10px;
        }
        
        .requirement.met {
            color: #66bb6a;
        }
        
        .requirement.met i {
            color: #66bb6a;
        }
    </style>
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
            <h1 class="login-title">Create your account</h1>
            <p class="login-subtitle">Sign up to get started with your new account</p>
        </div>

        <form class="login-form" id="registerForm" method="post">
            <button type="button" class="btn btn-google" id="google-register-btn" onclick="signUpWithGoogle()">
                <div class="google-icon"></div>
                Sign up with Google
            </button>

            <div class="divider"><span>or</span></div>

            <div class="form-group">
                <label for="email" class="form-label">Email address</label>
                <input type="email" id="email" name="email" class="form-input" 
                       placeholder="Enter your email" 
                       value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder="Create a password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('password', 'password-icon')" 
                            aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="password-icon"></i>
                    </button>
                </div>
                <div class="password-strength" id="password-strength">
                    <div class="strength-bar">
                        <div class="strength-fill"></div>
                    </div>
                    <div class="strength-text">Password strength: <span id="strength-level">Weak</span></div>
                </div>
                <div class="password-requirements" id="password-requirements">
                    <div class="requirement" id="req-length">
                        <i class="fas fa-times"></i>
                        At least 8 characters
                    </div>
                    <div class="requirement" id="req-uppercase">
                        <i class="fas fa-times"></i>
                        One uppercase letter
                    </div>
                    <div class="requirement" id="req-lowercase">
                        <i class="fas fa-times"></i>
                        One lowercase letter
                    </div>
                    <div class="requirement" id="req-number">
                        <i class="fas fa-times"></i>
                        One number
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                           placeholder="Confirm your password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'confirm-password-icon')" 
                            aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="confirm-password-icon"></i>
                    </button>
                </div>
                <div id="password-match-message" style="font-size: 12px; margin-top: 4px; display: none;"></div>
            </div>

            <button type="submit" class="btn btn-primary" id="register-btn">
                <i class="fas fa-user-plus"></i>
                Create Account
            </button>

            <div class="register-link">
                <span class="texts">Already have an account?</span> <a href="login.php">Sign in</a>
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
                title: 'Registration Failed',
                text: '<?php echo addslashes($errorMessage); ?>',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK'
            });
        });
        <?php endif; ?>

        // Show validation errors if any
        <?php if ($showErrorsAlert && !empty($errorsList)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const errorList = <?php echo json_encode($errorsList); ?>;
            const errorText = errorList.join('\n• ');
            
            Swal.fire({
                icon: 'error',
                title: 'Please fix the following errors:',
                text: '• ' + errorText,
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK'
            });
        });
        <?php endif; ?>

        // Toggle password visibility
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const passwordIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            let score = 0;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };
            
            // Calculate score
            Object.values(requirements).forEach(met => {
                if (met) score++;
            });
            
            // Update UI
            updatePasswordRequirements(requirements);
            
            let strength = 'weak';
            if (score >= 4) strength = 'strong';
            else if (score >= 3) strength = 'good';
            else if (score >= 2) strength = 'fair';
            
            return { strength, score, requirements };
        }

        function updatePasswordRequirements(requirements) {
            const reqElements = {
                length: document.getElementById('req-length'),
                uppercase: document.getElementById('req-uppercase'),
                lowercase: document.getElementById('req-lowercase'),
                number: document.getElementById('req-number')
            };
            
            Object.keys(reqElements).forEach(key => {
                const element = reqElements[key];
                const icon = element.querySelector('i');
                
                if (requirements[key]) {
                    element.classList.add('met');
                    icon.classList.replace('fa-times', 'fa-check');
                } else {
                    element.classList.remove('met');
                    icon.classList.replace('fa-check', 'fa-times');
                }
            });
        }

        // Password input event listener
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthContainer = document.getElementById('password-strength');
            const requirementsContainer = document.getElementById('password-requirements');
            
            if (password.length > 0) {
                strengthContainer.style.display = 'block';
                requirementsContainer.style.display = 'block';
                
                const { strength } = checkPasswordStrength(password);
                
                // Update strength indicator
                strengthContainer.className = 'password-strength strength-' + strength;
                document.getElementById('strength-level').textContent = 
                    strength.charAt(0).toUpperCase() + strength.slice(1);
            } else {
                strengthContainer.style.display = 'none';
                requirementsContainer.style.display = 'none';
            }
        });

        // Confirm password validation
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const messageElement = document.getElementById('password-match-message');
            
            if (confirmPassword.length > 0) {
                messageElement.style.display = 'block';
                
                if (password === confirmPassword) {
                    messageElement.textContent = '✓ Passwords match';
                    messageElement.style.color = '#66bb6a';
                } else {
                    messageElement.textContent = '✗ Passwords do not match';
                    messageElement.style.color = '#ff4757';
                }
            } else {
                messageElement.style.display = 'none';
            }
        }

        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        document.getElementById('password').addEventListener('input', checkPasswordMatch);

        // Form validation before submit
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const email = document.getElementById('email').value;
            const fullName = document.getElementById('full_name').value;
            const terms = document.getElementById('terms').checked;
            
            let errors = [];
            
            // Client-side validation
            if (!email || !email.includes('@')) {
                errors.push('Please enter a valid email address');
            }
            
            if (fullName.length < 2) {
                errors.push('Full name must be at least 2 characters long');
            }
            
            if (password.length < 8) {
                errors.push('Password must be at least 8 characters long');
            }
            
            if (password !== confirmPassword) {
                errors.push('Passwords do not match');
            }
            
            if (!terms) {
                errors.push('You must agree to the Terms of Service and Privacy Policy');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Please fix the following errors:',
                    text: '• ' + errors.join('\n• '),
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK'
                });
            }
        });

        // Google Sign-Up
        function signUpWithGoogle() {
            const provider = new firebase.auth.GoogleAuthProvider();
            
            firebase.auth().signInWithPopup(provider)
                .then((result) => {
                    const user = result.user;
                    
                    // Check if user already exists
                    return fetch('includes/google-register.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ 
                            idToken: user.getIdToken(),
                            isNewUser: result.additionalUserInfo.isNewUser
                        })
                    });
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.isNewUser) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Account Created!',
                                text: 'Your account has been created successfully.',
                                confirmButtonColor: '#3085d6'
                            }).then(() => {
                                window.location.href = 'home.php';
                            });
                        } else {
                            window.location.href = 'home.php';
                        }
                    } else {
                        Swal.fire('Error', data.message || 'Registration failed', 'error');
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    if (error.code === 'auth/account-exists-with-different-credential') {
                        Swal.fire('Error', 'An account already exists with this email using a different sign-in method.', 'error');
                    } else {
                        Swal.fire('Error', error.message || 'Registration failed', 'error');
                    }
                });
        }

        // Real-time email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            if (email && !email.includes('@')) {
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>