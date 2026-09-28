<?php
// Helper untuk Data Lifecycle Management yang bersifat opsional dan terisolasi

if (!defined('RETENTION_AUDIT_DAYS')) {
            define('RETENTION_AUDIT_DAYS', 180);
}
if (!defined('RETENTION_UNBLOCK_TOKEN_HOURS')) {
            define('RETENTION_UNBLOCK_TOKEN_HOURS', 24);
}
if (!defined('ARCHIVE_UNBLOCK_REQUEST_DAYS')) {
            define('ARCHIVE_UNBLOCK_REQUEST_DAYS', 30);
}

function dlm_log_event($pdo, $userId, $action, $metadata = [], $entity_type = null, $entity_id = null) {
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, metadata, ip_address) VALUES (:uid, :act, :etype, :eid, :meta, :ip)');
        $stmt->execute([
            'uid' => $userId,
            'act' => (string)$action,
            'etype' => $entity_type,
            'eid' => $entity_id,
            'meta' => !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        // no-op
    }
}

function dlm_archive_unblock_requests($pdo) {
    // Arsipkan semua permohonan approved/denied yang belum diarsipkan
    $stmt = $pdo->prepare('SELECT * FROM unblock_requests WHERE status IN ("approved", "denied")');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return 0;

    $insert = $pdo->prepare('INSERT INTO unblock_requests_archive (id, user_id, nama, nim, alasan, status, created_at, processed_at, processed_by) VALUES (:id, :user_id, :nama, :nim, :alasan, :status, :created_at, :processed_at, :processed_by)');
    $delete = $pdo->prepare('DELETE FROM unblock_requests WHERE id = :id');
    $count = 0;
    foreach ($rows as $r) {
        // Cek jika sudah ada di arsip, skip
        $cek = $pdo->prepare('SELECT COUNT(*) FROM unblock_requests_archive WHERE id = :id');
        $cek->execute(['id' => $r['id']]);
        if ($cek->fetchColumn() > 0) continue;
        $insert->execute([
            'id' => $r['id'],
            'user_id' => $r['user_id'],
            'nama' => $r['nama'],
            'nim' => $r['nim'],
            'alasan' => $r['alasan'],
            'status' => $r['status'],
            'created_at' => $r['created_at'],
            'processed_at' => $r['processed_at'] ?? null,
            'processed_by' => $r['processed_by'] ?? null,
        ]);
        $delete->execute(['id' => $r['id']]);
        $count++;
    }
    return $count;
}

function dlm_restore_unblock_request($pdo, $id) {
    // Kembalikan permohonan dari arsip ke dashboard utama
    $stmt = $pdo->prepare('SELECT * FROM unblock_requests_archive WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    // Cek jika sudah ada di unblock_requests
    $cek = $pdo->prepare('SELECT COUNT(*) FROM unblock_requests WHERE id = :id');
    $cek->execute(['id' => $id]);
    if ($cek->fetchColumn() > 0) return false;
    $insert = $pdo->prepare('INSERT INTO unblock_requests (id, user_id, nama, nim, alasan, created_at, status, processed_at, processed_by) VALUES (:id, :user_id, :nama, :nim, :alasan, :created_at, :status, :processed_at, :processed_by)');
    $insert->execute([
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'nama' => $row['nama'],
        'nim' => $row['nim'],
        'alasan' => $row['alasan'],
        'created_at' => $row['created_at'],
        'status' => $row['status'],
        'processed_at' => $row['processed_at'],
        'processed_by' => $row['processed_by'],
    ]);
    // Hapus dari arsip
    $del = $pdo->prepare('DELETE FROM unblock_requests_archive WHERE id = :id');
    $del->execute(['id' => $id]);
    return true;
}

function dlm_run_retention($pdo) {
            // Hapus token unblock kedaluwarsa
            $hours = (int)RETENTION_UNBLOCK_TOKEN_HOURS;
            $pdo->prepare('DELETE FROM unblock_tokens WHERE used = 1 OR expires_at < (NOW() - INTERVAL :h HOUR)')->execute(['h' => $hours]);

            // Hapus audit log lama
            $days = (int)RETENTION_AUDIT_DAYS;
            $pdo->prepare('DELETE FROM audit_logs WHERE created_at < (NOW() - INTERVAL :d DAY)')->execute(['d' => $days]);
}


