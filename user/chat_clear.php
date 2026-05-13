<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    try {
        $db->prepare("DELETE FROM chat_messages WHERE user_id=?")
           ->execute([$_SESSION['user_id']]);
        echo json_encode(['ok' => true]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}