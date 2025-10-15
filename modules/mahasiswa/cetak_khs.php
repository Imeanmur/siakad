<?php
require_once '../../core/init.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mahasiswa') {
            die("Akses ditolak. Silakan login sebagai mahasiswa.");
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


ob_start();

require_once 'khs_template.php';

$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

$filename = "KHS_" . $mahasiswa['nim'] . ".pdf";
$dompdf->stream($filename, ["Attachment" => false]);
