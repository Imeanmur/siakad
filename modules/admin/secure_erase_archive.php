<?php
// Fitur penghancuran data arsip secara permanen (secure erase)
require_once '../../config/database.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

// Hanya admin yang boleh akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

try {
    // Hapus semua data arsip unblock_requests secara permanen
    $stmt = $pdo->prepare('TRUNCATE TABLE unblock_requests_archive');
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Seluruh data arsip telah dihancurkan secara permanen.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}
