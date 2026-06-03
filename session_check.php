<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['last_action'])) {

    $seconds_inactive = time() - $_SESSION['last_action'];

    $timeout_threshold = 900;

    if ($seconds_inactive > $timeout_threshold) {

        session_unset();

        session_destroy();

        header("Location: login.php?timeout=1");
        exit();
    }
}

$_SESSION['last_action'] = time();

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();
}

if (isset($required_role)) {

    if ($_SESSION['role'] !== $required_role) {

        $redirect_map = [
            'admin'     => 'admin_dashboard.php',
            'talent'    => 'talent_dashboard.php',
            'recruiter' => 'recruiter_dashboard.php',
        ];

        $their_dashboard = $redirect_map[$_SESSION['role']] ?? 'login.php';

        header("Location: " . $their_dashboard);
        exit();
    }
}

?>
