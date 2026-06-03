<?php

$required_role = 'admin';
require_once 'session_check.php';
require_once 'db_connect.php';

$action_message = '';
$action_type    = ''; 

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {

    $delete_id = (int) $_GET['id'];

    if ($delete_id === (int) $_SESSION['user_id']) {
        $action_message = "You cannot delete your own administrator account.";
        $action_type    = "danger";

    } else {

        $del_stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, "i", $delete_id); 
        mysqli_stmt_execute($del_stmt);

        if (mysqli_stmt_affected_rows($del_stmt) > 0) {
            $action_message = "User has been successfully deleted.";
            $action_type    = "success";
        } else {
            $action_message = "User not found or already deleted.";
            $action_type    = "warning";
        }
        mysqli_stmt_close($del_stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {

    $edit_id    = (int) $_POST['edit_id'];
    $edit_name  = htmlspecialchars(stripslashes(trim($_POST['edit_name']  ?? '')));
    $edit_email = htmlspecialchars(stripslashes(trim($_POST['edit_email'] ?? '')));
    $edit_role  = htmlspecialchars(stripslashes(trim($_POST['edit_role']  ?? '')));

    if (empty($edit_name) || empty($edit_email) || empty($edit_role)) {
        $action_message = "Edit failed: all fields are required.";
        $action_type    = "danger";

    } elseif (!filter_var($edit_email, FILTER_VALIDATE_EMAIL)) {
        $action_message = "Edit failed: invalid email address.";
        $action_type    = "danger";

    } elseif (!in_array($edit_role, ['admin', 'talent', 'recruiter'])) {
        $action_message = "Edit failed: invalid role value.";
        $action_type    = "danger";

    } else {

        $upd_stmt = mysqli_prepare(
            $conn,
            "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?"
        );

        mysqli_stmt_bind_param($upd_stmt, "sssi", $edit_name, $edit_email, $edit_role, $edit_id);
        mysqli_stmt_execute($upd_stmt);

        if (mysqli_stmt_affected_rows($upd_stmt) >= 0) {
            $action_message = "User '{$edit_name}' updated successfully.";
            $action_type    = "success";
        } else {
            $action_message = "Update failed. Please try again.";
            $action_type    = "danger";
        }
        mysqli_stmt_close($upd_stmt);
    }
}

$users_result = mysqli_query($conn, "SELECT id, name, email, role, trust_points, created_at FROM users ORDER BY created_at DESC");

$stats = [];

$r = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
$stats['total_users'] = mysqli_fetch_assoc($r)['count'];

$r = mysqli_query($conn, "SELECT COUNT(*) as count FROM jobs");
$stats['total_jobs'] = mysqli_fetch_assoc($r)['count'];

$r = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications");
$stats['total_applications'] = mysqli_fetch_assoc($r)['count'];

$r = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'talent'");
$stats['total_talent'] = mysqli_fetch_assoc($r)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — SkillBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1d3557; --accent: #2ec4b6; --highlight: #f4a261;
            --light-bg: #f0f4f8; --sidebar-w: 260px;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--light-bg); margin: 0; }
        h1,h2,h3,h4,h5,h6 { font-family: 'Sora', sans-serif; }

        .sidebar {
            width: var(--sidebar-w); min-height: 100vh; background: var(--primary);
            position: fixed; top: 0; left: 0; z-index: 100; padding: 1.5rem 0;
            display: flex; flex-direction: column;
        }
        .sidebar-brand {
            padding: 0 1.5rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; gap: 0.6rem;
        }
        .sidebar-brand .logo-icon {
            width: 36px; height: 36px; background: var(--accent);
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            font-weight: 800; color: white; font-family: 'Sora', sans-serif; font-size: 0.9rem;
        }
        .sidebar-brand .logo-text { color: white; font-weight: 700; font-family: 'Sora', sans-serif; font-size: 1.1rem; }
        .sidebar-brand .logo-text span { color: var(--accent); }

        .sidebar-nav { padding: 1.25rem 0; flex: 1; }
        .nav-section-title {
            color: rgba(255,255,255,0.4); font-size: 0.7rem; font-weight: 600;
            letter-spacing: 0.08em; text-transform: uppercase;
            padding: 0.75rem 1.5rem 0.25rem;
        }
        .nav-link-item {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.7rem 1.5rem; color: rgba(255,255,255,0.7);
            text-decoration: none; font-size: 0.92rem; font-weight: 500;
            transition: all 0.2s; border-left: 3px solid transparent;
        }
        .nav-link-item:hover, .nav-link-item.active {
            background: rgba(255,255,255,0.08); color: white;
            border-left-color: var(--accent);
        }
        .nav-link-item i { width: 20px; text-align: center; font-size: 1rem; }

        .sidebar-footer {
            padding: 1.25rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.1);
        }
        .user-chip {
            display: flex; align-items: center; gap: 0.6rem;
            color: rgba(255,255,255,0.8); font-size: 0.85rem;
        }
        .user-avatar {
            width: 32px; height: 32px; background: var(--accent);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: white; font-size: 0.8rem; flex-shrink: 0;
        }

        .main-content { margin-left: var(--sidebar-w); padding: 2rem; }

        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.75rem;
        }
        .page-header h2 { font-size: 1.6rem; font-weight: 700; color: var(--primary); margin: 0; }
        .page-header .breadcrumb { font-size: 0.85rem; color: #718096; margin: 0; }

        .stat-card {
            background: white; border-radius: 14px; padding: 1.25rem 1.5rem;
            border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            display: flex; align-items: center; gap: 1rem;
        }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }
        .stat-icon.blue   { background: rgba(29,53,87,0.1);   color: var(--primary); }
        .stat-icon.teal   { background: rgba(46,196,182,0.1); color: var(--accent);  }
        .stat-icon.orange { background: rgba(244,162,97,0.1); color: var(--highlight);}
        .stat-icon.green  { background: rgba(72,187,120,0.1); color: #48bb78; }

        .stat-label { font-size: 0.8rem; color: #718096; font-weight: 500; margin-bottom: 0.15rem; }
        .stat-value { font-family: 'Sora', sans-serif; font-size: 1.7rem; font-weight: 700; color: var(--primary); }

        .data-card {
            background: white; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden;
        }
        .data-card-header {
            padding: 1.25rem 1.5rem; border-bottom: 1px solid #f0f4f8;
            display: flex; justify-content: space-between; align-items: center;
        }
        .data-card-header h5 { font-size: 1rem; font-weight: 700; color: var(--primary); margin: 0; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #f8fafc; color: #718096; font-size: 0.75rem;
            font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
            padding: 0.85rem 1.25rem; border-bottom: 1px solid #edf2f7;
            font-family: 'DM Sans', sans-serif;
        }
        tbody td {
            padding: 1rem 1.25rem; border-bottom: 1px solid #f7fafc;
            font-size: 0.9rem; color: #2d3748; vertical-align: middle;
        }
        tbody tr:hover { background: #fafcff; }
        tbody tr:last-child td { border-bottom: none; }

        .badge-role {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.25rem 0.7rem; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600;
        }
        .badge-admin     { background: rgba(229,62,62,0.1);   color: #c53030; }
        .badge-talent    { background: rgba(46,196,182,0.1);  color: #0d7c73; }
        .badge-recruiter { background: rgba(244,162,97,0.12); color: #c05621; }

        .btn-edit   { background: rgba(46,196,182,0.1); color: #0d7c73; border: 1px solid rgba(46,196,182,0.3); border-radius: 8px; padding: 0.3rem 0.75rem; font-size: 0.82rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-edit:hover   { background: var(--accent); color: white; border-color: var(--accent); }
        .btn-delete { background: rgba(229,62,62,0.08); color: #c53030; border: 1px solid rgba(229,62,62,0.2); border-radius: 8px; padding: 0.3rem 0.75rem; font-size: 0.82rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-delete:hover { background: #e53e3e; color: white; border-color: #e53e3e; }

        .trust-pill {
            display: inline-flex; align-items: center; gap: 0.3rem;
            background: rgba(244,162,97,0.12); color: #c05621;
            border-radius: 20px; padding: 0.2rem 0.6rem; font-size: 0.8rem; font-weight: 600;
        }
    </style>
</head>
<body>


<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon">SB</div>
        <div class="logo-text">Skill<span>Bridge</span></div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Administration</div>
        <a href="admin_dashboard.php" class="nav-link-item active" id="nav-adash">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <div class="nav-link-item" id="nav-ausers" onclick="scrollToSection('users-table','nav-ausers')">
            <i class="bi bi-people-fill"></i> Manage Users
        </div>

        <div class="nav-section-title" style="margin-top:0.5rem;">Platform</div>
        <a href="logout.php" class="nav-link-item">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-chip">
            
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
            <div>
                <div style="font-weight:600; font-size:0.88rem;"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div style="font-size:0.75rem; color:rgba(255,255,255,0.5);">Administrator</div>
            </div>
        </div>
    </div>
</aside>


<main class="main-content">

    
    <div class="page-header">
        <div>
            <h2><i class="bi bi-shield-lock-fill me-2" style="color:var(--accent)"></i>Admin Dashboard</h2>
            <div class="breadcrumb">SkillBridge / Administration / Overview</div>
        </div>
        <div style="font-size:0.85rem; color:#718096;">
            <i class="bi bi-clock me-1"></i>
            Session expires after <strong>15 min</strong> of inactivity
        </div>
    </div>

    
    <?php if (!empty($action_message)): ?>
        <div class="alert alert-<?= $action_type ?> d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-<?= $action_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
            <?= htmlspecialchars($action_message) ?>
        </div>
    <?php endif; ?>

   
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= $stats['total_users'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon teal"><i class="bi bi-briefcase-fill"></i></div>
                <div>
                    <div class="stat-label">Job Postings</div>
                    <div class="stat-value"><?= $stats['total_jobs'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div>
                    <div class="stat-label">Applications</div>
                    <div class="stat-value"><?= $stats['total_applications'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-mortarboard-fill"></i></div>
                <div>
                    <div class="stat-label">Talent Accounts</div>
                    <div class="stat-value"><?= $stats['total_talent'] ?></div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="data-card" id="users-table">
        <div class="data-card-header">
            <h5><i class="bi bi-people me-2"></i>All Registered Users</h5>
            <span style="font-size:0.85rem; color:#718096;">
                <?= mysqli_num_rows($users_result) ?> users total
            </span>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Trust Points</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    if (mysqli_num_rows($users_result) > 0):
                        while ($user = mysqli_fetch_assoc($users_result)):

                            $badge_class = 'badge-' . $user['role'];
                    ?>
                    <tr>
                        <td style="color:#a0aec0; font-size:0.8rem;"><?= $user['id'] ?></td>
                        <td>
                            
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <div style="width:32px; height:32px; background:var(--primary); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:0.8rem; font-weight:700; flex-shrink:0;">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                            </div>
                        </td>
                        <td style="color:#718096;"><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <span class="badge-role <?= $badge_class ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="trust-pill">
                                <i class="bi bi-star-fill" style="font-size:0.7rem;"></i>
                                <?= (int)$user['trust_points'] ?> pts
                            </span>
                        </td>
                        <td style="color:#718096; font-size:0.85rem;">
                            <?= date('d M Y', strtotime($user['created_at'])) ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                
                                <button
                                    class="btn-edit"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editModal"
                                    data-id="<?= $user['id'] ?>"
                                    data-name="<?= htmlspecialchars($user['name']) ?>"
                                    data-email="<?= htmlspecialchars($user['email']) ?>"
                                    data-role="<?= $user['role'] ?>"
                                >
                                    <i class="bi bi-pencil-fill me-1"></i>Edit
                                </button>

                                
                                <?php if ($user['id'] !== (int)$_SESSION['user_id']): ?>
                                <a
                                    href="admin_dashboard.php?action=delete&id=<?= $user['id'] ?>"
                                    class="btn-delete"
                                    onclick="return confirm('Are you sure you want to DELETE user \'<?= addslashes($user['name']) ?>\'? This action cannot be undone.');"
                                >
                                    <i class="bi bi-trash-fill me-1"></i>Delete
                                </a>
                                <?php else: ?>
                                    <span style="font-size:0.78rem; color:#a0aec0;">(You)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:3rem; color:#a0aec0;">
                            <i class="bi bi-inbox" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>
                            No users found in the database.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>


<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header" style="border-bottom: 1px solid #f0f4f8; padding: 1.25rem 1.5rem;">
                <h5 class="modal-title" id="editModalLabel" style="font-family:'Sora',sans-serif; font-weight:700; color:var(--primary);">
                    <i class="bi bi-pencil-fill me-2" style="color:var(--accent);"></i>Edit User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            
            <form method="POST" action="admin_dashboard.php">
                
                <input type="hidden" name="action" value="edit_user">
                
                <input type="hidden" name="edit_id" id="edit_id">

                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label" style="font-weight:600; font-size:0.88rem; color:#2d3748;">Full Name</label>
                        <input type="text" class="form-control" id="edit_name" name="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label" style="font-weight:600; font-size:0.88rem; color:#2d3748;">Email Address</label>
                        <input type="email" class="form-control" id="edit_email" name="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label" style="font-weight:600; font-size:0.88rem; color:#2d3748;">Role</label>
                        <select class="form-select" id="edit_role" name="edit_role" required>
                            <option value="admin">Admin</option>
                            <option value="talent">Talent</option>
                            <option value="recruiter">Recruiter</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer" style="border-top: 1px solid #f0f4f8; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background:var(--primary); color:white; font-weight:600; border-radius:8px;">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>

document.addEventListener('DOMContentLoaded', function () {

    var editModal = document.getElementById('editModal');

    editModal.addEventListener('show.bs.modal', function (event) {

        var button = event.relatedTarget;

        var userId    = button.getAttribute('data-id');
        var userName  = button.getAttribute('data-name');
        var userEmail = button.getAttribute('data-email');
        var userRole  = button.getAttribute('data-role');

        editModal.querySelector('#edit_id').value    = userId;
        editModal.querySelector('#edit_name').value  = userName;
        editModal.querySelector('#edit_email').value = userEmail;
        editModal.querySelector('#edit_role').value  = userRole;
    });
});

function scrollToSection(sectionId, navId) {
    document.querySelectorAll('.nav-link-item').forEach(function(el) { el.classList.remove('active'); });
    var navEl = document.getElementById(navId);
    if (navEl) navEl.classList.add('active');
    var section = document.getElementById(sectionId);
    if (section) section.scrollIntoView({ behavior: 'smooth' });
}

var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
        if (entry.isIntersecting && entry.target.id === 'users-table') {
            document.querySelectorAll('.nav-link-item').forEach(function(el) { el.classList.remove('active'); });
            var navEl = document.getElementById('nav-ausers');
            if (navEl) navEl.classList.add('active');
        }
    });
}, { threshold: 0.2 });
var usersTable = document.getElementById('users-table');
if (usersTable) observer.observe(usersTable);
</script>
</body>
</html>
