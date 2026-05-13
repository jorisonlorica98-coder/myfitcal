<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Kung walang pending login AT walang reset flow, ibalik sa login
if (empty($_SESSION['pending_user_id']) && empty($_SESSION['reset_email'])) {
    header('Location: /myfitcal_system/login.php');
    exit;
}

$error     = '';
$otp_sent  = $_SESSION['pending_user_email'] ?? $_SESSION['reset_email'] ?? 'your email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['otp'] ?? '');
    $db      = getDB();

    // ── RESET PASSWORD FLOW ──────────────────────────────────────
    if (!empty($_SESSION['reset_step']) && $_SESSION['reset_step'] === 'verify') {
        $email = $_SESSION['reset_email'];

        $stmt = $db->prepare("SELECT id, otp_code, otp_expires_at FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || $user['otp_code'] !== $entered) {
            $error = 'Invalid OTP code. Please try again.';
        } elseif (new DateTime() > new DateTime($user['otp_expires_at'])) {
            $error = 'OTP has expired. Please request a new one.';
        } else {
            $db->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?")
               ->execute([$user['id']]);

            $_SESSION['otp_verified'] = true;

            header('Location: /myfitcal_system/reset_password.php');
            exit;
        }

    // ── NORMAL LOGIN FLOW ─────────────────────────────────────────
    } else {
        $userId = $_SESSION['pending_user_id'];

        $stmt = $db->prepare("SELECT otp_code, otp_expires_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || $user['otp_code'] !== $entered) {
            $error = 'Invalid OTP code. Please try again.';
        } elseif (new DateTime() > new DateTime($user['otp_expires_at'])) {
            $error = 'OTP has expired. Please go back and login again.';
        } else {
            $db->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?")
               ->execute([$userId]);

            $stmt2 = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
            $stmt2->execute([$userId]);
            $fullUser = $stmt2->fetch();

            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['pending_user_email']);

            setUserSession($fullUser);

            header('Location: /myfitcal_system/setup/step1-profile.php');
            exit;
        }
    }
}

// ── RESEND OTP ────────────────────────────────────────────────────
if (isset($_GET['resend'])) {
    $db = getDB();

    // Helper: build the styled HTML email
    function buildOtpEmail(string $name, string $otp, string $type = 'reset'): string {
        $digits  = str_split($otp);
        $expires = $type === 'reset' ? '15 minutes' : '5 minutes';
        $subject = $type === 'reset' ? 'Password Reset' : 'Login Verification';
        $intro   = $type === 'reset'
            ? 'Here is your one-time password reset code for <strong style="color:#ffffff;">MyFitCal</strong>.'
            : 'Here is your one-time login code for <strong style="color:#ffffff;">MyFitCal</strong>.';

        $year = date('Y');
        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
        <body style='margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;'>
          <table width='100%' cellpadding='0' cellspacing='0' style='background:#f1f5f9;padding:32px 0;'>
            <tr><td align='center'>
              <table width='520' cellpadding='0' cellspacing='0' style='max-width:520px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(15,23,42,0.15);'>

                <!-- HEADER -->
                <tr>
                  <td style='background:linear-gradient(135deg,#0a1f44 0%,#142c5b 100%);padding:36px 40px 32px;text-align:center;'>
                    <p style='margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:0.15em;color:#93c5fd;text-transform:uppercase;'>{$subject}</p>
                    <h1 style='margin:0;font-size:28px;font-weight:900;color:#ffffff;'>Hi, {$name}!</h1>
                    <p style='margin:10px 0 0;font-size:15px;color:#94a3b8;line-height:1.6;'>{$intro}</p>
                  </td>
                </tr>

                <!-- BODY -->
                <tr>
                  <td style='background:#1e293b;padding:36px 40px;text-align:center;'>
                    <p style='margin:0 0 20px;font-size:14px;color:#94a3b8;line-height:1.7;'>
                      Use this 6-digit code to complete your request.<br>
                      <strong style='color:#f87171;'>Do not share this code with anyone.</strong>
                    </p>

                    <!-- OTP DIGIT BOXES -->
                    <table cellpadding='0' cellspacing='0' style='margin:0 auto 24px;border-collapse:separate;border-spacing:8px 0;'>
                      <tr>
                        <td width='52' height='60' align='center' valign='middle' style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[0]}</td>
                        <td width='52' height='60' align='center' valign='middle' style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[1]}</td>
                        <td width='52' height='60' align='center' valign='middle' style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[2]}</td>
                        <td width='52' height='60' align='center' valign='middle' style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[3]}</td>
                        <td width='52' height='60' align='center' valign='middle' style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[4]}</td>
                        <td width='52' height='60' align='center' valign='middle' style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[5]}</td>
                      </tr>
                    </table>

                    <!-- EXPIRES BADGE -->
                    <div style='display:inline-block;background:#292524;border:1.5px solid #78350f;border-radius:999px;padding:8px 20px;font-size:13px;font-weight:700;color:#fb923c;margin-bottom:28px;'>
                      &#x23F0; Expires in {$expires}
                    </div>

                    <p style='margin:0;font-size:13px;color:#64748b;line-height:1.7;'>
                      If you did not attempt this, please ignore this email.<br>
                      Your account remains secure.
                    </p>
                  </td>
                </tr>

                <!-- FOOTER -->
                <tr>
                  <td style='background:#0f172a;padding:20px 40px;text-align:center;border-top:1px solid #1e293b;'>
                    <p style='margin:0;font-size:12px;color:#475569;'>
                      &copy; {$year} <strong style='color:#94a3b8;'>MyFitCal</strong> &nbsp;&middot;&nbsp; One-time code, do not share.
                    </p>
                  </td>
                </tr>

              </table>
            </td></tr>
          </table>
        </body>
        </html>";
    }

    // ── RESET FLOW RESEND ────────────────────────────────────────
    if (!empty($_SESSION['reset_step']) && $_SESSION['reset_step'] === 'verify') {
        $email = $_SESSION['reset_email'];

        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if ($u) {
            $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")
               ->execute([$otp, $expiry, $u['id']]);

            $html = buildOtpEmail(htmlspecialchars($u['name']), $otp, 'reset');
            sendMail($email, $u['name'], 'MyFitCal — Password Reset OTP', $html);
        }

        header('Location: /myfitcal_system/verify-otp.php?resent=1');
        exit;

    // ── NORMAL LOGIN RESEND ──────────────────────────────────────
    } else {
        $userId = $_SESSION['pending_user_id'];

        $stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();

        if ($u) {
            $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")
               ->execute([$otp, $expiry, $userId]);

            $html = buildOtpEmail(htmlspecialchars($u['name']), $otp, 'login');
            sendOTPEmail($u['email'], $u['name'], $otp, $html);
        }

        header('Location: /myfitcal_system/verify-otp.php?resent=1');
        exit;
    }
}

