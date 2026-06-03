<?php

$required_role = 'recruiter';
require_once 'session_check.php';
require_once 'db_connect.php';

$recruiter_id   = (int) $_SESSION['user_id'];
$action_message = '';
$action_type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_job') {

    $title       = htmlspecialchars(stripslashes(trim($_POST['title']       ?? '')));
    $description = htmlspecialchars(stripslashes(trim($_POST['description'] ?? '')));

    if (empty($title) || empty($description)) {
        $action_message = "Both job title and description are required.";
        $action_type    = "danger";

    } elseif (strlen($title) > 200) {
        $action_message = "Job title is too long (max 200 characters).";
        $action_type    = "danger";

    } else {

        $job_stmt = mysqli_prepare(
            $conn,
            "INSERT INTO jobs (recruiter_id, title, description, status) VALUES (?, ?, ?, 'open')"
        );
        mysqli_stmt_bind_param($job_stmt, "iss", $recruiter_id, $title, $description);

        if (mysqli_stmt_execute($job_stmt)) {
            $action_message = "Job '{$title}' posted successfully! Talent can now apply.";
            $action_type    = "success";
        } else {
            $action_message = "Failed to post job. Please try again.";
            $action_type    = "danger";
        }
        mysqli_stmt_close($job_stmt);
    }
}

