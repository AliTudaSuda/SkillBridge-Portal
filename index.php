<?php

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    $dash = ['admin'=>'admin_dashboard.php','talent'=>'talent_dashboard.php','recruiter'=>'recruiter_dashboard.php'];
    header("Location: " . ($dash[$_SESSION['role']] ?? 'login.php'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillBridge — Micro-Internship Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#1d3557; --accent:#2ec4b6; --highlight:#f4a261; }
        body { font-family:'DM Sans',sans-serif; margin:0; background:white; }
        h1,h2,h3,h4,h5 { font-family:'Sora',sans-serif; }

        .navbar { background:white; box-shadow:0 1px 20px rgba(0,0,0,0.08); padding:1rem 2rem; }
        .navbar-brand { font-family:'Sora',sans-serif; font-weight:800; font-size:1.3rem; color:var(--primary) !important; display:flex; align-items:center; gap:0.5rem; }
        .brand-icon { width:36px; height:36px; background:var(--primary); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--accent); font-weight:800; font-size:0.9rem; }
        .brand-text span { color:var(--accent); }

        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, #2a4a7f 60%, #1a6b68 100%);
            padding: 6rem 0 5rem; color: white; position: relative; overflow: hidden;
        }
        .hero::before {
            content: ''; position: absolute; top: -50%; right: -20%; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(46,196,182,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero h1 { font-size: clamp(2rem, 5vw, 3.2rem); font-weight: 800; line-height: 1.15; }
        .hero h1 span { color: var(--accent); }
        .hero p { font-size: 1.1rem; opacity: 0.85; line-height: 1.7; max-width: 540px; }

        .sdg-pill {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: rgba(46,196,182,0.15); border: 1px solid rgba(46,196,182,0.4);
            color: var(--accent); border-radius: 20px; padding: 0.4rem 1rem;
            font-size: 0.85rem; font-weight: 600; margin-bottom: 1.5rem;
        }

        .btn-cta-primary {
            background: var(--accent); color: white; border: none;
            border-radius: 12px; padding: 0.85rem 2rem;
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: 1rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;
            transition: all 0.2s;
        }
        .btn-cta-primary:hover { background: #25a99e; color: white; transform: translateY(-2px); }

        .btn-cta-secondary {
            background: rgba(255,255,255,0.1); color: white; border: 1.5px solid rgba(255,255,255,0.4);
            border-radius: 12px; padding: 0.85rem 2rem;
            font-family: 'Sora', sans-serif; font-weight: 600; font-size: 1rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;
            transition: all 0.2s;
        }
        .btn-cta-secondary:hover { background: rgba(255,255,255,0.2); color: white; }

        .section { padding: 5rem 0; }
        .section-title { font-size: 2rem; font-weight: 800; color: var(--primary); }
        .section-sub { color: #718096; font-size: 1rem; max-width: 500px; margin: 0 auto; }

        .feature-card {
            background: white; border-radius: 16px; padding: 2rem;
            box-shadow: 0 4px 24px rgba(29,53,87,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 8px 32px rgba(29,53,87,0.14); }
        .feature-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1.25rem; }
        .fi-blue   { background: rgba(29,53,87,0.08); color: var(--primary); }
        .fi-teal   { background: rgba(46,196,182,0.1); color: var(--accent); }
        .fi-orange { background: rgba(244,162,97,0.12); color: var(--highlight); }
        .fi-green  { background: rgba(72,187,120,0.1); color: #48bb78; }
        .fi-purple { background: rgba(128,90,213,0.1); color: #805ad5; }
        .fi-red    { background: rgba(229,62,62,0.08); color: #e53e3e; }

        .feature-card h5 { font-size: 1.05rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem; }
        .feature-card p { color: #718096; font-size: 0.9rem; line-height: 1.6; margin: 0; }

        .how-section { background: var(--primary); color: white; }
        .step-num { width: 48px; height: 48px; background: rgba(46,196,182,0.2); border: 2px solid rgba(46,196,182,0.4); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.1rem; color: var(--accent); flex-shrink: 0; }

        footer { background: #0f1e2e; color: rgba(255,255,255,0.6); padding: 2.5rem 0; font-size: 0.88rem; text-align: center; }
        footer strong { color: white; }
    </style>
</head>
<body>


<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <div class="brand-icon">SB</div>
            <div class="brand-text">Skill<span>Bridge</span></div>
        </a>
        <div class="ms-auto d-flex gap-2">
            <a href="login.php" class="btn btn-outline-primary" style="border-radius:10px; font-weight:600; border-color:var(--primary); color:var(--primary);">Sign In</a>
            <a href="register.php" class="btn" style="border-radius:10px; font-weight:600; background:var(--primary); color:white;">Get Started</a>
        </div>
    </div>
</nav>


<section class="hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="sdg-pill">
                    <i class="bi bi-globe-americas"></i>
                    Aligned with UN SDG 08: Decent Work & Economic Growth
                </div>
                <h1>Bridge the Gap Between <span>Student Talent</span> and Real Opportunity</h1>
                <p class="mt-3 mb-4">SkillBridge connects university students with micro-internship opportunities, building careers one skill at a time. Earn Trust Points, build your portfolio, and launch your future.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="register.php" class="btn-cta-primary">
                        <i class="bi bi-person-plus-fill"></i>Join as Talent
                    </a>
                    <a href="register.php" class="btn-cta-secondary">
                        <i class="bi bi-building"></i>Post Internships
                    </a>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-flex justify-content-center" style="margin-top:2rem;">
                
                <div style="position:relative; width:280px;">
                    <div style="background:white; border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 10px 40px rgba(0,0,0,0.2); margin-bottom:1rem;">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div style="width:40px; height:40px; background:rgba(46,196,182,0.15); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">🎓</div>
                            <div>
                                <div style="font-size:0.78rem; color:#718096; font-weight:500;">Students Placed</div>
                                <div style="font-family:'Sora',sans-serif; font-size:1.6rem; font-weight:800; color:var(--primary);">1,240+</div>
                            </div>
                        </div>
                    </div>
                    <div style="background:white; border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 10px 40px rgba(0,0,0,0.2); margin-left:2rem;">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div style="width:40px; height:40px; background:rgba(244,162,97,0.15); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">⭐</div>
                            <div>
                                <div style="font-size:0.78rem; color:#718096; font-weight:500;">Trust Points Awarded</div>
                                <div style="font-family:'Sora',sans-serif; font-size:1.6rem; font-weight:800; color:var(--primary);">48,300</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<section class="section" style="background:#f8fafc;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Everything You Need to Launch Your Career</h2>
            <p class="section-sub mt-2">A full-featured platform built with security, usability, and real impact in mind.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon fi-blue"><i class="bi bi-shield-lock-fill"></i></div>
                    <h5>Secure Authentication</h5>
                    <p>Bcrypt password hashing, session timeout after 15 minutes, and SQL injection prevention via prepared statements.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon fi-teal"><i class="bi bi-trophy-fill"></i></div>
                    <h5>Gamified Trust Points</h5>
                    <p>Earn +10 Trust Points every time a recruiter accepts your application. Build your credibility score.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon fi-orange"><i class="bi bi-bell-fill"></i></div>
                    <h5>Real-Time Notifications</h5>
                    <p>Instant in-app alerts when your application status changes — never miss an opportunity.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon fi-green"><i class="bi bi-cloud-upload-fill"></i></div>
                    <h5>Resume Upload</h5>
                    <p>Upload your CV or portfolio (PDF, JPG, PNG — up to 2MB) directly to your profile for recruiters to review.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon fi-purple"><i class="bi bi-people-fill"></i></div>
                    <h5>Three-Role System</h5>
                    <p>Separate dashboards for Admins, Talent, and Recruiters — each with tailored features and access control.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon fi-red"><i class="bi bi-globe-americas"></i></div>
                    <h5>UN SDG 08 Aligned</h5>
                    <p>Designed to promote decent work and economic growth by connecting students with meaningful micro-internships.</p>
                </div>
            </div>
        </div>
    </div>
</section>


<section class="section how-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 style="font-size:2rem; font-weight:800; color:white;">How SkillBridge Works</h2>
            <p style="color:rgba(255,255,255,0.6);">Simple. Secure. Effective.</p>
        </div>
        <div class="row g-4">
            <?php
            $steps = [
                ['Register & Choose Role','Sign up as a Talent (student) or Recruiter. Your role determines your dashboard.','bi-person-plus'],
                ['Build Your Profile','Talents upload their resume. Recruiters set up their company profile.','bi-person-badge'],
                ['Connect & Apply','Recruiters post micro-internships. Talents browse and apply in one click.','bi-briefcase'],
                ['Get Accepted & Earn Points','Accepted? You earn Trust Points and get notified instantly.','bi-trophy'],
            ];
            foreach ($steps as $i => $step):
            ?>
            <div class="col-md-3">
                <div style="display:flex; flex-direction:column; align-items:center; text-align:center; padding:1.5rem;">
                    <div class="step-num mb-3"><?= $i+1 ?></div>
                    <div style="width:52px; height:52px; background:rgba(255,255,255,0.07); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:var(--accent); margin-bottom:1rem;">
                        <i class="bi <?= $step[2] ?>"></i>
                    </div>
                    <h5 style="font-size:0.95rem; font-weight:700; color:white; margin-bottom:0.5rem;"><?= $step[0] ?></h5>
                    <p style="font-size:0.85rem; color:rgba(255,255,255,0.55); line-height:1.6; margin:0;"><?= $step[1] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="register.php" class="btn-cta-primary" style="font-size:1.05rem; padding:1rem 2.5rem;">
                <i class="bi bi-rocket-takeoff-fill me-2"></i>Start Your Journey
            </a>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <p class="mb-1">
            <strong>SkillBridge</strong> — Micro-Internship Marketplace
        </p>
        <p class="mb-0">
            Final Year Web Programming Assessment · Aligned with <strong>UN SDG 08: Decent Work & Economic Growth</strong>
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
