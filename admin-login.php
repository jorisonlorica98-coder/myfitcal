<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin') {
    header('Location: /myfitcal_system/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id,name,email,password,role,is_active FROM users WHERE email=? AND role='admin' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Invalid admin credentials.';
            } elseif (!$user['is_active']) {
                $error = 'This admin account has been deactivated.';
            } else {
                setUserSession($user);
                header('Location: /myfitcal_system/admin/dashboard.php');
                exit;
            }
        }
    }
}
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Portal — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--primary:#0f2b60;--primary-alt:#0a1f44;--green:#16a34a;--muted:#64748b;--border:#d5e2f2;--input-bg:#f0f6ff;--slate:#0f172a;}
html,body{height:100%;font-family:'Plus Jakarta Sans',sans-serif;color:#111827;font-display: swap;}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:url('assets/image/login.png') center/cover fixed;background-color:#0f172a;position:relative;}
body::before{content:'';position:absolute;inset:0;background:rgba(15,23,42,.72);backdrop-filter: blur(3px);}


.page-shell{position:relative;z-index:1;width:100%;max-width:620px;padding:2rem 1.25rem;}
.auth-card{background:rgba(255,255,255,.98);border-radius:28px;box-shadow:0 20px 60px rgba(15,23,42,.25);border:1px solid rgba(15,23,42,.08);overflow:hidden;transform: translateZ(0);}
.card-inner{padding:2rem 3rem 1.8rem;}


.brand{display:flex;align-items:center;justify-content:center;gap:0.9rem;margin-bottom:1rem;}
.brand img{width:58px;height:58px;object-fit:contain;}
.brand-name{font-size:1.7rem;font-weight:900;color:var(--slate);letter-spacing:-0.3px;}
.brand-sub{font-size:1rem;color:var(--muted);text-align:center;line-height:1.5;margin-bottom:1.5rem;}


.admin-badge{
  display:inline-flex;align-items:center;gap:.4rem;
  background:#eff6ff;border:1px solid #bfdbfe;
  color:var(--primary);font-size:0.9rem;font-weight:700;
  padding:.4rem 1.2rem;border-radius:999px;
  margin:0 auto 1.5rem;display:flex;justify-content:center;width:fit-content;
}


.page-title{font-size:2.1rem;font-weight:900;color:var(--slate);text-align:center;line-height:1.05;margin-bottom:0.55rem;}
.page-title span{color:var(--primary);}
.page-desc{font-size:1.1rem;color:#475569;text-align:center;line-height:1.7;margin-bottom:2rem;}


.alert-err{border-radius:16px;padding:0.95rem 1rem;font-size:0.88rem;font-weight:600;display:flex;align-items:center;gap:0.55rem;margin-bottom:1.25rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}


.form-panel{display:grid;gap:1.2rem;}
.field{display:grid;gap:0.6rem;}
.field label{font-size:0.85rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.06em;}
.iw{position:relative;}
.iw i.ico{position:absolute;left:1.3rem;top:50%;transform:translateY(-50%);color:#7186a4;font-size:1.2rem;pointer-events:none;}
.iw input{width:100%;padding:1.1rem 1rem 1.1rem 3.6rem;border:1px solid var(--border);border-radius:18px;background:var(--input-bg);color:#0f172a;font-size:1.05rem;outline:none;transition: border .2s, background .2s;}
.iw input:focus{border-color:rgba(15, 43, 96,.5);background:#fff;}
.iw input::placeholder{color:#7186a4;}
.pw-eye{position:absolute;right:1.3rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#7186a4;cursor:pointer;font-size:1.2rem;padding:0;transition:color .2s;}
.pw-eye:hover{color: var(--primary);}


.btn-submit{width:100%;padding:1.2rem;border:none;border-radius:18px;background:var(--primary);color:#fff;font-size:1.2rem;font-weight:800;cursor:pointer;letter-spacing:0.02em;transition:transform .2s, box-shadow .2s;box-shadow:0 10px 35px rgba(15, 43, 96,.4);margin-top:0.4rem;}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 15px 42px rgba(15, 43, 96,.45);}


.trust-row{display:flex;align-items:center;justify-content:center;gap:1.5rem;margin-top:1.5rem;}
.trust-item{display:flex;align-items:center;gap:0.4rem;font-size:0.9rem;color:#94a3b8;font-weight:600;}


.divider{display:flex;align-items:center;gap:0.8rem;margin:2rem 0 1rem;color:#94a3b8;font-size:0.95rem;}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e2e8f0;}


.back-row{text-align:center;}
.back-row p{font-size:1.05rem;color:#475569;margin-bottom:1rem;}
.btn-back{display:inline-flex;align-items:center;gap:0.5rem;padding:1rem 2rem;border:1px solid var(--border);border-radius:18px;background:#fff;color:#111827;font-size:1.1rem;font-weight:700;text-decoration:none;transition:background .2s;}
.btn-back:hover{background:#f8fafc;}
.back-row a{color:var(--primary);text-decoration:none;}
.back-row a:hover{color:var(--primary-alt);}


.footer-note{margin-top:1.8rem;text-align:center;font-size:0.95rem;color:#64748b;line-height:1.6;}
.footer-note strong{color:#111827;}


@media(max-width:520px){
  .page-shell{padding:1.5rem 1rem;}
  .auth-card{border-radius:22px;}
  .card-inner{padding:1.8rem 1.6rem 1.4rem;}
  .page-title{font-size:2rem;}
  .trust-row{flex-direction:column;gap:0.8rem;}
}
</style>
</head>
<body>
<div class="page-shell">
  <div class="auth-card">
    <div class="card-inner">
      <div class="brand">
        <img src="assets/image/logo.png" alt="MyFitCal">
        <span class="brand-name">MYFITCAL</span>
      </div>
      <p class="brand-sub">Personalized Fitness & Calorie Management</p>

      <div class="admin-badge">
        <i class="bi bi-shield-fill"></i> Administrator Portal
      </div>

      <h1 class="page-title">Admin <span>Sign In</span></h1>
      <p class="page-desc">Enter your admin credentials to access the dashboard.</p>

      <?php if ($error): ?>
      <div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="form-panel">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="login">

        <div class="field">
          <label>Email Address</label>
          <div class="iw">
            <i class="bi bi-envelope ico"></i>
            <input type="email" name="email" value="myfitcal01@gmail.com" placeholder="admin@email.com" autocomplete="email" required>
          </div>
        </div>

        <div class="field">
          <label>Password</label>
          <div class="iw">
            <i class="bi bi-lock ico"></i>
            <input type="password" name="password" id="pwField" placeholder="Enter admin password" required>
            <button type="button" class="pw-eye" onclick="togglePw()">
              <i class="bi bi-eye" id="pwIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <i class="bi bi-shield-check"></i> Sign In to Admin Portal
        </button>
      </form>

      <div class="trust-row">
        <div class="trust-item"><i class="bi bi-shield-lock"></i> Secure Access</div>
        <div class="trust-item"><i class="bi bi-lock"></i> Encrypted</div>
        <div class="trust-item"><i class="bi bi-person-badge"></i> Admin Only</div>
      </div>

      <div class="divider"></div>
      <div class="back-row">
        <p>Not an administrator?</p>
        <a href="login.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to User Login</a>
      </div>

      <div class="footer-note"><strong>MyFitCal</strong> | © MyFitCal. All Rights Reserved.</div>
    </div>
  </div>
</div>

<script>
function togglePw() {
  var f = document.getElementById('pwField');
  var i = document.getElementById('pwIcon');
  if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
  else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
</body>
</html>