<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['reset_email']) || empty($_SESSION['otp_verified'])) {
    header('Location: forgot_password.php');
    exit;
}

$email   = $_SESSION['reset_email'];
$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Ang password ay dapat may hindi bababa sa 8 character.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Dapat may kahit isang uppercase na letra ang password.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Dapat may kahit isang numero ang password.';
    } elseif ($password !== $confirm) {
        $error = 'Hindi magkatugma ang mga password.';
    } else {
        $pdo  = getDB();
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $pdo->prepare(
            "UPDATE users
             SET password = ?, otp_code = NULL, otp_expires_at = NULL,
                 failed_attempts = 0, locked_until = NULL, updated_at = NOW()
             WHERE email = ?"
        )->execute([$hash, $email]);

        unset($_SESSION['reset_email'], $_SESSION['otp_verified'], $_SESSION['reset_step']);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --primary: #142c5b;
    --primary-alt: #0a1f44;
  }

  html, body { height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; }

  body {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    background: url('assets/image/login.png') center/cover fixed;
    background-color: #0f172a;
    position: relative;
  }

  body::before {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, .72);
    backdrop-filter: blur(2px);
  }

  .page-shell {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 480px;
    padding: 1.25rem;
  }

  .auth-card {
    background: rgba(255, 255, 255, .98);
    border-radius: 24px;
    padding: 2.2rem 2.5rem 2rem;
    box-shadow: 0 12px 40px rgba(15, 23, 42, .18);
    border: 1px solid rgba(15, 23, 42, .1);
  }

  .brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    margin-bottom: 1.25rem;
  }
  .brand img { width: 48px; height: 48px; object-fit: contain; }
  .brand-name { font-size: 1.4rem; font-weight: 900; color: #111827; }

  h1 {
    font-size: 2rem;
    font-weight: 900;
    text-align: center;
    color: #111827;
    margin-bottom: .4rem;
  }

  .sub {
    font-size: .95rem;
    color: #475569;
    text-align: center;
    line-height: 1.7;
    margin-bottom: 1.75rem;
  }

  .alert {
    border-radius: 14px;
    padding: .9rem 1rem;
    font-size: .88rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: 1.25rem;
  }
  .alert-err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
  .alert-ok  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }

  /* Success state */
  .success-box { text-align: center; padding: 1rem 0; }
  .success-icon {
    width: 72px; height: 72px;
    background: linear-gradient(135deg, var(--primary-alt), var(--primary));
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    box-shadow: 0 8px 25px rgba(10, 31, 68, .25);
  }
  .success-icon i { font-size: 2rem; color: #fff; }
  .success-box h1 { margin-bottom: .5rem; }
  .success-box p  { color: #475569; font-size: .95rem; line-height: 1.7; margin-bottom: 1.5rem; }
  .btn-login {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 1rem 2.5rem;
    background: linear-gradient(135deg, var(--primary-alt), var(--primary));
    color: #fff;
    font-family: inherit;
    font-size: 1rem;
    font-weight: 800;
    border: none;
    border-radius: 16px;
    cursor: pointer;
    text-decoration: none;
    transition: transform .2s, box-shadow .2s;
    box-shadow: 0 8px 25px rgba(10, 31, 68, .25);
  }
  .btn-login:hover { transform: translateY(-1px); box-shadow: 0 12px 32px rgba(10, 31, 68, .3); }

  .field { margin-bottom: 1.25rem; }

  label {
    display: block;
    font-size: .82rem;
    font-weight: 700;
    color: #374151;
    margin-bottom: .45rem;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .pw-wrap { position: relative; }

  .pw-wrap i.icon {
    position: absolute;
    left: 1rem; top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 1rem;
    pointer-events: none;
  }

  .pw-wrap input {
    width: 100%;
    padding: .8rem 2.8rem .8rem 2.6rem;
    font-size: .95rem;
    font-family: inherit;
    border: 1.5px solid #e5e7eb;
    border-radius: 14px;
    background: #f8fafc;
    color: #111827;
    outline: none;
    transition: border-color .2s, background .2s, box-shadow .2s;
  }
  .pw-wrap input:focus {
    border-color: var(--primary);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(20, 44, 91, .1);
  }

  .toggle-pw {
    position: absolute;
    right: .85rem; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    cursor: pointer; color: #9ca3af;
    font-size: 1rem; padding: 0; line-height: 1;
    transition: color .2s;
  }
  .toggle-pw:hover { color: var(--primary); }

  /* Strength meter */
  .strength-bar { display: flex; gap: 4px; margin-top: 8px; }
  .strength-bar span {
    flex: 1; height: 4px; border-radius: 4px;
    background: #e5e7eb; transition: background .3s;
  }
  .strength-label { font-size: .75rem; color: #9ca3af; margin-top: 4px; height: 16px; }

  /* Rules checklist */
  .rules { list-style: none; margin-top: 10px; }
  .rules li {
    font-size: .8rem; color: #9ca3af;
    display: flex; align-items: center; gap: 6px;
    margin-bottom: 4px; transition: color .2s;
  }
  .rules li.pass { color: #15803d; }
  .rules li i { font-size: .8rem; }

  .btn-submit {
    width: 100%;
    padding: 1.1rem;
    border: none;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--primary-alt), var(--primary));
    color: #fff;
    font-size: 1.05rem;
    font-weight: 800;
    font-family: inherit;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
    box-shadow: 0 8px 25px rgba(10, 31, 68, .25);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: .5rem;
  }
  .btn-submit:hover  { transform: translateY(-1px); box-shadow: 0 12px 32px rgba(10, 31, 68, .3); }
  .btn-submit:active { transform: scale(.98); }
  .btn-submit:disabled {
    background: linear-gradient(135deg, #94a3b8, #64748b);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
  }

  .hint {
    text-align: center;
    font-size: .88rem;
    color: #64748b;
    margin-top: 1rem;
  }
  .hint a {
    color: var(--primary);
    font-weight: 700;
    text-decoration: none;
  }
  .hint a:hover { text-decoration: underline; }

  .footer-note {
    text-align: center;
    font-size: .82rem;
    color: #94a3b8;
    margin-top: 1.5rem;
  }

  @media (max-width: 480px) {
    .page-shell { padding: 1rem; }
    .auth-card  { padding: 1.8rem 1.4rem; }
    h1          { font-size: 1.7rem; }
  }
</style>
</head>
<body>

<div class="page-shell">
  <div class="auth-card">

    <div class="brand">
      <img src="assets/image/logo.png" alt="MyFitCal">
      <span class="brand-name">MYFITCAL</span>
    </div>

    <?php if ($success): ?>

      <div class="success-box">
        <div class="success-icon">
          <i class="bi bi-shield-check"></i>
        </div>
        <h1>Password Reset!</h1>
        <p>Your password has been successfully updated.<br>You can now login with your new password.</p>
        <a class="btn-login" href="login.php">
          <i class="bi bi-box-arrow-in-right"></i> Login →
        </a>
      </div>

    <?php else: ?>

      <h1>New Password</h1>
      <p class="sub">Create a new password<br>for your account.</p>

      <?php if ($error): ?>
      <div class="alert alert-err">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="" id="resetForm">
        <div class="field">
          <label for="password">New Password</label>
          <div class="pw-wrap">
            <i class="bi bi-lock icon"></i>
            <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="Minimum 8 characters">
            <button type="button" class="toggle-pw" onclick="togglePw('password')" aria-label="Show/Hide">
              <i class="bi bi-eye" id="eye-pw"></i>
            </button>
          </div>
          <div class="strength-bar">
            <span id="s1"></span><span id="s2"></span>
            <span id="s3"></span><span id="s4"></span>
          </div>
          <div class="strength-label" id="strengthLabel"></div>
          <ul class="rules">
            <li id="r-len">  <i class="bi bi-circle"></i> Minimum 8 characters</li>
            <li id="r-upper"><i class="bi bi-circle"></i> Has uppercase letter</li>
            <li id="r-num">  <i class="bi bi-circle"></i> Has number</li>
          </ul>
        </div>

        <div class="field">
          <label for="password_confirm">Confirm Password</label>
          <div class="pw-wrap">
            <i class="bi bi-lock-fill icon"></i>
            <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" placeholder="Re-enter your password">
            <button type="button" class="toggle-pw" onclick="togglePw('password_confirm')" aria-label="Show/Hide">
              <i class="bi bi-eye" id="eye-cf"></i>
            </button>
          </div>
          <div class="strength-label" id="matchLabel" style="margin-top:6px;"></div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn" disabled>
          <i class="bi bi-shield-lock-fill"></i> Reset Password →
        </button>
      </form>

      <p class="hint">
        <a href="login.php"><i class="bi bi-arrow-left"></i> Return to Login</a>
      </p>

      <div class="footer-note">
        <strong>MyFitCal</strong> &middot; Secure password reset.
      </div>

    <?php endif; ?>

  </div>
</div>

<script>
function togglePw(id) {
  const inp   = document.getElementById(id);
  const eyeId = id === 'password' ? 'eye-pw' : 'eye-cf';
  const eye   = document.getElementById(eyeId);
  const show  = inp.type === 'password';
  inp.type       = show ? 'text' : 'password';
  eye.className  = show ? 'bi bi-eye-slash' : 'bi bi-eye';
}

const pwInp  = document.getElementById('password');
const cfInp  = document.getElementById('password_confirm');
const btn    = document.getElementById('submitBtn');
const segs   = ['s1','s2','s3','s4'].map(id => document.getElementById(id));
const strLbl = document.getElementById('strengthLabel');
const mchLbl = document.getElementById('matchLabel');
const colors = ['#ef4444','#f97316','#eab308','#15803d'];
const labels = ['Napakahina','Mahina','Katamtaman','Malakas'];
const rules  = { len: false, upper: false, num: false };

function setRule(id, pass) {
  rules[id] = pass;
  const li = document.getElementById('r-' + id);
  li.classList.toggle('pass', pass);
  li.querySelector('i').className = pass ? 'bi bi-check-circle-fill' : 'bi bi-circle';
}

function updateStrength(pw) {
  setRule('len',   pw.length >= 8);
  setRule('upper', /[A-Z]/.test(pw));
  setRule('num',   /[0-9]/.test(pw));

  let score = 0;
  if (pw.length >= 8)  score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score = Math.min(score + 1, 4);
  score = Math.min(score, 4);

  segs.forEach((s, i) => {
    s.style.background = i < score ? colors[score - 1] : '#e5e7eb';
  });
  strLbl.textContent = score > 0 ? labels[score - 1] : '';
  strLbl.style.color = score > 0 ? colors[score - 1] : '#9ca3af';
}

function checkMatch() {
  const pw = pwInp.value, cf = cfInp.value;
  if (!cf) { mchLbl.textContent = ''; return false; }
  const ok = pw === cf;
  mchLbl.textContent = ok ? '✓ Magkatugma' : '✗ Hindi magkatugma';
  mchLbl.style.color = ok ? '#15803d' : '#b91c1c';
  return ok;
}

function validate() {
  const allRules = Object.values(rules).every(Boolean);
  btn.disabled = !(allRules && checkMatch());
}

pwInp.addEventListener('input', () => { updateStrength(pwInp.value); validate(); });
cfInp.addEventListener('input', validate);
</script>
</body>
</html>