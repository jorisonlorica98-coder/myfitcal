<?php
date_default_timezone_set('Asia/Manila');

$base = dirname(__DIR__);
require_once $base . '/config/db.php';

$db  = getDB();
$now = date('H:i');

echo "=== MyFitCal Reminder Script ===\n";
echo "Current PH Time: " . date('h:i A') . "\n";
echo "DB Connected: OK\n";

// Load PHPMailer
if (!loadPHPMailer()) {
    echo "ERROR: PHPMailer not found at:\n";
    echo $base . "/vendor/phpmailer/phpmailer/src/\n";
    exit(1);
}
echo "PHPMailer: OK\n\n";

// Get all users with active reminders that have passed
$stmt = $db->prepare("
    SELECT
        u.id, u.name, u.email, u.gender,
        wr.reminder_time, wr.next_workout_day,
        ug.goal_type,
        uf.fitness_level,
        (SELECT COUNT(DISTINCT day_number)
         FROM user_workout_progress
         WHERE user_id = u.id AND completed = 1) as days_done
    FROM users u
    JOIN workout_reminders wr ON wr.user_id = u.id AND wr.is_active = 1
    LEFT JOIN user_goals ug ON ug.user_id = u.id
    LEFT JOIN user_fitness uf ON uf.user_id = u.id
    WHERE u.role = 'user'
    AND u.is_active = 1
    AND wr.is_active = 1
    AND u.id NOT IN (
        SELECT user_id FROM email_notifications
        WHERE type = 'workout_reminder'
        AND DATE(sent_at) = CURDATE()
        AND status = 'sent'
    )
");
$stmt->execute();
$users = $stmt->fetchAll();

echo "Users with active reminders: " . count($users) . "\n\n";

$sent = 0; $errors = 0;

foreach ($users as $user) {
    $reminder_time = $user['reminder_time'];

    // Parse reminder time - handle ALL formats
    $reminder_time = trim($reminder_time);
    $parsed = false;

    // Format 1: "8:00 PM" or "3:40 PM" (12hr with AM/PM)
    if (preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $reminder_time, $m)) {
        $h = (int)$m[1];
        $min = (int)$m[2];
        $ampm = strtoupper($m[3]);
        if ($ampm === 'PM' && $h !== 12) $h += 12;
        if ($ampm === 'AM' && $h === 12) $h = 0;
        $parsed = sprintf('%02d:%02d', $h, $min);
    }
    // Format 2: "05:28:44" or "05:28" (24hr with or without seconds)
    elseif (preg_match('/^(\d{2}):(\d{2})/', $reminder_time, $m)) {
        $parsed = sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    }
    // Format 3: fallback strtotime
    else {
        $ts = strtotime($reminder_time);
        if ($ts !== false) {
            $parsed = date('H:i', $ts);
        }
    }

    if (!$parsed) {
        echo "  → Could not parse time: {$reminder_time}, skipping.\n";
        continue;
    }

    $current = date('H:i');
    echo "User: {$user['name']} | Reminder: {$reminder_time} → {$parsed} | Now: {$current}\n";

    // Check if reminder time has passed
    if ($current < $parsed) {
        echo "  → Not yet time, skipping.\n";
        continue;
    }

    $fname     = explode(' ', $user['name'])[0];
    $done      = (int)$user['days_done'];
    $day_next  = $done + 1;
    $remaining = 30 - $done;
    $pct       = $done > 0 ? round($done / 30 * 100) : 0;
    $bar_w     = max(4, $pct);
    $is_female = strtolower($user['gender'] ?? '') === 'female';
    $acc_color = $is_female ? '#be185d' : '#16a34a';
    $goal_map  = ['lose'=>'Weight Loss','maintain'=>'Maintenance','gain'=>'Weight Gain','muscle'=>'Muscle Gain'];
    $goal_lbl  = $goal_map[$user['goal_type'] ?? ''] ?? 'Fitness';
    $lvl       = ucfirst($user['fitness_level'] ?? 'Beginner');

    $subject = "💪 MyFitCal — Day {$day_next} Workout Reminder, {$fname}!";
    $html    = buildReminderEmail($fname, $day_next, $done, $remaining, $pct, $bar_w, $goal_lbl, $lvl, $acc_color);

    echo "  → Sending to {$user['email']}...\n";
    $result = sendMail($user['email'], $user['name'], $subject, $html);

    if ($result === true) {
        try {
            $db->prepare("INSERT INTO email_notifications (user_id, type, subject, body, sent_to, status, sent_at)
                VALUES (?, 'workout_reminder', ?, ?, ?, 'sent', NOW())")
               ->execute([$user['id'], $subject, $html, $user['email']]);
        } catch(Exception $e) {}
        $sent++;
        echo "  ✓ Sent!\n";
    } else {
        try {
            $db->prepare("INSERT INTO email_notifications (user_id, type, subject, body, sent_to, status)
                VALUES (?, 'workout_reminder', ?, ?, ?, 'failed')")
               ->execute([$user['id'], $subject, $html, $user['email']]);
        } catch(Exception $e) {}
        $errors++;
        echo "  ✗ Failed: {$result}\n";
    }
}

echo "\n=== Done! Sent: $sent | Errors: $errors ===\n";

// ── Email HTML ─────────────────────────────────────────────────
function buildReminderEmail($fname, $day_next, $done, $remaining, $pct, $bar_w, $goal_lbl, $lvl, $acc_color) {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">
  <tr><td style="background:linear-gradient(135deg,#0a1628,#0d2137,#0a2e1f);border-radius:20px 20px 0 0;padding:32px;text-align:center;">
    <h1 style="margin:0;font-size:26px;color:#fff;font-weight:900;">Hey {$fname}! 💪</h1>
    <p style="margin:10px 0 0;font-size:14px;color:rgba(255,255,255,.5);">Time to crush your Day {$day_next} workout!</p>
  </td></tr>
  <tr><td style="background:#fff;padding:32px;">
    <div style="text-align:center;margin-bottom:24px;">
      <div style="display:inline-block;background:{$acc_color}18;border:2px solid {$acc_color}44;border-radius:16px;padding:16px 36px;">
        <div style="font-size:48px;font-weight:900;color:{$acc_color};line-height:1;">Day {$day_next}</div>
        <div style="font-size:12px;color:#64748b;text-transform:uppercase;margin-top:6px;">of 30 Days</div>
      </div>
    </div>
    <div style="background:#f1f5f9;border-radius:999px;height:10px;overflow:hidden;margin-bottom:6px;">
      <div style="background:{$acc_color};height:100%;width:{$bar_w}%;border-radius:999px;"></div>
    </div>
    <div style="font-size:12px;color:#64748b;margin-bottom:24px;">{$done} / 30 days done · {$pct}% complete</div>
    <table width="100%" cellpadding="4" cellspacing="0" style="margin-bottom:24px;">
      <tr>
        <td width="33%"><div style="background:#f8fafc;border-radius:12px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:900;color:#0f172a;">{$done}</div><div style="font-size:10px;color:#94a3b8;text-transform:uppercase;margin-top:2px;">Done</div></div></td>
        <td width="33%"><div style="background:#f8fafc;border-radius:12px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:900;color:{$acc_color};">{$remaining}</div><div style="font-size:10px;color:#94a3b8;text-transform:uppercase;margin-top:2px;">Remaining</div></div></td>
        <td width="33%"><div style="background:#f8fafc;border-radius:12px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:900;color:#0f172a;">30</div><div style="font-size:10px;color:#94a3b8;text-transform:uppercase;margin-top:2px;">Total</div></div></td>
      </tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:14px;margin-bottom:24px;">
      <tr>
        <td style="padding:14px 20px;"><div style="font-size:10px;color:#94a3b8;text-transform:uppercase;margin-bottom:3px;">Goal</div><div style="font-size:14px;font-weight:800;color:#0f172a;">{$goal_lbl}</div></td>
        <td style="padding:14px 20px;border-left:1px solid #e2e8f0;"><div style="font-size:10px;color:#94a3b8;text-transform:uppercase;margin-bottom:3px;">Level</div><div style="font-size:14px;font-weight:800;color:#0f172a;">{$lvl}</div></td>
      </tr>
    </table>
    <div style="text-align:center;margin-bottom:20px;">
      <a href="http://localhost/myfitcal_system/user/workout.php?day={$day_next}"
         style="display:inline-block;background:{$acc_color};color:#fff;text-decoration:none;font-size:15px;font-weight:800;padding:14px 40px;border-radius:12px;">
        Start Day {$day_next} Workout →
      </a>
    </div>
    <p style="margin:0;font-size:13px;color:#94a3b8;text-align:center;line-height:1.7;">
      Stay consistent — every workout counts!<br>
      <strong style="color:#0f172a;">MyFitCal</strong> is rooting for you 💪
    </p>
  </td></tr>
  <tr><td style="background:#f8fafc;border-radius:0 0 20px 20px;padding:18px;text-align:center;">
    <p style="margin:0;font-size:11px;color:#94a3b8;">MyFitCal — Personalized Fitness & Calorie Management</p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}