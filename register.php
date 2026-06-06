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
    $dash = ['admin'=>'admin_dashboard.php','talent'=>'talent_dashboard.php','recruiter'=>'recruiter_dashboard.php'];
    header("Location: " . ($dash[$_SESSION['role']] ?? 'login.php'));
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security error: Invalid CSRF token.");
    }

    $name     = htmlspecialchars(stripslashes(trim($_POST['name']     ?? '')));
    $email    = htmlspecialchars(stripslashes(trim($_POST['email']    ?? '')));
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');
    $role     = htmlspecialchars(stripslashes(trim($_POST['role']     ?? '')));

    if (empty($name) || empty($email) || empty($password) || empty($confirm) || empty($role)) {
        $error = "All fields are required. Please fill in the complete form.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address (e.g., student@university.edu).";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match. Please try again.";
    } elseif (!in_array($role, ['talent', 'recruiter'])) {
        $error = "Invalid role selected. Please choose 'Talent' or 'Recruiter'.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email); 
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "This email address is already registered. Please log in.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($insert_stmt, "ssss", $name, $email, $hashed_password, $role);

            if (mysqli_stmt_execute($insert_stmt)) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $error = "Registration failed due to a server error. Please try again.";
            }
            mysqli_stmt_close($insert_stmt);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — SkillBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:    #1d3557; --accent:     #2ec4b6;
            --highlight:  #f4a261; --light-bg:   #f0f4f8;
            --card-bg:    #ffffff; --text-dark:  #1a202c; --text-muted: #718096;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--light-bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Sora', sans-serif; }
        .auth-card { background: var(--card-bg); border-radius: 20px; box-shadow: 0 20px 60px rgba(29, 53, 87, 0.12); padding: 2.5rem; width: 100%; max-width: 480px; }
        .brand-logo { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; text-decoration: none; }
        .brand-logo .logo-icon { width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 1.2rem; font-weight: 800; font-family: 'Sora', sans-serif; }
        .brand-logo .logo-text { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.3rem; color: var(--primary); }
        .brand-logo .logo-text span { color: var(--accent); }
        .auth-card h2 { font-size: 1.6rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.25rem; }
        .auth-card .subtitle { color: var(--text-muted); font-size: 0.92rem; margin-bottom: 1.75rem; }
        .form-label { font-weight: 500; color: var(--text-dark); font-size: 0.9rem; }
        .form-control, .form-select { border: 2px solid #e2e8f0; border-radius: 10px; padding: 0.65rem 1rem; font-family: 'DM Sans', sans-serif; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus, .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(46, 196, 182, 0.15); outline: none; }
        .btn-register { background: var(--primary); color: white; border: none; border-radius: 10px; padding: 0.75rem; font-family: 'Sora', sans-serif; font-weight: 600; font-size: 1rem; width: 100%; transition: background 0.2s, transform 0.1s; cursor: pointer; }
        .btn-register:hover { background: #2a4a7f; transform: translateY(-1px); }
        .sdg-badge { display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(46, 196, 182, 0.1); color: var(--accent); border: 1px solid rgba(46, 196, 182, 0.3); border-radius: 20px; padding: 0.3rem 0.8rem; font-size: 0.8rem; font-weight: 600; margin-bottom: 1.5rem; }
    </style>
</head>
<body>

<div class="auth-card">
    <a href="index.php" class="brand-logo">
        <div class="logo-icon">SB</div>
        <div class="logo-text">Skill<span>Bridge</span></div>
    </a>

    <div class="sdg-badge">
        <i class="bi bi-globe-americas"></i>
        UN SDG 08: Decent Work & Economic Growth
    </div>

    <h2>Create Your Account</h2>
    <p class="subtitle">Join thousands of students launching their careers.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="mb-3">
            <label for="name" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="name" name="name" placeholder="e.g., Ahmad bin Razak" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="e.g., student@university.edu" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Minimum 8 characters" required>
        </div>
        <div class="mb-3">
            <label for="confirm" class="form-label">Confirm Password</label>
            <input type="password" class="form-control" id="confirm" name="confirm" placeholder="Re-enter your password" required>
        </div>
        <div class="mb-4">
            <label for="role" class="form-label">I am a...</label>
            <select class="form-select" id="role" name="role" required>
                <option value="" disabled <?= empty($_POST['role']) ? 'selected' : '' ?>>Select your role</option>
                <option value="talent"    <?= ($_POST['role'] ?? '') === 'talent'    ? 'selected' : '' ?>>🎓 Student / Talent — Looking for internships</option>
                <option value="recruiter" <?= ($_POST['role'] ?? '') === 'recruiter' ? 'selected' : '' ?>>🏢 Recruiter — Posting micro-internships</option>
            </select>
        </div>
        <button type="submit" class="btn-register">
            <i class="bi bi-person-plus-fill me-2"></i>Create Account
        </button>
    </form>

    <p class="text-center mt-4 mb-0" style="font-size: 0.9rem; color: var(--text-muted);">
        Already have an account?
        <a href="login.php" style="color: var(--accent); font-weight: 600; text-decoration: none;">Sign In</a>
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>