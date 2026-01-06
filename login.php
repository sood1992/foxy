<?php
// login.php - Neofox Yellow Theme Login
// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username, password, role FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":username", $username);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Check for redirect parameter
        $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
        header("Location: " . $redirect);
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Neofox Gear Control</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --neofox-yellow: #FFD700;
            --bright-yellow: #FFEB3B;
            --yellow-light: #FFF9C4;
            --yellow-dark: #F57F17;
            --black-primary: #000000;
            --black-secondary: #1A1A1A;
            --white-primary: #FFFFFF;
            --gray-light: #F5F5F5;
            --red-accent: #FF5722;
            --green-accent: #4CAF50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--neofox-yellow);
            color: var(--black-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Fun floating elements */
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .floating-icon {
            position: absolute;
            font-size: 3rem;
            opacity: 0.1;
            animation: float 8s ease-in-out infinite;
            color: var(--black-primary);
        }

        .floating-icon:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 15%; right: 15%; animation-delay: 1s; }
        .floating-icon:nth-child(3) { top: 70%; left: 8%; animation-delay: 2s; }
        .floating-icon:nth-child(4) { bottom: 15%; right: 12%; animation-delay: 3s; }
        .floating-icon:nth-child(5) { bottom: 60%; left: 15%; animation-delay: 4s; }
        .floating-icon:nth-child(6) { top: 50%; right: 5%; animation-delay: 5s; }
        .floating-icon:nth-child(7) { bottom: 40%; right: 40%; animation-delay: 6s; }
        .floating-icon:nth-child(8) { top: 30%; left: 30%; animation-delay: 7s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-15px) rotate(2deg); }
            50% { transform: translateY(-30px) rotate(-2deg); }
            75% { transform: translateY(-10px) rotate(1deg); }
        }

        /* Main login container */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            padding: 2rem;
        }

        /* Logo/Brand Section */
        .brand-section {
            text-align: center;
            margin-bottom: 3rem;
        }

        .brand-logo {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--black-primary);
            margin-bottom: 1rem;
            letter-spacing: -2px;
        }

        .brand-tagline {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--black-primary);
            line-height: 1.2;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .brand-subtitle {
            font-size: 1.1rem;
            color: var(--black-secondary);
            font-weight: 600;
        }

        /* Login Card */
        .login-card {
            background: var(--white-primary);
            border-radius: 24px;
            padding: 3rem;
            border: 4px solid var(--black-primary);
            box-shadow: 12px 12px 0px var(--black-primary);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, var(--neofox-yellow) 0%, var(--yellow-dark) 100%);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .login-title {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--black-primary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: -1px;
        }

        .login-subtitle {
            color: var(--black-secondary);
            font-weight: 600;
            font-size: 1rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 700;
            color: var(--black-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 1.2rem 1.5rem;
            border: 3px solid var(--black-primary);
            border-radius: 16px;
            font-size: 1.1rem;
            font-weight: 600;
            background: var(--white-primary);
            color: var(--black-primary);
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 4px rgba(255, 215, 0, 0.2);
            transform: translateY(-2px);
        }

        .form-input::placeholder {
            color: var(--black-secondary);
            opacity: 0.7;
        }

        /* Input Icons */
        .input-icon {
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--black-secondary);
            font-size: 1.2rem;
            pointer-events: none;
        }

        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 1.5rem;
            background: var(--black-primary);
            color: var(--neofox-yellow);
            border: none;
            border-radius: 16px;
            font-size: 1.2rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 6px 6px 0px var(--neofox-yellow);
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-3px) translateX(-3px);
            box-shadow: 9px 9px 0px var(--neofox-yellow);
        }

        .btn-login:active {
            transform: translateY(-1px) translateX(-1px);
            box-shadow: 3px 3px 0px var(--neofox-yellow);
        }

        /* Error Alert */
        .error-alert {
            background: var(--white-primary);
            border: 3px solid var(--red-accent);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 6px 6px 0px var(--red-accent);
        }

        .error-icon {
            width: 50px;
            height: 50px;
            background: var(--red-accent);
            color: var(--white-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .error-text {
            font-weight: 700;
            color: var(--black-primary);
            font-size: 1rem;
        }

        /* Request Gear Link Section */
        .request-gear-section {
            text-align: center;
            margin-top: 2rem;
            padding: 1.5rem;
            background: var(--yellow-light);
            border-radius: 16px;
            border: 2px solid var(--yellow-dark);
        }

        .request-gear-title {
            font-weight: 800;
            color: var(--black-primary);
            margin-bottom: 1rem;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .btn-request-gear {
            display: inline-block;
            padding: 1.5rem 3rem;
            background: var(--neofox-yellow);
            color: var(--black-primary);
            text-decoration: none;
            border-radius: 16px;
            border: 3px solid var(--black-primary);
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            box-shadow: 6px 6px 0px var(--black-primary);
            width: 100%;
        }

        .btn-request-gear:hover {
            transform: translateY(-3px) translateX(-3px);
            box-shadow: 9px 9px 0px var(--black-primary);
            text-decoration: none;
            color: var(--black-primary);
        }

        .btn-request-gear:active {
            transform: translateY(-1px) translateX(-1px);
            box-shadow: 3px 3px 0px var(--black-primary);
        }

        .btn-request-gear i {
            margin-right: 0.75rem;
            font-size: 1.3rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                padding: 1rem;
                max-width: 400px;
            }

            .brand-logo {
                font-size: 2.5rem;
            }

            .brand-tagline {
                font-size: 1.4rem;
            }

            .login-card {
                padding: 2rem;
            }

            .login-title {
                font-size: 1.8rem;
            }

            .floating-icon {
                font-size: 2rem;
            }
        }

        /* Animation for page load */
        .login-card {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Elements -->
    <div class="floating-elements">
        <div class="floating-icon"><i class="fas fa-camera"></i></div>
        <div class="floating-icon"><i class="fas fa-video"></i></div>
        <div class="floating-icon"><i class="fas fa-microphone"></i></div>
        <div class="floating-icon"><i class="fas fa-lightbulb"></i></div>
        <div class="floating-icon"><i class="fas fa-headphones"></i></div>
        <div class="floating-icon"><i class="fas fa-film"></i></div>
        <div class="floating-icon"><i class="fas fa-speaker"></i></div>
        <div class="floating-icon"><i class="fas fa-camera-retro"></i></div>
    </div>

    <div class="login-container">
        <!-- Brand Section -->
        <div class="brand-section">
            <div class="brand-logo">
                <i class="fas fa-cube"></i> NEOFOX
            </div>
            <div class="brand-tagline">
                GEAR CONTROL<br>SYSTEM
            </div>
           
        </div>

        <!-- Login Card -->
        <div class="login-card">
            <div class="login-header">
                <h2 class="login-title">ACCESS</h2>
                <p class="login-subtitle">Enter your credentials to continue</p>
            </div>

            <?php if (isset($error)): ?>
            <div class="error-alert">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="error-text"><?php echo htmlspecialchars($error); ?></div>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div style="position: relative;">
                        <input type="text" 
                               class="form-input" 
                               id="username" 
                               name="username" 
                               placeholder="Enter your username"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               required>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div style="position: relative;">
                        <input type="password" 
                               class="form-input" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password"
                               required>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> LOGIN
                </button>
            </form>

            <div class="request-gear-section">
                <div class="request-gear-title">Need Gear Access?</div>
                <a href="https://neofoxmedia.com/foxy/gear_request.php" class="btn-request-gear">
                    <i class="fas fa-plus-circle"></i> REQUEST GEAR
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Add some interaction feedback
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Login button interaction
        const loginBtn = document.querySelector('.btn-login');
        loginBtn.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ACCESSING...';
        });
    </script>
</body>
</html>