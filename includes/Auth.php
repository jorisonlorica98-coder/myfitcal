<?php
// ============================================================
//  MyFitCal — Auth Helper
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Session Timeout — 5 minutes ──────────────────────────────
define('SESSION_TIMEOUT', 1000000); // 5 minutes in seconds

function isLoggedIn(): bool {
    if (empty($_SESSION['user_id'])) return false;

    // Check if session has expired
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            return false;
        }
    }

    // Update last activity
    $_SESSION['last_activity'] = time();
    return true;
}

function isAdmin(): bool {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /myfitcal_system/login.php');
        exit;
    }
    // Auto-check workout reminder on every page load
    autoCheckReminder();
}

function requireAdmin(): void {
    if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: /myfitcal_system/admin-login.php');
        exit;
    }
}

function setUserSession(array $user): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'] ?? null;
    $_SESSION['name']          = $user['name'] ?? '';
    $_SESSION['email']         = $user['email'] ?? '';
    $_SESSION['user_role']     = $user['role'] ?? 'user';
    $_SESSION['last_activity'] = time(); // Start the timer
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ── Auto Reminder Checker ─────────────────────────────────────
function autoCheckReminder(): void {
    // Check once per minute max to avoid spam
    $last_check = $_SESSION['last_reminder_check'] ?? 0;
    if (time() - $last_check < 60) return; // 1 minute cooldown

    $_SESSION['last_reminder_check'] = time();

    try {
        require_once dirname(__DIR__) . '/config/db.php';
        $db      = getDB();
        $user_id = $_SESSION['user_id'];

        // Get reminder
        $rq = $db->prepare("
            SELECT wr.reminder_time, wr.is_active,
                   u.name, u.email, u.gender,
                   ug.goal_type, uf.fitness_level,
                   (SELECT COUNT(DISTINCT day_number) FROM user_workout_progress
                    WHERE user_id = u.id AND completed = 1) as days_done
            FROM workout_reminders wr
            JOIN users u ON u.id = wr.user_id
            LEFT JOIN user_goals ug ON ug.user_id = u.id
            LEFT JOIN user_fitness uf ON uf.user_id = u.id
            WHERE wr.user_id = ? AND wr.is_active = 1
            LIMIT 1
        ");
        $rq->execute([$user_id]);
        $rem = $rq->fetch();

        if (!$rem) return;

        // Check if time has passed reminder
        $reminder_at = strtotime(date('Y-m-d') . ' ' . $rem['reminder_time']);
        if ($reminder_at === false || time() < $reminder_at + 300) return;

        // Check if already sent today
        $sq = $db->prepare("
            SELECT id FROM email_notifications
            WHERE user_id = ? AND type = 'workout_reminder'
            AND DATE(sent_at) = CURDATE() AND status = 'sent'
            LIMIT 1
        ");
        $sq->execute([$user_id]);
        if ($sq->fetch()) return;

        // Check if workout done today
        $wq = $db->prepare("
            SELECT id FROM user_workout_progress
            WHERE user_id = ? AND completed = 1
            AND DATE(completed_at) = CURDATE()
            LIMIT 1
        ");
        $wq->execute([$user_id]);
        if ($wq->fetch()) return;

        // All checks passed — SEND EMAIL
        $fname     = explode(' ', $rem['name'])[0];
        $done      = (int)$rem['days_done'];
        $day_next  = $done + 1;
        $remaining = 30 - $done;
        $pct       = $done > 0 ? round($done / 30 * 100) : 0;
        $bar_w     = max(4, $pct);
        $is_female = strtolower($rem['gender'] ?? '') === 'female';
        $acc_color = $is_female ? '#be185d' : '#16a34a';
        $goal_map  = ['lose'=>'Weight Loss','maintain'=>'Maintenance','gain'=>'Weight Gain','muscle'=>'Muscle Gain'];
        $goal_lbl  = $goal_map[$rem['goal_type'] ?? ''] ?? 'Fitness';
        $lvl       = ucfirst($rem['fitness_level'] ?? 'Beginner');
        $rtime     = $rem['reminder_time'];

        $subject = "⏰ MyFitCal — You missed your {$rtime} workout, {$fname}!";
        $html    = buildAutoReminderHTML($fname, $day_next, $done, $remaining, $pct, $bar_w, $goal_lbl, $lvl, $acc_color, $rtime);

        $result = sendMail($rem['email'], $rem['name'], $subject, $html);

        $status = ($result === true) ? 'sent' : 'failed';
        $db->prepare("
            INSERT INTO email_notifications (user_id, type, subject, body, sent_to, status, sent_at)
            VALUES (?, 'workout_reminder', ?, ?, ?, ?, NOW())
        ")->execute([$user_id, $subject, $html, $rem['email'], $status]);

    } catch (Exception $e) {
        error_log('MyFitCal reminder check error: ' . $e->getMessage());
    }
}

function buildAutoReminderHTML($fname, $day_next, $done, $remaining, $pct, $bar_w, $goal_lbl, $lvl, $acc_color, $reminder_time): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">
  <tr><td style="background:linear-gradient(135deg,#0a1628,#0d2137,#0a2e1f);border-radius:20px 20px 0 0;padding:32px;text-align:center;">
    <div style="display:inline-block;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);border-radius:999px;padding:6px 18px;font-size:11px;font-weight:700;color:rgba(255,255,255,.6);letter-spacing:1px;text-transform:uppercase;margin-bottom:16px;">⏰ Missed Workout Reminder</div>
    <h1 style="margin:0;font-size:26px;color:#fff;font-weight:900;">Hey {$fname}!</h1>
    <p style="margin:10px 0 0;font-size:14px;color:rgba(255,255,255,.5);">Your {$reminder_time} workout reminder passed — don't skip today!</p>
  </td></tr>
  <tr><td style="background:#fff;padding:32px;">
    <div style="background:#fff7ed;border:1.5px solid #fed7aa;border-radius:14px;padding:14px 18px;margin-bottom:24px;">
      <div style="font-size:13px;font-weight:800;color:#c2410c;">⚠️ Don't skip today's workout!</div>
      <div style="font-size:12px;color:#ea580c;margin-top:4px;">You set a reminder at {$reminder_time} but haven't started yet. It's not too late!</div>
    </div>
    <div style="text-align:center;margin-bottom:24px;">
      <div style="display:inline-block;background:{$acc_color}18;border:2px solid {$acc_color}44;border-radius:16px;padding:16px 36px;">
        <div style="font-size:48px;font-weight:900;color:{$acc_color};line-height:1;">Day {$day_next}</div>
        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-top:6px;">of 30 Days</div>
      </div>
    </div>
    <p style="margin:0 0 8px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">Your Progress</p>
    <div style="background:#f1f5f9;border-radius:999px;height:10px;overflow:hidden;margin-bottom:6px;">
      <div style="background:{$acc_color};height:100%;width:{$bar_w}%;border-radius:999px;"></div>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:24px;">
      <span>{$done} / 30 days done</span><span>{$pct}% complete</span>
    </div>
    <table width="100%" cellpadding="4" cellspacing="0" style="margin-bottom:24px;">
      <tr>
        <td width="33%"><div style="background:#f8fafc;border-radius:12px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:900;color:#0f172a;">{$done}</div><div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-top:2px;">Done</div></div></td>
        <td width="33%"><div style="background:#f8fafc;border-radius:12px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:900;color:{$acc_color};">{$remaining}</div><div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-top:2px;">Remaining</div></div></td>
        <td width="33%"><div style="background:#f8fafc;border-radius:12px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:900;color:#0f172a;">30</div><div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-top:2px;">Total</div></div></td>
      </tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:14px;margin-bottom:24px;">
      <tr>
        <td style="padding:14px 20px;"><div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:3px;">Goal</div><div style="font-size:14px;font-weight:800;color:#0f172a;">{$goal_lbl}</div></td>
        <td style="padding:14px 20px;border-left:1px solid #e2e8f0;"><div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:3px;">Level</div><div style="font-size:14px;font-weight:800;color:#0f172a;">{$lvl}</div></td>
      </tr>
    </table>
    <div style="text-align:center;margin-bottom:20px;">
      <a href="http://localhost/myfitcal_system/user/workout.php?day={$day_next}" style="display:inline-block;background:{$acc_color};color:#fff;text-decoration:none;font-size:15px;font-weight:800;padding:14px 40px;border-radius:12px;">
        Start Day {$day_next} Workout Now →
      </a>
    </div>
    <p style="margin:0;font-size:13px;color:#94a3b8;text-align:center;line-height:1.7;">
      Even a late workout is better than none!<br>
      <strong style="color:#0f172a;">MyFitCal</strong> believes in you 💪
    </p>
  </td></tr>
  <tr><td style="background:#f8fafc;border-radius:0 0 20px 20px;padding:18px;text-align:center;">
    <p style="margin:0;font-size:11px;color:#94a3b8;">MyFitCal · You set a reminder at {$reminder_time}</p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

// ── OTP Email Sender ──────────────────────────────────────────
function sendOTPEmail(string $email, string $name, string $otp): void {
    try {
        require_once dirname(__DIR__) . '/PHPMailer/src/Exception.php';
        require_once dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php';
        require_once dirname(__DIR__) . '/PHPMailer/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jorisonlorica98@gmail.com';   // <-- palitan ng email mo
        $mail->Password   = 'mget izsf jfio vvuc';     // <-- palitan ng Gmail App Password mo
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('no-reply@myfitcal.com', 'MyFitCal');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Your MyFitCal Login Code';
        $mail->Body    = buildOTPEmailHTML($name, $otp);

        $mail->send();
    } catch (Exception $e) {
        error_log('MyFitCal OTP email error: ' . $e->getMessage());
    }
}

function buildOTPEmailHTML(string $name, string $otp): string {
    $fname  = explode(' ', $name)[0];
    $digits = str_split($otp);
    $boxes  = '';
    foreach ($digits as $d) {
        $boxes .= "<span style='display:inline-block;width:44px;height:54px;line-height:54px;
                   text-align:center;font-size:28px;font-weight:900;color:#142c5b;
                   background:#f0f4ff;border:1.5px solid #c7d2fe;border-radius:10px;
                   margin:0 3px;'>{$d}</span>";
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
<tr><td align="center">
<table width="520" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;">

  <tr><td style="background:linear-gradient(135deg,#0a1628,#142c5b);border-radius:20px 20px 0 0;
                 padding:32px;text-align:center;">
    <div style="display:inline-block;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);
                border-radius:999px;padding:6px 18px;font-size:11px;font-weight:700;
                color:rgba(255,255,255,.7);letter-spacing:1px;text-transform:uppercase;margin-bottom:14px;">
      Login Verification
    </div>
    <h1 style="margin:0;font-size:24px;color:#fff;font-weight:900;">Hi {$fname}!</h1>
    <p style="margin:8px 0 0;font-size:14px;color:rgba(255,255,255,.55);">
      Here is your one-time login code for MyFitCal.
    </p>
  </td></tr>

  <tr><td style="background:#fff;padding:36px 32px;text-align:center;">
    <p style="margin:0 0 20px;font-size:14px;color:#475569;">
      Use this 6-digit code to complete your sign in.<br>
      <strong style="color:#b91c1c;">Do not share this code with anyone.</strong>
    </p>

    <div style="margin:0 auto 24px;">{$boxes}</div>

    <div style="background:#fff7ed;border:1.5px solid #fed7aa;border-radius:12px;
                padding:12px 18px;display:inline-block;margin-bottom:24px;">
      <span style="font-size:13px;font-weight:700;color:#c2410c;">
        ⏱ Expires in 5 minutes
      </span>
    </div>

    <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.7;">
      If you did not attempt to login, please ignore this email.<br>
      Your account remains secure.
    </p>
  </td></tr>

  <tr><td style="background:#f8fafc;border-radius:0 0 20px 20px;padding:16px;text-align:center;">
    <p style="margin:0;font-size:11px;color:#94a3b8;">
      MyFitCal &middot; This code is valid for one-time use only.
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}