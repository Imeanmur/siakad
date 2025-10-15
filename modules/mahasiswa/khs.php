<?php
$page_title = 'Kartu Hasil Studi (KHS)';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'mahasiswa') {
    header('Location: ' . BASE_URL);
    exit();
}

$user_id = $_SESSION['user_id'];
$current_tahun_ajaran = "2025/2026";
$current_semester = "Ganjil";

function getAngkaMutu($grade_huruf)
{
    switch ($grade_huruf) {
        case 'A':
            return 4.00;
        case 'B':
            return 3.00;
        case 'C':
            return 2.00;
        case 'D':
            return 1.00;
        default:
            return 0.00;
    }
}

try {
    $stmt_mhs = $pdo->prepare("SELECT id, nim, nama_lengkap, jurusan FROM mahasiswa WHERE user_id = ?");
    $stmt_mhs->execute([$user_id]);
    $mahasiswa = $stmt_mhs->fetch();
    if (!$mahasiswa) die("Data mahasiswa tidak ditemukan.");

    $stmt_khs = $pdo->prepare("
        SELECT kd.sks, n.grade_huruf FROM krs
        JOIN krs_detail kd ON krs.id = kd.krs_id
        LEFT JOIN nilai n ON kd.id = n.krs_detail_id
        WHERE krs.mahasiswa_id = ? AND krs.tahun_ajaran = ? AND krs.semester = ?
    ");
    $stmt_khs->execute([$mahasiswa['id'], $current_tahun_ajaran, $current_semester]);
    $khs_data_semester = $stmt_khs->fetchAll(PDO::FETCH_ASSOC);

    $total_sks_semester = 0;
    $total_bobot_semester = 0;
    foreach ($khs_data_semester as $data) {
        if ($data['grade_huruf']) {
            $sks = $data['sks'];
            $angka_mutu = getAngkaMutu($data['grade_huruf']);
            $total_sks_semester += $sks;
            $total_bobot_semester += $sks * $angka_mutu;
        }
    }
    $ips = ($total_sks_semester > 0) ? round($total_bobot_semester / $total_sks_semester, 2) : 0;

    $stmt_ipk = $pdo->prepare("
        SELECT kd.sks, n.grade_huruf FROM krs
        JOIN krs_detail kd ON krs.id = kd.krs_id
        JOIN nilai n ON kd.id = n.krs_detail_id
        WHERE krs.mahasiswa_id = ? AND n.grade_huruf IS NOT NULL
    ");
    $stmt_ipk->execute([$mahasiswa['id']]);
    $all_grades = $stmt_ipk->fetchAll(PDO::FETCH_ASSOC);

    $total_sks_kumulatif = 0;
    $total_bobot_kumulatif = 0;
    foreach ($all_grades as $grade) {
        $sks = $grade['sks'];
        $angka_mutu = getAngkaMutu($grade['grade_huruf']);
        $total_sks_kumulatif += $sks;
        $total_bobot_kumulatif += $sks * $angka_mutu;
    }
    $ipk = ($total_sks_kumulatif > 0) ? round($total_bobot_kumulatif / $total_sks_kumulatif, 2) : 0;
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-end mb-3">
    <a href="cetak_khs.php" target="_blank" class="btn btn-primary"><i class="bi bi-printer me-2"></i>Cetak KHS</a>
</div>

<?php
require_once 'khs_template.php';

require_once '../../includes/footer.php';
?>