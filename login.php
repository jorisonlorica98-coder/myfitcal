<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'user';
    header('Location: ' . ($role === 'admin' ? '/myfitcal_system/admin/dashboard.php' : '/myfitcal_system/setup/step1-profile.php'));
    exit;
}

// ── Auto-reset expired locks when redirected after countdown ──
if (isset($_GET['unlocked'])) {
    $db = getDB();
    $db->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE locked_until IS NOT NULL AND locked_until <= NOW()")
       ->execute();
}

$error           = '';
$locked_msg      = '';
$attempts_msg    = '';
$secs_remaining  = 0;
$registerSuccess = '';

if (!empty($_SESSION['register_success'])) {
    $registerSuccess = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, name, email, password, role, is_active, failed_attempts, locked_until FROM users WHERE email = ? AND role = 'user' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'No account found with that email.';
            } else {
                // ── Check if account is currently locked ──────────
                if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
                    $now            = new DateTime();
                    $unlock         = new DateTime($user['locked_until']);
                    $diff           = $now->diff($unlock);
                    $secs_remaining = $diff->s + ($diff->i * 60);
                    $locked_msg     = "Account temporarily locked due to too many failed attempts.";
                } else {
                    // ── Lock expired — auto reset if needed ────────
                    if ($user['locked_until'] && new DateTime() >= new DateTime($user['locked_until'])) {
                        $db->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?")
                           ->execute([$user['id']]);
                        $user['failed_attempts'] = 0;
                        $user['locked_until']    = null;
                    }

                    // ── Not locked — attempt login ─────────────────
                    if (!$user['is_active']) {
                        $error = 'Your account has been deactivated. Contact the administrator.';
                    } elseif (!password_verify($password, $user['password'])) {
                        // WRONG PASSWORD
                        $new_attempts = ($user['failed_attempts'] ?? 0) + 1;

                        if ($new_attempts >= 3) {
                            $lock_until     = (new DateTime())->modify('+5 minutes')->format('Y-m-d H:i:s');
                            $secs_remaining = 300;
                            $db->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?")
                               ->execute([$new_attempts, $lock_until, $user['id']]);
                            $locked_msg = "Too many failed login attempts. Your account has been locked for <strong>5 minutes</strong>.";
                        } else {
                            $remaining    = 3 - $new_attempts;
                            $db->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?")
                               ->execute([$new_attempts, $user['id']]);
                            $attempts_msg = $remaining;
                            $error        = "Invalid email or password.";
                        }
                    } else {
                        // ── CORRECT PASSWORD — generate OTP, don't login yet ──
                        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
                            $db->prepare("UPDATE users SET password = ? WHERE id = ?")
                               ->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]);
                        }

                        // Reset failed attempts
                        $db->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?")
                           ->execute([$user['id']]);

                        // Generate 6-digit OTP
                        $otp    = strval(rand(100000, 999999));
                        $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                        // Save OTP to users table
                        $db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")
                           ->execute([$otp, $expiry, $user['id']]);

                        // Store pending session (not fully logged in yet)
                        $_SESSION['pending_user_id']   = $user['id'];
                        $_SESSION['pending_user_name'] = $user['name'];
                        $_SESSION['pending_user_email'] = $user['email'];

                        // Send OTP via PHPMailer
                        sendOTPEmail($user['email'], $user['name'], $otp);

                        header('Location: /myfitcal_system/verify-otp.php');
                        exit;
                    }
                }
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
<title>Sign In — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--primary:#142c5b;--primary-alt:#0a1f44;--muted:#64748b;--border:#e5e7eb;}
html,body{height:100%;font-family:'Plus Jakarta Sans',sans-serif;color:#111827;font-display:swap;}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:url('assets/image/login.png') center/cover fixed;background-color:#0f172a;position:relative;}
body::before{content:'';position:absolute;inset:0;background:rgba(15,23,42,.72);backdrop-filter:blur(2px);}

.page-shell{position:relative;z-index:1;width:100%;max-width:540px;padding:1.25rem;}
.auth-card{background:rgba(255,255,255,.98);border-radius:24px;box-shadow:0 12px 40px rgba(15,23,42,.18);border:1px solid rgba(15,23,42,.1);overflow:hidden;transform:translateZ(0);}
.card-inner{padding:2.2rem 2.5rem 2rem;}

.brand{display:flex;align-items:center;justify-content:center;gap:0.75rem;margin-bottom:1rem;}
.brand img{width:52px;height:52px;object-fit:contain;}
.brand-name{font-size:1.5rem;font-weight:900;color:#111827;letter-spacing:-0.3px;}

.page-title{font-size:2.2rem;font-weight:900;color:#111827;text-align:center;line-height:1.05;margin-bottom:0.45rem;}
.page-desc{font-size:0.98rem;color:#475569;text-align:center;line-height:1.7;margin-bottom:1.75rem;}

/* ── ALERTS ── */
.alert{border-radius:14px;padding:0.95rem 1.1rem;font-size:0.88rem;font-weight:600;display:flex;align-items:flex-start;gap:0.6rem;margin-bottom:1.25rem;line-height:1.5;}
.alert i{font-size:1rem;flex-shrink:0;margin-top:1px;}
.alert-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;}
.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
.alert-lock{background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;}

/* Attempt dots */
.attempt-badges{display:flex;gap:5px;margin-top:8px;}
.attempt-dot{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;}
.attempt-dot.used{background:#fecaca;color:#b91c1c;}
.attempt-dot.left{background:#fee2e2;color:#ef4444;border:1.5px dashed #fca5a5;}

/* Countdown */
.countdown-wrap{margin-top:10px;}
.countdown-bar-track{height:5px;background:rgba(194,65,12,.2);border-radius:999px;overflow:hidden;margin-bottom:5px;}
.countdown-bar-fill{height:100%;background:#c2410c;border-radius:999px;transition:width 1s linear;}
.countdown-text{font-size:0.82rem;color:#c2410c;font-weight:700;}

.form-panel{display:grid;gap:1.1rem;}
.field{display:grid;gap:0.5rem;}
.field label{font-size:0.78rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.08em;}
.iw{position:relative;}
.iw i.ico{position:absolute;left:1.2rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1.1rem;pointer-events:none;}
.iw input{width:100%;padding:1rem 1rem 1rem 3.4rem;border:1px solid #e5e7eb;border-radius:16px;background:#f8fafc;color:#0f172a;font-size:1rem;outline:none;transition:border .2s,background .2s;}
.iw input:focus{border-color:rgba(10,31,68,.4);background:#fff;}
.iw input::placeholder{color:#94a3b8;}
.iw input.input-err{border-color:#fca5a5;background:#fff5f5;}
.pw-eye{position:absolute;right:1.1rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1.1rem;padding:0;transition:color .2s;}
.pw-eye:hover{color:var(--primary-alt);}

.form-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;font-size:0.9rem;color:#475569;margin:0.25rem 0;}
.remember{display:flex;align-items:center;gap:0.55rem;cursor:pointer;user-select:none;}
.remember input{accent-color:var(--primary);width:18px;height:18px;}
.forgot{font-weight:700;color:var(--primary);text-decoration:none;}
.forgot:hover{color:var(--primary-alt);}

.btn-submit{width:100%;padding:1.1rem;border:none;border-radius:16px;background:linear-gradient(135deg,var(--primary-alt),var(--primary));color:#fff;font-size:1.1rem;font-weight:800;cursor:pointer;letter-spacing:0.02em;transition:transform .2s,box-shadow .2s;box-shadow:0 8px 25px rgba(10,31,68,.25);}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 12px 32px rgba(10,31,68,.3);}
.btn-submit:disabled{opacity:.55;cursor:not-allowed;transform:none;}
.btn-submit span{display:inline-block;transform:translateX(0);transition:transform .2s;}
.btn-submit:hover:not(:disabled) span{transform:translateX(4px);}

.action-row{display:grid;gap:0.85rem;margin-top:1.2rem;}
.option-btn{display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;padding:1rem;border-radius:16px;border:1px solid #e5e7eb;background:#fff;color:#0f172a;font-weight:700;text-decoration:none;transition:background .2s,border-color .2s;font-size:0.95rem;}
.option-btn:hover{background:#f8fafc;border-color:#cbd5e1;}
.option-btn.secondary{background:#f8fafc;border-color:transparent;}

.footer-note{text-align:center;font-size:0.88rem;color:#64748b;margin-top:1.5rem;}

@media(max-width:520px){
  .page-shell{padding:1rem;}
  .auth-card{border-radius:20px;}
  .card-inner{padding:1.8rem 1.6rem 1.4rem;}
  .page-title{font-size:1.9rem;}
  .form-row{flex-direction:column;align-items:flex-start;}
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

      <h1 class="page-title">Sign In</h1>
      <p class="page-desc">Access your MyFitCal account</p>

      <?php if ($registerSuccess): ?>
      <div class="alert alert-ok">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= htmlspecialchars($registerSuccess) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($locked_msg): ?>
      <div class="alert alert-lock">
        <i class="bi bi-lock-fill"></i>
        <div style="flex:1;">
          <div><?= $locked_msg ?></div>
          <div class="countdown-wrap">
            <div class="countdown-bar-track">
              <div class="countdown-bar-fill" id="cntBar" style="width:100%"></div>
            </div>
            <div class="countdown-text">
              Unlocking in <span id="cntNum"><?= $secs_remaining ?></span>s...
            </div>
          </div>
        </div>
      </div>
      <?php elseif ($error): ?>
      <div class="alert alert-err">
        <i class="bi bi-exclamation-circle-fill"></i>
        <div>
          <div><?= htmlspecialchars($error) ?></div>
          <?php if ($attempts_msg !== ''): ?>
          <div style="font-size:0.82rem;margin-top:6px;">
            <span style="opacity:.8;">Attempts remaining: </span>
            <strong style="color:#b91c1c;"><?= $attempts_msg ?> of 3</strong>
          </div>
          <div class="attempt-badges">
            <?php
              $used = 3 - (int)$attempts_msg;
              for ($i = 1; $i <= 3; $i++) {
                $cls = $i <= $used ? 'used' : 'left';
                echo "<span class='attempt-dot {$cls}'>{$i}</span>";
              }
            ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" class="form-panel">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="field">
          <label>Username / Email</label>
          <div class="iw">
            <i class="bi bi-person-circle ico"></i>
            <input type="email" name="email"
                   placeholder="Username / Email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   autocomplete="email"
                   class="<?= $error ? 'input-err' : '' ?>"
                   <?= $locked_msg ? 'disabled' : '' ?>
                   required>
          </div>
        </div>

        <div class="field">
          <label>Password</label>
          <div class="iw">
            <i class="bi bi-lock ico"></i>
            <input type="password" name="password" id="pwField"
                   placeholder="Password"
                   class="<?= $error ? 'input-err' : '' ?>"
                   <?= $locked_msg ? 'disabled' : '' ?>
                   required>
            <button type="button" class="pw-eye"
                    onclick="togglePw('pwField','pwIcon')"
                    <?= $locked_msg ? 'disabled' : '' ?>>
              <i class="bi bi-eye" id="pwIcon"></i>
            </button>
          </div>
        </div>

        <div class="form-row">
          <label class="remember">
            <input type="checkbox" name="remember" <?= $locked_msg ? 'disabled' : '' ?>>
            Remember Me
          </label>
          <a href="Forget_password.php" class="forgot">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-submit" <?= $locked_msg ? 'disabled' : '' ?>>
          <?= $locked_msg
            ? '<i class="bi bi-lock-fill"></i> Account Locked'
            : 'Login securely <span>→</span>' ?>
        </button>
      </form>

      <div class="action-row">
        <a href="register.php" class="option-btn"><i class="bi bi-person-plus"></i> Create account</a>
        <a href="admin-login.php" class="option-btn secondary"><i class="bi bi-shield-shaded"></i> Admin login</a>
      </div>

      <div class="footer-note">
        <strong>MyFitCal</strong> | © MyFitCal. All Rights Reserved.
      </div>

    </div>
  </div>
</div>

<script>
function togglePw(fid, iid) {
  var f = document.getElementById(fid), i = document.getElementById(iid);
  if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
  else { f.type = 'password'; i.className = 'bi bi-eye'; }
}

// Countdown — synced with actual PHP remaining seconds
var cntNum = document.getElementById('cntNum');
var cntBar = document.getElementById('cntBar');
if (cntNum && cntBar) {
  var total     = <?= (int)$secs_remaining ?>;
  var remaining = total;

  cntBar.style.width = '100%';

  var timer = setInterval(function() {
    remaining--;
    if (remaining <= 0) {
      clearInterval(timer);
      cntNum.textContent = '0';
      cntBar.style.width = '0%';
      window.location.href = window.location.pathname + '?unlocked=1';
    } else {
      cntNum.textContent = remaining;
      cntBar.style.width = ((remaining / total) * 100) + '%';
    }
  }, 1000);
}
</script>
</body>
</html>