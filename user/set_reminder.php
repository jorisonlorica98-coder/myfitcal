<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$db = getDB();

// Ensure table exists
$db->exec("CREATE TABLE IF NOT EXISTS workout_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    reminder_time VARCHAR(10) NOT NULL,
    next_workout_day INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $time = trim($body['time'] ?? '');
    $day  = (int)($body['day'] ?? 1);

    if (empty($time)) {
        echo json_encode(['ok'=>false,'error'=>'No time provided']); exit;
    }

    $dismiss = $body['dismiss'] ?? false;

    if ($dismiss) {
        $db->prepare("UPDATE workout_reminders SET is_active=0 WHERE user_id=?")
           ->execute([$user_id]);
        unset($_SESSION['preferred_workout_time']);
        echo json_encode(['ok'=>true,'dismissed'=>true]); exit;
    }

    $db->prepare("INSERT INTO workout_reminders (user_id, reminder_time, next_workout_day, is_active)
        VALUES (?,?,?,1)
        ON DUPLICATE KEY UPDATE reminder_time=VALUES(reminder_time), next_workout_day=VALUES(next_workout_day), is_active=1, updated_at=NOW()")
       ->execute([$user_id, $time, $day]);

    $_SESSION['preferred_workout_time'] = $time;
    echo json_encode(['ok'=>true,'time'=>$time,'day'=>$day]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $row = $db->prepare("SELECT reminder_time, next_workout_day, is_active FROM workout_reminders WHERE user_id=?");
    $row->execute([$user_id]);
    $r = $row->fetch();
    echo json_encode($r ?: ['reminder_time'=>'','next_workout_day'=>1,'is_active'=>0]);
}