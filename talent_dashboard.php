<?php

$required_role = 'talent';
require_once 'session_check.php';
require_once 'db_connect.php';

$talent_id = (int) $_SESSION['user_id'];

$action_message = '';
$action_type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_resume') {

    if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {

        $file      = $_FILES['resume_file'];
        $file_name = $file['name'];
        $file_tmp  = $file['tmp_name'];
        $file_size = $file['size'];
        $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($file_type, $allowed_types)) {
            $action_message = "Invalid file type. Only PDF, JPG, JPEG, PNG are allowed.";
            $action_type    = "danger";

        } elseif ($file_size > 2 * 1024 * 1024) {

            $action_message = "File too large. Maximum allowed size is 2MB.";
            $action_type    = "danger";

        } else {

            $unique_name    = 'resume_' . $talent_id . '_' . uniqid() . '.' . $file_type;
            $upload_dir     = 'uploads/resumes/';
            $upload_path    = $upload_dir . $unique_name;

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (move_uploaded_file($file_tmp, $upload_path)) {

                $upd_stmt = mysqli_prepare($conn, "UPDATE users SET profile_resume = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd_stmt, "si", $upload_path, $talent_id);
                mysqli_stmt_execute($upd_stmt);
                mysqli_stmt_close($upd_stmt);

                $action_message = "Resume uploaded successfully!";
                $action_type    = "success";

            } else {
                $action_message = "File upload failed. Check server write permissions on /uploads/.";
                $action_type    = "danger";
            }
        }
    } else {
        $action_message = "No file was selected. Please choose a file before uploading.";
        $action_type    = "warning";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {

    $job_id = (int) $_POST['job_id'];

    $check_stmt = mysqli_prepare($conn, "SELECT id FROM applications WHERE job_id = ? AND talent_id = ?");
    mysqli_stmt_bind_param($check_stmt, "ii", $job_id, $talent_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);

    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $action_message = "You have already applied for this job.";
        $action_type    = "warning";
    } else {

        $app_stmt = mysqli_prepare($conn, "INSERT INTO applications (job_id, talent_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($app_stmt, "ii", $job_id, $talent_id);

        if (mysqli_stmt_execute($app_stmt)) {
            $action_message = "Application submitted successfully! The recruiter will review it soon.";
            $action_type    = "success";
        } else {
            $action_message = "Application failed. Please try again.";
            $action_type    = "danger";
        }
        mysqli_stmt_close($app_stmt);
    }
    mysqli_stmt_close($check_stmt);
}

if (isset($_GET['action']) && $_GET['action'] === 'mark_read') {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = {$talent_id}");
    header("Location: talent_dashboard.php");
    exit();
}

$me_result  = mysqli_query($conn, "SELECT * FROM users WHERE id = {$talent_id}");
$me         = mysqli_fetch_assoc($me_result);

$jobs_query = "
    SELECT j.id, j.title, j.description, j.created_at, u.name AS recruiter_name
    FROM jobs j
    JOIN users u ON j.recruiter_id = u.id
    WHERE j.status = 'open'
    ORDER BY j.created_at DESC
";
$jobs_result = mysqli_query($conn, $jobs_query);

$applied_result = mysqli_query($conn, "SELECT job_id FROM applications WHERE talent_id = {$talent_id}");
$applied_job_ids = [];
while ($row = mysqli_fetch_assoc($applied_result)) {
    $applied_job_ids[] = (int) $row['job_id'];
}

$my_apps_query = "
    SELECT a.id, a.status, a.applied_at, j.title AS job_title, u.name AS recruiter_name
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON j.recruiter_id = u.id
    WHERE a.talent_id = {$talent_id}
    ORDER BY a.applied_at DESC
";
$my_apps_result = mysqli_query($conn, $my_apps_query);

$notif_result = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = {$talent_id} ORDER BY created_at DESC LIMIT 10");
$unread_count = 0;
$notifications = [];
while ($notif = mysqli_fetch_assoc($notif_result)) {
    $notifications[] = $notif;
    if ($notif['is_read'] == 0) $unread_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard — SkillBridge</title>
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
        .user-avatar { width:32px; height:32px; background:var(--accent); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:white; font-size:0.8rem; flex-shrink:0; }

        .notif-badge { background:var(--highlight); color:white; border-radius:50%; width:18px; height:18px; font-size:0.65rem; font-weight:700; display:inline-flex; align-items:center; justify-content:center; margin-left:auto; }

        .main-content { margin-left:var(--sidebar-w); padding:2rem; }
        .page-header { margin-bottom:1.75rem; }
        .page-header h2 { font-size:1.6rem; font-weight:700; color:var(--primary); margin:0 0 0.25rem; }
        .page-header p { color:#718096; font-size:0.9rem; margin:0; }

        .stat-card { background:white; border-radius:14px; padding:1.25rem 1.5rem; box-shadow:0 2px 12px rgba(0,0,0,0.06); display:flex; align-items:center; gap:1rem; }
        .stat-icon { width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
        .stat-icon.teal   { background:rgba(46,196,182,0.1); color:var(--accent); }
        .stat-icon.orange { background:rgba(244,162,97,0.12); color:var(--highlight); }
        .stat-icon.blue   { background:rgba(29,53,87,0.1); color:var(--primary); }
        .stat-label { font-size:0.8rem; color:#718096; font-weight:500; margin-bottom:0.15rem; }
        .stat-value { font-family:'Sora',sans-serif; font-size:1.7rem; font-weight:700; color:var(--primary); }

        .trust-display {
            background: linear-gradient(135deg, var(--highlight), #e08d50);
            color:white; border-radius:14px; padding:1.25rem 1.5rem;
            display:flex; align-items:center; gap:1rem;
            box-shadow:0 4px 15px rgba(244,162,97,0.35);
        }
        .trust-display .pts { font-family:'Sora',sans-serif; font-size:2rem; font-weight:800; line-height:1; }
        .trust-display .label { font-size:0.82rem; opacity:0.85; }

        .data-card { background:white; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:1.5rem; }
        .data-card-header { padding:1.25rem 1.5rem; border-bottom:1px solid #f0f4f8; display:flex; justify-content:space-between; align-items:center; }
        .data-card-header h5 { font-size:1rem; font-weight:700; color:var(--primary); margin:0; }

        .job-card { padding:1.25rem 1.5rem; border-bottom:1px solid #f7fafc; transition:background 0.2s; }
        .job-card:hover { background:#fafcff; }
        .job-card:last-child { border-bottom:none; }
        .job-title { font-weight:700; color:var(--primary); font-family:'Sora',sans-serif; font-size:0.95rem; margin-bottom:0.2rem; }
        .job-recruiter { font-size:0.82rem; color:#718096; }
        .job-desc { font-size:0.88rem; color:#4a5568; margin-top:0.5rem; line-height:1.5;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }

        .status-badge { display:inline-flex; align-items:center; gap:0.3rem; padding:0.25rem 0.7rem; border-radius:20px; font-size:0.78rem; font-weight:600; }
        .status-pending  { background:rgba(237,137,54,0.1); color:#c05621; }
        .status-accepted { background:rgba(72,187,120,0.12); color:#276749; }
        .status-rejected { background:rgba(229,62,62,0.1);  color:#c53030; }

        .btn-apply { background:var(--accent); color:white; border:none; border-radius:8px; padding:0.4rem 1rem; font-size:0.85rem; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; transition:all 0.2s; }
        .btn-apply:hover { background:#25a99e; transform:translateY(-1px); }
        .btn-applied { background:#f0f4f8; color:#a0aec0; border:none; border-radius:8px; padding:0.4rem 1rem; font-size:0.85rem; font-weight:600; cursor:not-allowed; }

        .upload-area { border:2px dashed #cbd5e0; border-radius:12px; padding:2rem; text-align:center; cursor:pointer; transition:all 0.2s; background:#fafcff; }
        .upload-area:hover { border-color:var(--accent); background:rgba(46,196,182,0.04); }
        .upload-area input[type="file"] { display:none; }

        .notif-item { padding:0.9rem 1.5rem; border-bottom:1px solid #f7fafc; display:flex; align-items:flex-start; gap:0.75rem; }
        .notif-item:last-child { border-bottom:none; }
        .notif-item.unread { background:rgba(46,196,182,0.04); }
        .notif-dot { width:8px; height:8px; background:var(--accent); border-radius:50%; flex-shrink:0; margin-top:0.35rem; }
        .notif-dot.read { background:#e2e8f0; }

        .search-bar { display:flex; align-items:center; gap:0.5rem; background:#f8fafc; border:2px solid #e2e8f0; border-radius:10px; padding:0.5rem 1rem; margin:1rem 1.5rem; transition:border-color 0.2s; }
        .search-bar:focus-within { border-color:var(--accent); }
        .search-bar i { color:#a0aec0; font-size:0.95rem; }
        .search-bar input { border:none; background:transparent; outline:none; font-family:'DM Sans',sans-serif; font-size:0.9rem; color:#2d3748; width:100%; }
        .search-bar input::placeholder { color:#a0aec0; }
        .no-results { text-align:center; padding:2.5rem; color:#a0aec0; display:none; }
        .no-results i { font-size:2rem; display:block; margin-bottom:0.5rem; }

        table { width:100%; border-collapse:collapse; }
        thead th { background:#f8fafc; color:#718096; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; padding:0.85rem 1.25rem; border-bottom:1px solid #edf2f7; font-family:'DM Sans',sans-serif; }
        tbody td { padding:0.9rem 1.25rem; border-bottom:1px solid #f7fafc; font-size:0.88rem; color:#2d3748; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon">SB</div>
        <div class="logo-text">Skill<span>Bridge</span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-title">Talent Portal</div>
        <div class="nav-link-item active" id="nav-dashboard" onclick="scrollToSection('top-section', 'nav-dashboard')">
            <i class="bi bi-grid-fill"></i> Dashboard
        </div>
        <div class="nav-link-item" id="nav-jobs" onclick="scrollToSection('jobs-section', 'nav-jobs')">
            <i class="bi bi-briefcase-fill"></i> Browse Jobs
        </div>
        <div class="nav-link-item" id="nav-apps" onclick="scrollToSection('my-apps-section', 'nav-apps')">
            <i class="bi bi-file-text-fill"></i> My Applications
        </div>
        <div class="nav-link-item" id="nav-upload" onclick="scrollToSection('upload-section', 'nav-upload')">
            <i class="bi bi-cloud-upload-fill"></i> Upload Resume
        </div>

        <div class="nav-section-title" style="margin-top:0.5rem;">Account</div>
        <div class="nav-link-item" id="nav-notif" onclick="scrollToSection('notif-section', 'nav-notif')">
            <i class="bi bi-bell-fill"></i> Notifications
            <?php if ($unread_count > 0): ?>
                <span class="notif-badge"><?= $unread_count ?></span>
            <?php endif; ?>
        </div>
        <a href="logout.php" class="nav-link-item">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
            <div>
                <div style="font-weight:600; font-size:0.88rem;"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div style="font-size:0.75rem; color:rgba(255,255,255,0.5);">🎓 Talent</div>
            </div>
        </div>
    </div>
</aside>


<main class="main-content">

    <div class="page-header" id="top-section">
        <h2>Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?>! 👋</h2>
        <p>Browse opportunities and launch your career journey.</p>
    </div>

    
    <?php if (!empty($action_message)): ?>
        <div class="alert alert-<?= $action_type ?> d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-<?= $action_type === 'success' ? 'check-circle-fill' : ($action_type === 'warning' ? 'exclamation-circle-fill' : 'x-circle-fill') ?>"></i>
            <?= htmlspecialchars($action_message) ?>
        </div>
    <?php endif; ?>

    
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="trust-display">
                <div>
                    <div class="pts"><?= (int)$me['trust_points'] ?></div>
                    <div class="label">Trust Points Earned</div>
                </div>
                <i class="bi bi-trophy-fill ms-auto" style="font-size:2.5rem; opacity:0.5;"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon teal"><i class="bi bi-briefcase-fill"></i></div>
                <div>
                    <div class="stat-label">Open Jobs Available</div>
                    <div class="stat-value"><?= mysqli_num_rows($jobs_result) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-file-earmark-check-fill"></i></div>
                <div>
                    <div class="stat-label">Jobs Applied</div>
                    <div class="stat-value"><?= count($applied_job_ids) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

     
        <div class="col-lg-7">

            
            <div class="data-card" id="jobs-section">
                <div class="data-card-header">
                    <h5><i class="bi bi-briefcase me-2"></i>Available Micro-Internships</h5>
                    <span style="font-size:0.82rem; color:#718096;"><?= mysqli_num_rows($jobs_result) ?> open positions</span>
                </div>

                
                <div class="search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="job-search-input"
                        placeholder="Search jobs by title, recruiter, or keyword..."
                        oninput="filterJobs(this.value)"
                    >
                </div>

                <?php

                mysqli_data_seek($jobs_result, 0);

                if (mysqli_num_rows($jobs_result) > 0):
                    while ($job = mysqli_fetch_assoc($jobs_result)):

                        $already_applied = in_array((int)$job['id'], $applied_job_ids);
                ?>
                <div class="job-card" data-search="<?= strtolower(htmlspecialchars($job['title'] . ' ' . $job['recruiter_name'] . ' ' . $job['description'])) ?>">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;">
                        <div style="flex:1;">
                            <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
                            <div class="job-recruiter">
                                <i class="bi bi-building me-1"></i>
                                <?= htmlspecialchars($job['recruiter_name']) ?>
                                &nbsp;·&nbsp;
                                <i class="bi bi-calendar3 me-1"></i>
                                <?= date('d M Y', strtotime($job['created_at'])) ?>
                            </div>
                            <div class="job-desc"><?= htmlspecialchars($job['description']) ?></div>
                        </div>
                        <div style="flex-shrink:0; padding-top:0.25rem;">
                            <?php if ($already_applied): ?>
                               
                                <button class="btn-applied" disabled>
                                    <i class="bi bi-check2 me-1"></i>Applied
                                </button>
                            <?php else: ?>
                                
                                <form method="POST" action="talent_dashboard.php">
                                    <input type="hidden" name="action" value="apply">
                                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                    <button type="submit" class="btn-apply">
                                        <i class="bi bi-send me-1"></i>Apply
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php
                    endwhile;
                else:
                ?>
                <div style="text-align:center; padding:3rem; color:#a0aec0;">
                    <i class="bi bi-inbox" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>
                    No open jobs at the moment. Check back soon!
                </div>
                <?php endif; ?>
                
                <div class="no-results" id="no-results-msg">
                    <i class="bi bi-search"></i>
                    No jobs match your search. Try a different keyword.
                </div>
            </div>

            <div class="data-card" id="my-apps-section">
                <div class="data-card-header">
                    <h5><i class="bi bi-file-text me-2"></i>My Applications</h5>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Recruiter</th>
                                <th>Applied</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (mysqli_num_rows($my_apps_result) > 0): ?>
                            <?php while ($app = mysqli_fetch_assoc($my_apps_result)): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($app['job_title']) ?></strong></td>
                                <td style="color:#718096;"><?= htmlspecialchars($app['recruiter_name']) ?></td>
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
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding:2rem; color:#a0aec0;">
                                    You haven't applied to any jobs yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>


        <div class="col-lg-5">

          
            <div class="data-card" id="upload-section" style="margin-bottom:1.5rem;">
                <div class="data-card-header">
                    <h5><i class="bi bi-cloud-upload me-2"></i>Resume & Profile</h5>
                </div>
                <div style="padding:1.5rem;">

                    
                    <?php if (!empty($me['profile_resume'])): ?>
                    <div style="background:rgba(46,196,182,0.08); border:1px solid rgba(46,196,182,0.25); border-radius:10px; padding:0.85rem 1rem; margin-bottom:1rem; display:flex; align-items:center; gap:0.6rem;">
                        <i class="bi bi-file-earmark-check-fill" style="color:var(--accent); font-size:1.2rem;"></i>
                        <div>
                            <div style="font-weight:600; font-size:0.85rem; color:var(--primary);">Resume on File</div>
                            <div style="font-size:0.78rem; color:#718096;">
                                <a href="<?= htmlspecialchars($me['profile_resume']) ?>" target="_blank" style="color:var(--accent);">
                                    View / Download
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                
                    <form method="POST" action="talent_dashboard.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_resume">

                    
                        <div class="upload-area" onclick="document.getElementById('resume_file').click();">
                            <i class="bi bi-cloud-arrow-up" style="font-size:2.5rem; color:var(--accent); display:block; margin-bottom:0.5rem;"></i>
                            <div style="font-weight:600; color:var(--primary); font-size:0.92rem;">Click to select file</div>
                            <div style="font-size:0.8rem; color:#718096; margin-top:0.25rem;">PDF, JPG, PNG — max 2MB</div>

                          
                            <input
                                type="file"
                                id="resume_file"
                                name="resume_file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onchange="document.getElementById('file-name-display').textContent = this.files[0]?.name || 'No file selected'"
                            >
                        </div>

                       
                        <p id="file-name-display" style="font-size:0.82rem; color:#718096; margin:0.5rem 0 0.75rem; text-align:center;">No file selected</p>

                        <button type="submit" style="width:100%; background:var(--primary); color:white; border:none; border-radius:10px; padding:0.65rem; font-family:'Sora',sans-serif; font-weight:600; font-size:0.9rem; cursor:pointer; transition:background 0.2s;">
                            <i class="bi bi-upload me-2"></i>Upload Resume
                        </button>
                    </form>
                </div>
            </div>

           
            <div class="data-card" id="notif-section">
                <div class="data-card-header">
                    <h5>
                        <i class="bi bi-bell me-2"></i>Notifications
                        <?php if ($unread_count > 0): ?>
                            <span style="background:var(--highlight); color:white; border-radius:20px; padding:0.1rem 0.5rem; font-size:0.75rem; margin-left:0.35rem;">
                                <?= $unread_count ?> new
                            </span>
                        <?php endif; ?>
                    </h5>
                    <?php if ($unread_count > 0): ?>
                    <a href="talent_dashboard.php?action=mark_read" style="font-size:0.8rem; color:var(--accent); text-decoration:none; font-weight:500;">
                        Mark all read
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notif): ?>
                    <div class="notif-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>">
                        <div class="notif-dot <?= $notif['is_read'] == 1 ? 'read' : '' ?>"></div>
                        <div style="flex:1;">
                            <div style="font-size:0.88rem; color:#2d3748; line-height:1.5;">
                                <?= htmlspecialchars($notif['message']) ?>
                            </div>
                            <div style="font-size:0.75rem; color:#a0aec0; margin-top:0.25rem;">
                                <?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:2.5rem; color:#a0aec0;">
                        <i class="bi bi-bell-slash" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>
                        No notifications yet. Apply to jobs to get started!
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>

function scrollToSection(sectionId, navId) {

    document.querySelectorAll('.nav-link-item').forEach(function(el) {
        el.classList.remove('active');
    });

    var navEl = document.getElementById(navId);
    if (navEl) navEl.classList.add('active');

    var section = document.getElementById(sectionId);
    if (section) section.scrollIntoView({ behavior: 'smooth' });
}

var sectionNavMap = {
    'top-section':      'nav-dashboard',
    'jobs-section':     'nav-jobs',
    'my-apps-section':  'nav-apps',
    'upload-section':   'nav-upload',
    'notif-section':    'nav-notif'
};

var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {

        if (entry.isIntersecting) {
            var navId = sectionNavMap[entry.target.id];
            if (navId) {

                document.querySelectorAll('.nav-link-item').forEach(function(el) {
                    el.classList.remove('active');
                });
                var navEl = document.getElementById(navId);
                if (navEl) navEl.classList.add('active');
            }
        }
    });
}, { threshold: 0.25 });

Object.keys(sectionNavMap).forEach(function(id) {
    var el = document.getElementById(id);
    if (el) observer.observe(el);
});

function filterJobs(searchTerm) {
    var term       = searchTerm.toLowerCase().trim();
    var jobCards   = document.querySelectorAll('.job-card');
    var noResults  = document.getElementById('no-results-msg');
    var visibleCount = 0;

    jobCards.forEach(function(card) {
        var cardText = card.getAttribute('data-search') || '';

        if (term === '' || cardText.includes(term)) {

            card.style.display = '';
            visibleCount++;
        } else {

            card.style.display = 'none';
        }
    });

    if (noResults) {
        noResults.style.display = (visibleCount === 0 && term !== '') ? 'block' : 'none';
    }
}
</script>
</html>