if (isset($_GET['action']) && in_array($_GET['action'], ['accept', 'reject']) && isset($_GET['app_id'])) {

    $app_id    = (int) $_GET['app_id'];
    $new_status = ($_GET['action'] === 'accept') ? 'accepted' : 'rejected';

    $verify_stmt = mysqli_prepare($conn, "
        SELECT a.id, a.talent_id, a.status, j.title AS job_title, u.name AS talent_name
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        JOIN users u ON a.talent_id = u.id
        WHERE a.id = ? AND j.recruiter_id = ?
    ");
    mysqli_stmt_bind_param($verify_stmt, "ii", $app_id, $recruiter_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    $app_data      = mysqli_fetch_assoc($verify_result);
    mysqli_stmt_close($verify_stmt);

    if (!$app_data) {

        $action_message = "Application not found or access denied.";
        $action_type    = "danger";

    } elseif ($app_data['status'] !== 'pending') {

        $action_message = "This application has already been processed (status: {$app_data['status']}).";
        $action_type    = "warning";

    } else {

        $upd_stmt = mysqli_prepare($conn, "UPDATE applications SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd_stmt, "si", $new_status, $app_id);
        mysqli_stmt_execute($upd_stmt);
        mysqli_stmt_close($upd_stmt);

        if ($new_status === 'accepted') {

            $talent_id_for_points = (int) $app_data['talent_id'];

            $points_stmt = mysqli_prepare($conn, "UPDATE users SET trust_points = trust_points + 10 WHERE id = ?");
            mysqli_stmt_bind_param($points_stmt, "i", $talent_id_for_points);
            mysqli_stmt_execute($points_stmt);
            mysqli_stmt_close($points_stmt);

            $notif_message = "🎉 Congratulations! Your application for '{$app_data['job_title']}' was accepted. You earned +10 Trust Points!";

            $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            mysqli_stmt_bind_param($notif_stmt, "is", $talent_id_for_points, $notif_message);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);

            $action_message = "✅ Application accepted! {$app_data['talent_name']} has been awarded +10 Trust Points and notified.";
            $action_type    = "success";

        } else {

            $talent_id_for_notif = (int) $app_data['talent_id'];
            $notif_message = "Your application for '{$app_data['job_title']}' was not selected this time. Keep applying!";

            $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            mysqli_stmt_bind_param($notif_stmt, "is", $talent_id_for_notif, $notif_message);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);

            $action_message = "Application marked as rejected. The talent has been notified.";
            $action_type    = "info";
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'toggle_job' && isset($_GET['job_id'])) {
    $toggle_job_id = (int) $_GET['job_id'];

    $tog_stmt = mysqli_prepare($conn, "
        UPDATE jobs
        SET status = CASE WHEN status = 'open' THEN 'closed' ELSE 'open' END
        WHERE id = ? AND recruiter_id = ?
    ");
    mysqli_stmt_bind_param($tog_stmt, "ii", $toggle_job_id, $recruiter_id);
    mysqli_stmt_execute($tog_stmt);
    mysqli_stmt_close($tog_stmt);

    header("Location: recruiter_dashboard.php");
    exit();
}

$jobs_query = "
    SELECT j.id, j.title, j.description, j.status, j.created_at,
           COUNT(a.id) AS application_count
    FROM jobs j
    LEFT JOIN applications a ON j.id = a.job_id
    WHERE j.recruiter_id = {$recruiter_id}
    GROUP BY j.id
    ORDER BY j.created_at DESC
";
$jobs_result = mysqli_query($conn, $jobs_query);

$apps_query = "
    SELECT
        a.id AS app_id,
        a.status,
        a.applied_at,
        j.title AS job_title,
        j.id    AS job_id,
        u.name  AS talent_name,
        u.email AS talent_email,
        u.trust_points,
        u.profile_resume
    FROM applications a
    JOIN jobs  j ON a.job_id    = j.id
    JOIN users u ON a.talent_id = u.id
    WHERE j.recruiter_id = {$recruiter_id}
    ORDER BY a.applied_at DESC
";
$apps_result = mysqli_query($conn, $apps_query);

$total_jobs  = mysqli_num_rows($jobs_result);
$total_apps  = mysqli_num_rows($apps_result);
$pending_count = 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruiter Dashboard — SkillBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#1d3557; --accent:#2ec4b6; --highlight:#f4a261; --light-bg:#f0f4f8; --sidebar-w:260px; }
        body { font-family:'DM Sans',sans-serif; background:var(--light-bg); margin:0; }
        h1,h2,h3,h4,h5,h6 { font-family:'Sora',sans-serif; }

        .sidebar { width:var(--sidebar-w); min-height:100vh; background:var(--primary); position:fixed; top:0; left:0; z-index:100; padding:1.5rem 0; display:flex; flex-direction:column; }
        .sidebar-brand { padding:0 1.5rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.1); display:flex; align-items:center; gap:0.6rem; }
        .logo-icon { width:36px; height:36px; background:var(--accent); border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:800; color:white; font-family:'Sora',sans-serif; font-size:0.9rem; }
        .logo-text { color:white; font-weight:700; font-family:'Sora',sans-serif; font-size:1.1rem; }
        .logo-text span { color:var(--accent); }
        .sidebar-nav { padding:1.25rem 0; flex:1; }
        .nav-section-title { color:rgba(255,255,255,0.4); font-size:0.7rem; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; padding:0.75rem 1.5rem 0.25rem; }
        .nav-link-item { display:flex; align-items:center; gap:0.75rem; padding:0.7rem 1.5rem; color:rgba(255,255,255,0.7); text-decoration:none; font-size:0.92rem; font-weight:500; transition:all 0.2s; border-left:3px solid transparent; cursor:pointer; }
        .nav-link-item:hover, .nav-link-item.active { background:rgba(255,255,255,0.08); color:white; border-left-color:var(--accent); }
        .nav-link-item i { width:20px; text-align:center; font-size:1rem; }
        .sidebar-footer { padding:1.25rem 1.5rem; border-top:1px solid rgba(255,255,255,0.1); }
        .user-chip { display:flex; align-items:center; gap:0.6rem; color:rgba(255,255,255,0.8); font-size:0.85rem; }
        .user-avatar { width:32px; height:32px; background:var(--highlight); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:white; font-size:0.8rem; flex-shrink:0; }

        .main-content { margin-left:var(--sidebar-w); padding:2rem; }
        .page-header { margin-bottom:1.75rem; }
        .page-header h2 { font-size:1.6rem; font-weight:700; color:var(--primary); margin:0 0 0.25rem; }

        .stat-card { background:white; border-radius:14px; padding:1.25rem 1.5rem; box-shadow:0 2px 12px rgba(0,0,0,0.06); display:flex; align-items:center; gap:1rem; }
        .stat-icon { width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
        .stat-icon.blue   { background:rgba(29,53,87,0.1); color:var(--primary); }
        .stat-icon.teal   { background:rgba(46,196,182,0.1); color:var(--accent); }
        .stat-icon.orange { background:rgba(244,162,97,0.12); color:var(--highlight); }
        .stat-label { font-size:0.8rem; color:#718096; font-weight:500; margin-bottom:0.15rem; }
        .stat-value { font-family:'Sora',sans-serif; font-size:1.7rem; font-weight:700; color:var(--primary); }

        .data-card { background:white; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:1.5rem; }
        .data-card-header { padding:1.25rem 1.5rem; border-bottom:1px solid #f0f4f8; display:flex; justify-content:space-between; align-items:center; }
        .data-card-header h5 { font-size:1rem; font-weight:700; color:var(--primary); margin:0; }

        .form-panel { padding:1.5rem; }
        .form-label { font-weight:600; font-size:0.88rem; color:#2d3748; }
        .form-control, .form-select { border:2px solid #e2e8f0; border-radius:10px; padding:0.65rem 1rem; font-family:'DM Sans',sans-serif; transition:border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus, .form-select:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(46,196,182,0.15); outline:none; }
        textarea.form-control { resize:vertical; min-height:100px; }
        .btn-post { background:var(--primary); color:white; border:none; border-radius:10px; padding:0.7rem 1.5rem; font-family:'Sora',sans-serif; font-weight:600; cursor:pointer; transition:background 0.2s; }
        .btn-post:hover { background:#2a4a7f; }

        table { width:100%; border-collapse:collapse; }
        thead th { background:#f8fafc; color:#718096; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; padding:0.85rem 1.25rem; border-bottom:1px solid #edf2f7; font-family:'DM Sans',sans-serif; }
        tbody td { padding:0.9rem 1.25rem; border-bottom:1px solid #f7fafc; font-size:0.88rem; color:#2d3748; vertical-align:middle; }
        tbody tr:hover { background:#fafcff; }
        tbody tr:last-child td { border-bottom:none; }

        .status-badge { display:inline-flex; align-items:center; gap:0.3rem; padding:0.25rem 0.7rem; border-radius:20px; font-size:0.78rem; font-weight:600; }
        .status-pending  { background:rgba(237,137,54,0.1); color:#c05621; }
        .status-accepted { background:rgba(72,187,120,0.12); color:#276749; }
        .status-rejected { background:rgba(229,62,62,0.1); color:#c53030; }
        .status-open     { background:rgba(46,196,182,0.1); color:#0d7c73; }
        .status-closed   { background:rgba(160,174,192,0.15); color:#718096; }

        .btn-accept { background:rgba(72,187,120,0.12); color:#276749; border:1px solid rgba(72,187,120,0.3); border-radius:8px; padding:0.3rem 0.8rem; font-size:0.8rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.2s; }
        .btn-accept:hover { background:#48bb78; color:white; border-color:#48bb78; }
        .btn-reject { background:rgba(229,62,62,0.08); color:#c53030; border:1px solid rgba(229,62,62,0.2); border-radius:8px; padding:0.3rem 0.8rem; font-size:0.8rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.2s; }
        .btn-reject:hover { background:#e53e3e; color:white; border-color:#e53e3e; }
        .btn-toggle { background:#f8fafc; color:#718096; border:1px solid #e2e8f0; border-radius:8px; padding:0.3rem 0.8rem; font-size:0.8rem; font-weight:500; cursor:pointer; text-decoration:none; transition:all 0.2s; }
        .btn-toggle:hover { background:#edf2f7; color:#2d3748; }

        .job-title-cell { font-weight:700; color:var(--primary); }
        .job-desc-cell { color:#718096; font-size:0.82rem; max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        .trust-pill { display:inline-flex; align-items:center; gap:0.3rem; background:rgba(244,162,97,0.12); color:#c05621; border-radius:20px; padding:0.2rem 0.6rem; font-size:0.78rem; font-weight:600; }

        .gamification-callout {
            background: linear-gradient(135deg, rgba(46,196,182,0.08), rgba(29,53,87,0.06));
            border: 1px solid rgba(46,196,182,0.2);
            border-radius: 12px; padding: 1rem 1.25rem;
            margin: 0 1.5rem 1rem; font-size: 0.85rem;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .gamification-callout i { font-size: 1.4rem; color: var(--accent); flex-shrink: 0; }
    </style>
</head>
<body>


<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon">SB</div>
        <div class="logo-text">Skill<span>Bridge</span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-title">Recruiter Portal</div>
        <a href="recruiter_dashboard.php" class="nav-link-item active" id="nav-rdash">
            <i class="bi bi-grid-fill"></i> Dashboard
        </a>
        <div class="nav-link-item" id="nav-rpost" onclick="scrollToSection('post-job-section','nav-rpost')">
            <i class="bi bi-plus-circle-fill"></i> Post a Job
        </div>
        <div class="nav-link-item" id="nav-rjobs" onclick="scrollToSection('my-jobs-section','nav-rjobs')">
            <i class="bi bi-briefcase-fill"></i> My Job Listings
        </div>
        <div class="nav-link-item" id="nav-rapps" onclick="scrollToSection('applications-section','nav-rapps')">
            <i class="bi bi-file-earmark-person-fill"></i> Applications
        </div>
        <div class="nav-section-title" style="margin-top:0.5rem;">Account</div>
        <a href="logout.php" class="nav-link-item">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
            <div>
                <div style="font-weight:600; font-size:0.88rem;"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div style="font-size:0.75rem; color:rgba(255,255,255,0.5);">🏢 Recruiter</div>
            </div>
        </div>
    </div>
</aside>


<main class="main-content">

    <div class="page-header">
        <h2>Recruiter Dashboard</h2>
        <p style="color:#718096; font-size:0.9rem; margin:0;">Post opportunities and discover talent.</p>
    </div>

 
    <?php if (!empty($action_message)): ?>
        <div class="alert alert-<?= $action_type ?> d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-<?= $action_type === 'success' ? 'check-circle-fill' : ($action_type === 'info' ? 'info-circle-fill' : 'exclamation-triangle-fill') ?>"></i>
            <?= htmlspecialchars($action_message) ?>
        </div>
    <?php endif; ?>

   
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-briefcase-fill"></i></div>
                <div>
                    <div class="stat-label">Jobs Posted</div>
                    <div class="stat-value"><?= $total_jobs ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon teal"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div>
                    <div class="stat-label">Total Applications</div>
                    <div class="stat-value"><?= $total_apps ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-trophy-fill"></i></div>
                <div>
                    <div class="stat-label">SDG 08</div>
                    <div class="stat-value" style="font-size:1rem; line-height:1.4; margin-top:0.2rem;">Decent Work & Growth</div>
                </div>
            </div>
        </div>
    </div>

   
    <div class="data-card" id="post-job-section">
        <div class="data-card-header">
            <h5><i class="bi bi-plus-circle me-2"></i>Post a New Micro-Internship</h5>
        </div>
        <div class="form-panel">
            
            <form method="POST" action="recruiter_dashboard.php">
                <input type="hidden" name="action" value="post_job">

                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="title" class="form-label">Job Title <span style="color:#e53e3e;">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="title"
                            name="title"
                            placeholder="e.g. UI/UX Design Intern"
                            maxlength="200"
                            required
                        >
                        <div style="font-size:0.78rem; color:#a0aec0; margin-top:0.25rem;">Max 200 characters</div>
                    </div>
                    <div class="col-md-7">
                        <label for="description" class="form-label">Job Description <span style="color:#e53e3e;">*</span></label>
                        <textarea
                            class="form-control"
                            id="description"
                            name="description"
                            placeholder="Describe responsibilities, required skills, duration, and any stipend..."
                            required
                        ></textarea>
                    </div>
                </div>

                <div style="margin-top:1.25rem;">
                    <button type="submit" class="btn-post">
                        <i class="bi bi-send-fill me-2"></i>Publish Job Posting
                    </button>
                </div>
            </form>
        </div>
    </div>

    
    <div class="data-card" id="my-jobs-section">
        <div class="data-card-header">
            <h5><i class="bi bi-briefcase me-2"></i>My Job Listings</h5>
            <span style="font-size:0.82rem; color:#718096;"><?= $total_jobs ?> postings</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description Preview</th>
                        <th>Status</th>
                        <th>Applicants</th>
                        <th>Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if (mysqli_num_rows($jobs_result) > 0):
                    mysqli_data_seek($jobs_result, 0);
                    while ($job = mysqli_fetch_assoc($jobs_result)):
                ?>
                <tr>
                    <td class="job-title-cell"><?= htmlspecialchars($job['title']) ?></td>
                    <td class="job-desc-cell"><?= htmlspecialchars($job['description']) ?></td>
                    <td>
                        <span class="status-badge status-<?= $job['status'] ?>">
                            <?= ucfirst($job['status']) ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:700; color:var(--primary);"><?= $job['application_count'] ?></span>
                        <span style="color:#a0aec0; font-size:0.8rem;"> applied</span>
                    </td>
                    <td style="color:#718096; font-size:0.82rem;"><?= date('d M Y', strtotime($job['created_at'])) ?></td>
                    <td>
                    
                        <a
                            href="recruiter_dashboard.php?action=toggle_job&job_id=<?= $job['id'] ?>"
                            class="btn-toggle"
                            onclick="return confirm('Toggle this job to <?= $job['status'] === 'open' ? 'closed' : 'open' ?>?');"
                        >
                            <i class="bi bi-<?= $job['status'] === 'open' ? 'lock' : 'unlock' ?> me-1"></i>
                            <?= $job['status'] === 'open' ? 'Close' : 'Reopen' ?>
                        </a>
                    </td>
                </tr>
                <?php
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding:3rem; color:#a0aec0;">
                        <i class="bi bi-briefcase" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>
                        No jobs posted yet. Create your first listing above!
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

   
    <div class="data-card" id="applications-section">
        <div class="data-card-header">
            <h5><i class="bi bi-file-earmark-person me-2"></i>Applications Received</h5>
            <span style="font-size:0.82rem; color:#718096;"><?= $total_apps ?> total</span>
        </div>

        
        <div class="gamification-callout">
            <i class="bi bi-trophy-fill"></i>
            <div>
                <strong>Gamification Active:</strong> Accepting an application automatically awards the talent
                <strong>+10 Trust Points</strong> and sends them an <strong>in-app notification</strong>.
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Talent</th>
                        <th>Job Applied For</th>
                        <th>Trust Points</th>
                        <th>Resume</th>
                        <th>Applied</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if (mysqli_num_rows($apps_result) > 0):
                    mysqli_data_seek($apps_result, 0);
                    while ($app = mysqli_fetch_assoc($apps_result)):
                ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <div style="width:32px; height:32px; background:var(--accent); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:0.8rem; font-weight:700; flex-shrink:0;">
                                <?= strtoupper(substr($app['talent_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600; font-size:0.9rem;"><?= htmlspecialchars($app['talent_name']) ?></div>
                                <div style="font-size:0.78rem; color:#a0aec0;"><?= htmlspecialchars($app['talent_email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-weight:500;"><?= htmlspecialchars($app['job_title']) ?></td>
                    <td>
                        <span class="trust-pill">
                            <i class="bi bi-star-fill" style="font-size:0.7rem;"></i>
                            <?= (int)$app['trust_points'] ?> pts
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($app['profile_resume'])): ?>
                            <a href="<?= htmlspecialchars($app['profile_resume']) ?>" target="_blank"
                               style="color:var(--accent); font-size:0.82rem; font-weight:500; text-decoration:none;">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i>View
                            </a>
                        <?php else: ?>
                            <span style="color:#a0aec0; font-size:0.82rem;">None uploaded</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#718096; font-size:0.82rem;"><?= date('d M Y', strtotime($app['applied_at'])) ?></td>
                    <td>
                        <span class="status-badge status-<?= $app['status'] ?>">
                            <?php
                            $icons = ['pending'=>'clock','accepted'=>'check-circle-fill','rejected'=>'x-circle-fill'];
                            echo '<i class="bi bi-' . $icons[$app['status']] . ' me-1"></i>';
                            echo ucfirst($app['status']);
                            ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($app['status'] === 'pending'): ?>
                    
                        <div style="display:flex; gap:0.4rem;">
                            <a
                                href="recruiter_dashboard.php?action=accept&app_id=<?= $app['app_id'] ?>"
                                class="btn-accept"
                                onclick="return confirm('Accept this application? The talent will receive +10 Trust Points and a notification.');"
                            >
                                <i class="bi bi-check2 me-1"></i>Accept
                            </a>
                            <a
                                href="recruiter_dashboard.php?action=reject&app_id=<?= $app['app_id'] ?>"
                                class="btn-reject"
                                onclick="return confirm('Reject this application? The talent will be notified.');"
                            >
                                <i class="bi bi-x me-1"></i>Reject
                            </a>
                        </div>
                        <?php else: ?>
                            
                            <span style="font-size:0.78rem; color:#a0aec0;">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:3rem; color:#a0aec0;">
                        <i class="bi bi-inbox" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>
                        No applications yet. Post a job to start receiving applications!
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>

function scrollToSection(sectionId, navId) {
    document.querySelectorAll('.nav-link-item').forEach(function(el) { el.classList.remove('active'); });
    var navEl = document.getElementById(navId);
    if (navEl) navEl.classList.add('active');
    var section = document.getElementById(sectionId);
    if (section) section.scrollIntoView({ behavior: 'smooth' });
}

var sectionNavMap = {
    'post-job-section':      'nav-rpost',
    'my-jobs-section':       'nav-rjobs',
    'applications-section':  'nav-rapps'
};
var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
        if (entry.isIntersecting) {
            var navId = sectionNavMap[entry.target.id];
            if (navId) {
                document.querySelectorAll('.nav-link-item').forEach(function(el) { el.classList.remove('active'); });
                var navEl = document.getElementById(navId);
                if (navEl) navEl.classList.add('active');
            }
        }
    });
}, { threshold: 0.2 });
Object.keys(sectionNavMap).forEach(function(id) {
    var el = document.getElementById(id);
    if (el) observer.observe(el);
});
</script>
