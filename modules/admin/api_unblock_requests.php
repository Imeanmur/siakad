<?php
// API endpoint untuk data sharing unblock_requests (JSON)
require_once '../../config/database.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

// Hanya admin yang boleh akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $stmt = $pdo->query('SELECT ur.*, u.email FROM unblock_requests ur JOIN users u ON ur.user_id = u.id ORDER BY ur.created_at DESC');
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}
