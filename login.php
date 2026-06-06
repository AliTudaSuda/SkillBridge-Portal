<?php
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['user_id'])) {
    $dash_map = [
        'admin'     => 'admin_dashboard.php',
        'talent'    => 'talent_dashboard.php',
        'recruiter' => 'recruiter_dashboard.php',
    ];
    header("Location: " . ($dash_map[$_SESSION['role']] ?? 'login.php'));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security error: Invalid CSRF token.");
    }

    $email    = htmlspecialchars(stripslashes(trim($_POST['email']    ?? '')));
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Both email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, name, email, password, role, trust_points FROM users WHERE email = ?"
        );
        mysqli_stmt_bind_param($stmt, "s", $email); 
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id']      = $user['id'];
            $_SESSION['user_name']    = $user['name'];
            $_SESSION['user_email']   = $user['email'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['trust_points'] = $user['trust_points'];
            $_SESSION['last_action']  = time();

            mysqli_stmt_close($stmt);

            switch ($_SESSION['role']) {
                case 'admin':
                    header("Location: admin_dashboard.php");
                    exit();
                case 'talent':
                    header("Location: talent_dashboard.php");
                    exit();
                case 'recruiter':
                    header("Location: recruiter_dashboard.php");
                    exit();
                default:
                    $error = "Unknown role. Please contact support.";
                    break;
            }
        } else {
            $error = "Invalid email or password. Please try again.";
            if (isset($stmt)) mysqli_stmt_close($stmt);
        }
    }
}

$registered = isset($_GET['registered']) ? true : false;
$timeout    = isset($_GET['timeout'])    ? true : false;
$logged_out = isset($_GET['logout'])     ? true : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — SkillBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1d3557; --accent: #2ec4b6; --highlight: #f4a261; --light-bg: #f0f4f8; --text-dark: #1a202c; --text-muted: #718096; }
        body { font-family: 'DM Sans', sans-serif; background: var(--light-bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        h1,h2,h3,h4,h5 { font-family: 'Sora', sans-serif; }
        .auth-wrapper { display: flex; gap: 0; width: 100%; max-width: 860px; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(29,53,87,0.15); }
        .auth-panel-left { background: var(--primary); padding: 3rem 2rem; width: 42%; display: flex; flex-direction: column; justify-content: center; color: white; }
        .auth-panel-left h3 { font-size: 1.8rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.2; }
        .auth-panel-left h3 span { color: var(--accent); }
        .auth-panel-left p { color: rgba(255,255,255,0.7); font-size: 0.9rem; line-height: 1.6; }
        .feature-item { display: flex; align-items: center; gap: 0.75rem; margin-top: 1.25rem; font-size: 0.88rem; color: rgba(255,255,255,0.8); }
        .feature-icon { width: 32px; height: 32px; background: rgba(46,196,182,0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 1rem; flex-shrink: 0; }
        .auth-panel-right { background: white; padding: 2.5rem; flex: 1; }
        .brand-logo { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2rem; text-decoration: none; }
        .logo-icon { width: 36px; height: 36px; background: var(--primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--accent); font-weight: 800; font-family: 'Sora', sans-serif; font-size: 0.9rem; }
        .logo-text { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.2rem; color: var(--primary); }
        .logo-text span { color: var(--accent); }
        .form-control { border: 2px solid #e2e8f0; border-radius: 10px; padding: 0.65rem 1rem; font-family: 'DM Sans', sans-serif; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(46,196,182,0.15); outline: none; }
        .btn-login { background: var(--primary); color: white; border: none; border-radius: 10px; padding: 0.75rem; font-family: 'Sora', sans-serif; font-weight: 600; font-size: 1rem; width: 100%; transition: background 0.2s, transform 0.1s; cursor: pointer; }
        .btn-login:hover { background: #2a4a7f; transform: translateY(-1px); }
        .form-label { font-weight: 500; color: var(--text-dark); font-size: 0.9rem; }
        .demo-box { background: #f8fafc; border: 1px dashed #cbd5e0; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1.25rem; font-size: 0.82rem; color: var(--text-muted); }
        .demo-box strong { color: var(--text-dark); }
        @media (max-width: 640px) { .auth-wrapper { flex-direction: column; } .auth-panel-left { width: 100%; padding: 2rem; } }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-panel-left">
        <h3>Welcome to <span>SkillBridge</span></h3>
        <p>Your micro-internship marketplace. Connect talent with opportunity — aligned with UN SDG 08.</p>
        <div class="feature-item"><div class="feature-icon"><i class="bi bi-briefcase-fill"></i></div>Real micro-internship postings</div>
        <div class="feature-item"><div class="feature-icon"><i class="bi bi-trophy-fill"></i></div>Gamified Trust Points system</div>
        <div class="feature-item"><div class="feature-icon"><i class="bi bi-bell-fill"></i></div>Real-time notifications</div>
        <div class="feature-item"><div class="feature-icon"><i class="bi bi-shield-lock-fill"></i></div>Secure bcrypt authentication</div>
    </div>
    
    <div class="auth-panel-right">
        <a href="index.php" class="brand-logo">
            <div class="logo-icon">SB</div>
            <div class="logo-text">Skill<span>Bridge</span></div>
        </a>

        <h4 style="font-weight: 700; margin-bottom: 0.25rem; color: var(--text-dark);">Sign In</h4>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Welcome back! Enter your credentials to continue.</p>

        <?php if ($registered): ?>
            <div class="alert alert-success d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill"></i> Registration successful! Please sign in.</div>
        <?php endif; ?>
        <?php if ($timeout): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2"><i class="bi bi-clock-fill"></i> Session expired. Please sign in again.</div>
        <?php endif; ?>
        <?php if ($logged_out): ?>
            <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-door-open-fill"></i> Successfully logged out.</div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="demo-box"><strong>Demo Admin:</strong> admin@skillbridge.edu / password</div>

        <form method="POST" action="login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="you@university.edu" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Your password" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <p class="text-center mt-4 mb-0" style="font-size: 0.9rem; color: var(--text-muted);">
            New to SkillBridge? <a href="register.php" style="color: var(--accent); font-weight: 600; text-decoration: none;">Create an Account</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>