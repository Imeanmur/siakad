<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . BASE_URL);
            exit();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=unblock_requests.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Tanggal', 'User ID', 'Nama', 'NIM', 'Email', 'Alasan', 'Status', 'Diproses Oleh', 'Diproses Pada']);

$stmt = $pdo->query('SELECT ur.*, u.email FROM unblock_requests ur JOIN users u ON ur.user_id = u.id ORDER BY ur.created_at DESC');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                        $row['id'],
                        $row['created_at'],
                        $row['user_id'],
                        $row['nama'],
                        $row['nim'],
                        $row['email'],
                        $row['alasan'],
                        $row['status'],
                        $row['processed_by'],
                        $row['processed_at'],
            ]);
}
fclose($output);
exit;


