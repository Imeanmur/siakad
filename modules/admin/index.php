<?php
$page_title = 'Dashboard Admin';
require_once '../../includes/header.php';
if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . BASE_URL);
            exit();
}

require_once '../../includes/sidebar.php';
?>

<div class="row g-3 my-2">
            <div class="col-md-4">
                        <div class="p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                                    <div>
                                                <h3 class="fs-2">720</h3>
                                                <p class="fs-5">Mahasiswa</p>
                                    </div>
                                    <i class="bi bi-people p-3 fs-1"></i>
                        </div>
            </div>

            <div class="col-md-4">
                        <div class="p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                                    <div>
                                                <h3 class="fs-2">50</h3>
                                                <p class="fs-5">Dosen</p>
                                    </div>
                                    <i class="bi bi-person-video3 p-3 fs-1"></i>
                        </div>
            </div>

            <div class="col-md-4">
                        <div class="p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                                    <div>
                                                <h3 class="fs-2">120</h3>
                                                <p class="fs-5">Mata Kuliah</p>
                                    </div>
                                    <i class="bi bi-journal-bookmark p-3 fs-1"></i>
                        </div>
            </div>
</div>

<div class="row my-5">
            <h3 class="fs-4 mb-3">Pengumuman Terbaru</h3>
            <div class="col">
                        <div class="card">
                                    <div class="card-body">
                                                Ini adalah area untuk menampilkan pengumuman dari admin untuk semua user.
                                    </div>
                        </div>
            </div>
</div>
<?php
require_once '../../includes/footer.php';
?>