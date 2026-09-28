<?php
// Konfigurasi retensi data (disimpan di file JSON)
$retention_file = __DIR__ . '/../config/retention_settings.json';
if (!file_exists($retention_file)) {
    file_put_contents($retention_file, json_encode([
        'audit_days' => 180,
        'unblock_token_hours' => 24
    ]));
}
function get_retention_settings() {
    $file = __DIR__ . '/../config/retention_settings.json';
    $data = json_decode(file_get_contents($file), true);
    return $data;
}
function set_retention_settings($audit_days, $unblock_token_hours) {
    $file = __DIR__ . '/../config/retention_settings.json';
    $data = [
        'audit_days' => (int)$audit_days,
        'unblock_token_hours' => (int)$unblock_token_hours
    ];
    file_put_contents($file, json_encode($data));
}
?>
