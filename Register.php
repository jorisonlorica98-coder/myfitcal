<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (isLoggedIn()) { header('Location: user/dashboard.php'); exit; }

$errors = []; $success = false; $old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh the page.';
    } else {
        $old = [
            'name'   => sanitize($_POST['name']   ?? ''),
            'email'  => sanitize($_POST['email']  ?? ''),
            'gender' => sanitize($_POST['gender'] ?? ''),
        ];
        $pw  = $_POST['password']         ?? '';
        $pw2 = $_POST['password_confirm'] ?? '';

        if (empty($old['name']) || strlen($old['name']) < 2)
            $errors[] = 'Full name must be at least 2 characters.';
        if (empty($old['email']) || !filter_var($old['email'], FILTER_VALIDATE_EMAIL))
            $errors[] = 'Please enter a valid email address.';
        if (strlen($pw) < 8)
            $errors[] = 'Password must be at least 8 characters.';
        if (!preg_match('/[A-Z]/', $pw))
            $errors[] = 'Password must contain at least one uppercase letter.';
        if (!preg_match('/[0-9]/', $pw))
            $errors[] = 'Password must contain at least one number.';
        if ($pw !== $pw2)
            $errors[] = 'Passwords do not match.';

        // reCAPTCHA v2 verification (cURL - stable sa localhost)
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        if (empty($recaptcha_response)) {
            $errors[] = 'Please complete the reCAPTCHA verification.';
        } else {
            $secret = '6Lep7tYsAAAAABmDQsg9RiuKJfruM01pWAlN_yyf';
            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'secret'   => $secret,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ]));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result       = curl_exec($ch);
            curl_close($ch);
            $captcha_data = json_decode($result);
            if (!$captcha_data || !$captcha_data->success) {
                $errors[] = 'reCAPTCHA verification failed. Please try again.';
            }
        }

        $db = getDB();

        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $chk->execute([$old['email']]);
            if ($chk->fetch()) $errors[] = 'An account with this email already exists.';
        }

        if (empty($errors)) {
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
            // ✅ CHANGED: is_active=0, status='pending' — requires admin approval
            $ins  = $db->prepare("INSERT INTO users (name, email, password, gender, is_active, status) VALUES (?,?,?,?,0,'pending')");
            $ins->execute([$old['name'], $old['email'], $hash, $old['gender'] ?: null]);
            // ✅ CHANGED: message updated to reflect pending approval
            $_SESSION['register_success'] = 'Your account has been submitted for review. You will receive an email once approved by the admin.';
            header('Location: login.php');
            exit;
        }
    }
}
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Account — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- reCAPTCHA v2 script -->
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--primary:#0f2b60;--primary-alt:#0a1f44;--green:#16a34a;--muted:#64748b;--border:#d5e2f2;--input-bg:#f0f6ff;}
html,body{height:100%;font-family:'Plus Jakarta Sans',sans-serif;color:#111827;font-display: swap;}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:url('assets/image/login.png') center/cover fixed;background-color:#0f172a;position:relative;}
body::before{content:'';position:absolute;inset:0;background:rgba(15,23,42,.72);backdrop-filter: blur(3px);}

.page-shell{position:relative;z-index:1;width:100%;max-width:620px;padding:2rem 1.25rem;}
.auth-card{background:rgba(255,255,255,.98);border-radius:28px;box-shadow:0 20px 60px rgba(15,23,42,.25);border:1px solid rgba(15,23,42,.08);overflow:hidden;transform: translateZ(0);}

.card-inner{padding:1.6rem 2.5rem 1.2rem;}

.brand{display:flex;align-items:center;justify-content:center;gap:0.9rem;margin-bottom:0.7rem;}
.brand img{width:50px;height:50px;object-fit:contain;}
.brand-name{font-size:1.7rem;font-weight:900;color:#111827;letter-spacing:-0.3px;}

.page-title{font-size:2.1rem;font-weight:900;color:#111827;text-align:center;line-height:1.05;margin-bottom:0.4rem;}
.page-desc{font-size:1.05rem;color:#475569;text-align:center;line-height:1.5;margin-bottom:1.1rem;}

.alert-err{border-radius:16px;padding:0.7rem 1rem;font-size:0.85rem;font-weight:600;display:flex;align-items:center;gap:0.55rem;margin-bottom:1rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
.alert-err ul{padding-left:1.1rem;margin-top:0.3rem;}
.alert-err li{margin-top:0.15rem;}

.form-panel{display:grid;gap:0.8rem;}
.field{display:grid;gap:0.5rem;}
.field label{font-size:0.85rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.06em;}
.iw{position:relative;}
.iw i.ico{position:absolute;left:1.3rem;top:50%;transform:translateY(-50%);color:#7186a4;font-size:1.2rem;pointer-events:none;}
.iw input,.iw select{width:100%;padding:0.95rem 1rem 0.95rem 3.6rem;border:1px solid var(--border);border-radius:18px;background:var(--input-bg);color:#0f172a;font-size:1.05rem;outline:none;transition: border .2s, background .2s;appearance:none;}
.iw input:focus,.iw select:focus{border-color:rgba(15, 43, 96,.5);background:#fff;}
.iw input::placeholder{color:#7186a4;}
.pw-eye{position:absolute;right:1.3rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#7186a4;cursor:pointer;font-size:1.2rem;padding:0;transition:color .2s;}
.pw-eye:hover{color: var(--primary);}

.pw-strength{margin-top:0.35rem;}
.pw-bars{display:flex;gap:5px;margin-bottom:0.35rem;}
.pw-bar{flex:1;height:5px;border-radius:999px;background:#e2e8f0;transition:background .3s;}
.pw-bar.weak{background:#ef4444;}
.pw-bar.fair{background:#f97316;}
.pw-bar.good{background:#eab308;}
.pw-bar.strong{background:#16a34a;}
.pw-text{font-size:0.82rem;font-weight:700;color:#94a3b8;transition:color .3s;}
.pw-text.weak{color:#ef4444;}
.pw-text.fair{color:#f97316;}
.pw-text.good{color:#eab308;}
.pw-text.strong{color:#16a34a;}
.pw-checks{display:flex;flex-wrap:wrap;gap:0.3rem 0.8rem;margin-top:0.25rem;}
.pw-check{font-size:0.82rem;font-weight:600;color:#cbd5e1;display:flex;align-items:center;gap:0.35rem;transition:color .2s;}
.pw-check.met{color:#16a34a;}
.pw-check i{font-size:0.82rem;}

.captcha-wrap{display:flex;justify-content:center;margin-top:0.2rem;}

.btn-submit{width:100%;padding:1rem;border:none;border-radius:18px;background:var(--primary);color:#fff;font-size:1.2rem;font-weight:800;cursor:pointer;letter-spacing:0.02em;transition:transform .2s, box-shadow .2s;box-shadow:0 10px 35px rgba(15, 43, 96,.4);margin-top: 0.2rem;}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 15px 42px rgba(15, 43, 96,.45);}
.btn-submit span{display:inline-block;transform:translateX(0);transition:transform .2s;}
.btn-submit:hover span{transform:translateX(4px);}

.divider{display:flex;align-items:center;gap:0.8rem;margin:1.1rem 0 0.7rem;color:#94a3b8;font-size:0.95rem;}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e2e8f0;}

.link-row{text-align:center;font-size:1.05rem;color:#475569;}
.link-row a{font-weight:700;color:var(--primary);text-decoration:none;}
.link-row a:hover{color:var(--primary-alt);}

.footer-note{margin-top:0.8rem;text-align:center;font-size:0.95rem;color:#64748b;line-height:1.6;}
.footer-note strong{color:#111827;}

@media(max-width:520px){
  .page-shell{padding:1.5rem 1rem;}
  .auth-card{border-radius:22px;}
  .card-inner{padding:1.4rem 1.6rem 1.1rem;}
  .page-title{font-size:2rem;}
  .g-recaptcha{transform:scale(0.85);transform-origin:center;}
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
      <h1 class="page-title">Create your Account</h1>
      <p class="page-desc">Join MyFitCal and start your fitness journey today.</p>

      <?php if (!empty($errors)): ?>
      <div class="alert-err">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?php if (count($errors) === 1): ?>
          <?= htmlspecialchars($errors[0]) ?>
        <?php else: ?>
          Please fix the following:
          <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="form-panel">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="field">
          <label>Full Name</label>
          <div class="iw">
            <i class="bi bi-person ico"></i>
            <input type="text" name="name" placeholder="Full Name"
                   value="<?= htmlspecialchars($old['name'] ?? '') ?>" required>
          </div>
        </div>

        <div class="field">
          <label>Email Address</label>
          <div class="iw">
            <i class="bi bi-envelope ico"></i>
            <input type="email" name="email" placeholder="myfitcal01@gmail.com"
                   value="<?= htmlspecialchars($old['email'] ?? '') ?>" autocomplete="email" required>
          </div>
        </div>

        <div class="field">
          <label>Password</label>
          <div class="iw">
            <i class="bi bi-lock ico"></i>
            <input type="password" name="password" id="pw1" placeholder="Enter a password"
                   oninput="checkStrength(this.value)" required>
            <button type="button" class="pw-eye" onclick="togglePw('pw1','pi1')">
              <i class="bi bi-eye" id="pi1"></i>
            </button>
          </div>
          <div class="pw-strength">
            <div class="pw-bars">
              <div class="pw-bar" id="bar1"></div>
              <div class="pw-bar" id="bar2"></div>
              <div class="pw-bar" id="bar3"></div>
              <div class="pw-bar" id="bar4"></div>
            </div>
            <div class="pw-text" id="pwText">Enter a password</div>
            <div class="pw-checks">
              <span class="pw-check" id="chk8"><i class="bi bi-check-circle-fill"></i> 8+ chars</span>
              <span class="pw-check" id="chkUpper"><i class="bi bi-check-circle-fill"></i> Uppercase</span>
              <span class="pw-check" id="chkNum"><i class="bi bi-check-circle-fill"></i> Number</span>
            </div>
          </div>
        </div>

        <div class="field">
          <label>Confirm Password</label>
          <div class="iw">
            <i class="bi bi-lock ico"></i>
            <input type="password" name="password_confirm" id="pw2"
                   placeholder="Re-enter your password"
                   oninput="checkMatch()" required>
            <button type="button" class="pw-eye" onclick="togglePw('pw2','pi2')">
              <i class="bi bi-eye" id="pi2"></i>
            </button>
          </div>
          <div id="matchMsg" style="font-size:0.8rem;font-weight:700;margin-top:0.3rem;display:none;"></div>
        </div>

        <div class="field">
          <label>Gender</label>
          <div class="iw">
            <i class="bi bi-person ico"></i>
            <select name="gender" required>
              <option value="" disabled selected>Gender</option>
              <option value="male"   <?= ($old['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Male</option>
              <option value="female" <?= ($old['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
              <option value="">Prefer not to say</option>
            </select>
          </div>
        </div>

        <!-- reCAPTCHA v2 widget -->
        <div class="captcha-wrap">
          <div class="g-recaptcha" data-sitekey="6Lep7tYsAAAAAB9UL1w3QQZ-e82MjS3oxpslNljJ"></div>
        </div>

        <button type="submit" class="btn-submit">Create Account <span>→</span></button>
      </form>

      <div class="divider">or</div>
      <div class="link-row">Already have an account? <a href="login.php">Sign in</a></div>
      <div class="footer-note"><strong>MyFitCal</strong> | © MyFitCal. All Rights Reserved.</div>
    </div>
  </div>
</div>

<script>
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function togglePw(fid, iid) {
  var f = document.getElementById(fid), i = document.getElementById(iid);
  if (f.type === 'password') {
      f.type = 'text';
      i.classList.remove('bi-eye');
      i.classList.add('bi-eye-slash');
  } else {
      f.type = 'password';
      i.classList.remove('bi-eye-slash');
      i.classList.add('bi-eye');
  }
}

const checkStrength = debounce(function(pw) {
  var has8    = pw.length >= 8;
  var hasUp   = /[A-Z]/.test(pw);
  var hasNum  = /[0-9]/.test(pw);
  var hasSym  = /[^A-Za-z0-9]/.test(pw);

  document.getElementById('chk8').className     = 'pw-check' + (has8   ? ' met' : '');
  document.getElementById('chkUpper').className  = 'pw-check' + (hasUp  ? ' met' : '');
  document.getElementById('chkNum').className    = 'pw-check' + (hasNum ? ' met' : '');

  var score = [has8, hasUp, hasNum, hasSym].filter(Boolean).length;
  if (pw.length === 0) score = 0;

  var levels = ['','weak','fair','good','strong'];
  var labels = ['Enter a password','Weak','Fair','Good','Strong'];
  var level  = levels[score] || '';
  var label  = labels[score] || 'Enter a password';

  ['bar1','bar2','bar3','bar4'].forEach(function(id, i) {
    var el = document.getElementById(id);
    el.className = 'pw-bar' + (i < score && level ? ' ' + level : '');
  });

  var txt = document.getElementById('pwText');
  txt.textContent  = label;
  txt.className    = 'pw-text' + (level ? ' ' + level : '');

  checkMatch();
}, 300);

const checkMatch = debounce(function() {
  var pw1 = document.getElementById('pw1').value;
  var pw2 = document.getElementById('pw2').value;
  var msg = document.getElementById('matchMsg');
  if (!pw2) { msg.style.display = 'none'; return; }
  msg.style.display = 'block';
  if (pw1 === pw2) {
    msg.textContent  = '✓ Passwords match';
    msg.style.color  = '#16a34a';
  } else {
    msg.textContent  = '✗ Passwords do not match';
    msg.style.color  = '#ef4444';
  }
}, 300);
</script>
</body>
</html>