$resent = isset($_GET['resent']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify OTP — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--primary:#142c5b;--primary-alt:#0a1f44;}
html,body{height:100%;font-family:'Plus Jakarta Sans',sans-serif;}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;
     background:url('assets/image/login.png') center/cover fixed;background-color:#0f172a;position:relative;}
body::before{content:'';position:absolute;inset:0;background:rgba(15,23,42,.72);backdrop-filter:blur(2px);}

.page-shell{position:relative;z-index:1;width:100%;max-width:480px;padding:1.25rem;}
.auth-card{background:rgba(255,255,255,.98);border-radius:24px;padding:2.2rem 2.5rem 2rem;
           box-shadow:0 12px 40px rgba(15,23,42,.18);border:1px solid rgba(15,23,42,.1);}

.brand{display:flex;align-items:center;justify-content:center;gap:.75rem;margin-bottom:1.25rem;}
.brand img{width:48px;height:48px;object-fit:contain;}
.brand-name{font-size:1.4rem;font-weight:900;color:#111827;}

h1{font-size:2rem;font-weight:900;text-align:center;color:#111827;margin-bottom:.4rem;}
.sub{font-size:.95rem;color:#475569;text-align:center;line-height:1.7;margin-bottom:1.5rem;}
.sub strong{color:#142c5b;}

.alert{border-radius:14px;padding:.9rem 1rem;font-size:.88rem;font-weight:600;
       display:flex;align-items:center;gap:.5rem;margin-bottom:1.25rem;}
.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
.alert-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;}

.otp-row{display:flex;gap:10px;justify-content:center;margin-bottom:1.5rem;}
.otp-row input{width:52px;height:62px;text-align:center;font-size:26px;font-weight:900;
               border:1.5px solid #e5e7eb;border-radius:14px;background:#f8fafc;
               color:#142c5b;outline:none;transition:border .2s,background .2s;
               font-family:'Plus Jakarta Sans',sans-serif;}
.otp-row input:focus{border-color:#142c5b;background:#fff;box-shadow:0 0 0 3px rgba(20,44,91,.1);}
.otp-row input.filled{border-color:#142c5b;background:#eef2ff;}

.btn-submit{width:100%;padding:1.1rem;border:none;border-radius:16px;
            background:linear-gradient(135deg,var(--primary-alt),var(--primary));
            color:#fff;font-size:1.05rem;font-weight:800;cursor:pointer;
            transition:transform .2s,box-shadow .2s;
            box-shadow:0 8px 25px rgba(10,31,68,.25);}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 12px 32px rgba(10,31,68,.3);}

.timer-wrap{text-align:center;margin-top:1rem;}
.timer-bar-track{height:4px;background:#fee2e2;border-radius:999px;overflow:hidden;margin-bottom:6px;}
.timer-bar-fill{height:100%;background:#c2410c;border-radius:999px;transition:width 1s linear;}
.timer-text{font-size:.82rem;color:#c2410c;font-weight:700;}

.hint{text-align:center;font-size:.88rem;color:#64748b;margin-top:1rem;}
.hint a{color:#142c5b;font-weight:700;cursor:pointer;text-decoration:none;}
.hint a:hover{text-decoration:underline;}

.footer-note{text-align:center;font-size:.82rem;color:#94a3b8;margin-top:1.5rem;}

@media(max-width:480px){
  .page-shell{padding:1rem;}
  .auth-card{padding:1.8rem 1.4rem;}
  .otp-row input{width:44px;height:54px;font-size:22px;}
  h1{font-size:1.7rem;}
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

    <h1>Check your email</h1>
    <p class="sub">
      We sent a 6-digit code to<br>
      <strong><?= htmlspecialchars($otp_sent) ?></strong>
    </p>

    <?php if ($error): ?>
    <div class="alert alert-err">
      <i class="bi bi-exclamation-circle-fill"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($resent): ?>
    <div class="alert alert-ok">
      <i class="bi bi-check-circle-fill"></i>
      A new code has been sent to your email.
    </div>
    <?php endif; ?>

    <form method="POST" onsubmit="collectOTP()">
      <div class="otp-row">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <input type="text" maxlength="1" id="o<?= $i ?>"
               inputmode="numeric" pattern="[0-9]"
               oninput="otpInput(this, <?= $i ?>)"
               onkeydown="otpBack(event, <?= $i ?>)"
               onpaste="otpPaste(event)">
        <?php endfor; ?>
      </div>
      <input type="hidden" name="otp" id="otpHidden">
      <button type="submit" class="btn-submit">
        Verify &amp; Sign In <span style="margin-left:4px">→</span>
      </button>
    </form>

    <div class="timer-wrap">
      <div class="timer-bar-track">
        <div class="timer-bar-fill" id="timerBar"></div>
      </div>
      <div class="timer-text">Code expires in <span id="timerNum">5:00</span></div>
    </div>

    <p class="hint">
      Didn't receive it?
      <a href="verify-otp.php?resend=1">Resend code</a>
      &nbsp;&middot;&nbsp;
      <a href="login.php">Back to login</a>
    </p>

    <div class="footer-note">
      <strong>MyFitCal</strong> &middot; One-time code, valid for 5 minutes only.
    </div>

  </div>
</div>

<script>
function otpInput(el, idx) {
  el.value = el.value.replace(/\D/g, '');
  el.classList.toggle('filled', el.value !== '');
  if (el.value && idx < 5) document.getElementById('o' + (idx + 1)).focus();
}

function otpBack(e, idx) {
  if (e.key === 'Backspace') {
    var el = document.getElementById('o' + idx);
    if (!el.value && idx > 0) {
      document.getElementById('o' + (idx - 1)).focus();
    }
    el.classList.remove('filled');
  }
}

function otpPaste(e) {
  e.preventDefault();
  var paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
  for (var i = 0; i < paste.length; i++) {
    var el = document.getElementById('o' + i);
    if (el) { el.value = paste[i]; el.classList.add('filled'); }
  }
  var last = document.getElementById('o' + (paste.length - 1));
  if (last) last.focus();
}

function collectOTP() {
  var code = '';
  for (var i = 0; i < 6; i++) code += document.getElementById('o' + i).value;
  document.getElementById('otpHidden').value = code;
}

document.getElementById('o0').focus();

var total = 300, secs = 300;
var bar   = document.getElementById('timerBar');
var num   = document.getElementById('timerNum');
bar.style.width = '100%';

var countdown = setInterval(function() {
  secs--;
  var m = Math.floor(secs / 60), s = secs % 60;
  num.textContent = m + ':' + (s < 10 ? '0' : '') + s;
  bar.style.width  = ((secs / total) * 100) + '%';
  if (secs <= 0) {
    clearInterval(countdown);
    num.textContent  = 'Expired';
    bar.style.width  = '0%';
    bar.style.background = '#e5e7eb';
  }
}, 1000);
</script>
</body>
</html>