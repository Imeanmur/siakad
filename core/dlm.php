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

function dlm_log_event($pdo, $userId, $action, $metadata = []) {
            try {
                        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, metadata, ip) VALUES (:uid, :act, :meta, :ip)');
                        $stmt->execute([
                                    'uid' => $userId,
                                    'act' => (string)$action,
                                    'meta' => !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
                                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                        ]);
            } catch (Exception $e) {
                        // no-op
            }
}

function dlm_archive_unblock_requests($pdo) {
            $days = (int)ARCHIVE_UNBLOCK_REQUEST_DAYS;
            $stmt = $pdo->prepare('SELECT * FROM unblock_requests WHERE status IN ("approved", "denied") AND created_at < (NOW() - INTERVAL :d DAY)');
            $stmt->execute(['d' => $days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) return 0;

            $insert = $pdo->prepare('INSERT INTO unblock_requests_archive (id, user_id, nama, nim, alasan, status, created_at, processed_at, processed_by) VALUES (:id, :user_id, :nama, :nim, :alasan, :status, :created_at, :processed_at, :processed_by)');
            $delete = $pdo->prepare('DELETE FROM unblock_requests WHERE id = :id');
            $count = 0;
            foreach ($rows as $r) {
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

function dlm_run_retention($pdo) {
            // Hapus token unblock kedaluwarsa
            $hours = (int)RETENTION_UNBLOCK_TOKEN_HOURS;
            $pdo->prepare('DELETE FROM unblock_tokens WHERE used = 1 OR expires_at < (NOW() - INTERVAL :h HOUR)')->execute(['h' => $hours]);

            // Hapus audit log lama
            $days = (int)RETENTION_AUDIT_DAYS;
            $pdo->prepare('DELETE FROM audit_logs WHERE created_at < (NOW() - INTERVAL :d DAY)')->execute(['d' => $days]);
}


