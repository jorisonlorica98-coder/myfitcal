<?php
session_start();
require_once __DIR__ . '/config/db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Pakilagay ang isang valid na email address.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, name, locked_until FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $mins  = ceil((strtotime($user['locked_until']) - time()) / 60);
                $error = "Pansamantala kang na-lock. Subukan muli pagkatapos ng {$mins} minuto.";
            } else {
                $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $pdo->prepare(
                    "UPDATE users
                     SET otp_code = ?, otp_expires_at = ?, failed_attempts = 0, locked_until = NULL
                     WHERE id = ?"
                )->execute([$otp, $expires, $user['id']]);

                $name   = htmlspecialchars($user['name']);
                $digits = str_split($otp);
                $html   = "
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
                            <p style='margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:0.15em;color:#93c5fd;text-transform:uppercase;'>Password Reset</p>
                            <h1 style='margin:0;font-size:28px;font-weight:900;color:#ffffff;'>Hi, {$name}!</h1>
                            <p style='margin:10px 0 0;font-size:15px;color:#94a3b8;line-height:1.6;'>Here is your one-time password reset code<br>for <strong style='color:#ffffff;'>MyFitCal</strong>.</p>
                          </td>
                        </tr>

                        <!-- BODY -->
                        <tr>
                          <td style='background:#1e293b;padding:36px 40px;text-align:center;'>
                            <p style='margin:0 0 20px;font-size:14px;color:#94a3b8;line-height:1.7;'>
                              Use this 6-digit code to reset your password.<br>
                              <strong style='color:#f87171;'>Do not share this code with anyone.</strong>
                            </p>

                            <!-- OTP DIGIT BOXES -->
                            <table cellpadding='0' cellspacing='0' style='margin:0 auto 24px;border-collapse:separate;border-spacing:8px 0;'>
                              <tr>
                                <td width='52' height='60' align='center' valign='middle'
                                    style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[0]}</td>
                                <td width='52' height='60' align='center' valign='middle'
                                    style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[1]}</td>
                                <td width='52' height='60' align='center' valign='middle'
                                    style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[2]}</td>
                                <td width='52' height='60' align='center' valign='middle'
                                    style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[3]}</td>
                                <td width='52' height='60' align='center' valign='middle'
                                    style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[4]}</td>
                                <td width='52' height='60' align='center' valign='middle'
                                    style='width:52px;height:60px;background:#0f172a;border:2px solid #475569;border-radius:12px;font-size:30px;font-weight:900;color:#ffffff;font-family:Arial,sans-serif;text-align:center;vertical-align:middle;padding:0;mso-padding-alt:0;'>{$digits[5]}</td>
                              </tr>
                            </table>

                            <!-- EXPIRES BADGE -->
                            <div style='display:inline-block;background:#292524;border:1.5px solid #78350f;border-radius:999px;padding:8px 20px;font-size:13px;font-weight:700;color:#fb923c;margin-bottom:28px;'>
                              &#x23F0; Expires in 15 minutes
                            </div>

                            <p style='margin:0;font-size:13px;color:#64748b;line-height:1.7;'>
                              If you did not request a password reset, please<br>
                              ignore this email. Your account remains secure.
                            </p>
                          </td>
                        </tr>

                        <!-- FOOTER -->
                        <tr>
                          <td style='background:#0f172a;padding:20px 40px;text-align:center;border-top:1px solid #1e293b;'>
                            <p style='margin:0;font-size:12px;color:#475569;'>
                              &copy; <?= date('Y') ?> <strong style='color:#94a3b8;'>MyFitCal</strong> &nbsp;&middot;&nbsp; One-time code, do not share.
                            </p>
                          </td>
                        </tr>

                      </table>
                    </td></tr>
                  </table>
                </body>
                </html>";

                $sent = sendMail($email, $user['name'], 'MyFitCal — Password Reset OTP', $html);

                if ($sent === true) {
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_step']  = 'verify';
                    header('Location: verify-otp.php?mode=reset');
                    exit;
                } else {
                    error_log('Mailer error: ' . $sent);
                    $error = 'Hindi mapadala ang email. Subukan muli.';
                }
            }
        } else {
            $success = 'Kung naka-register ang email na iyon, makakatanggap ka ng OTP.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — MyFitCal</title>
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
  .brand img {
    width: 48px;
    height: 48px;
    object-fit: contain;
  }
  .brand-name {
    font-size: 1.4rem;
    font-weight: 900;
    color: #111827;
  }

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
  .alert-err  { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
  .alert-ok   { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }

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

  .input-wrap { position: relative; }

  .input-wrap i.icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 1rem;
    pointer-events: none;
  }

  input[type="email"] {
    width: 100%;
    padding: .8rem 1rem .8rem 2.6rem;
    font-size: .95rem;
    font-family: inherit;
    border: 1.5px solid #e5e7eb;
    border-radius: 14px;
    background: #f8fafc;
    color: #111827;
    outline: none;
    transition: border-color .2s, background .2s, box-shadow .2s;
  }
  input[type="email"]:focus {
    border-color: var(--primary);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(20, 44, 91, .1);
  }

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
    margin-top: .25rem;
  }
  .btn-submit:hover  { transform: translateY(-1px); box-shadow: 0 12px 32px rgba(10, 31, 68, .3); }
  .btn-submit:active { transform: scale(.98); }

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
    .page-shell  { padding: 1rem; }
    .auth-card   { padding: 1.8rem 1.4rem; }
    h1           { font-size: 1.7rem; }
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

    <h1>Forgot password?</h1>
    <p class="sub">
      Enter your email and we'll send you a<br>
      6-digit OTP to reset your password.
    </p>

    <?php if ($error): ?>
    <div class="alert alert-err">
      <i class="bi bi-exclamation-circle-fill"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-ok">
      <i class="bi bi-check-circle-fill"></i>
      <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="field">
        <label for="email">Email Address</label>
        <div class="input-wrap">
          <i class="bi bi-envelope icon"></i>
          <input
            type="email"
            id="email"
            name="email"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            placeholder="Enter your email"
            required
            autofocus>
        </div>
      </div>

      <button type="submit" class="btn-submit">
        <i class="bi bi-send-fill"></i>
        Send OTP
        <span style="margin-left:4px">→</span>
      </button>
    </form>

    <p class="hint">
      <a href="login.php"><i class="bi bi-arrow-left"></i> Return to Login</a>
    </p>

    <div class="footer-note">
      <strong>MyFitCal</strong> &middot; Secure password reset via one-time code.
    </div>

  </div>
</div>

</body>
</html>