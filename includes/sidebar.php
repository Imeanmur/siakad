<div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading text-white text-center py-4 fs-5 fw-bold">SIAKAD</div>
            <div class="list-group list-group-flush my-3">

                        <?php // Tampilkan menu navigasi berdasarkan role (RBAC) 
                        ?>

                        <?php if ($user_role == 'admin'): ?>
                                    <a href="<?php echo BASE_URL; ?>modules/admin/" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/admin/manage_users.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-people me-2"></i>Manajemen User
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/admin/manage_students.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-person-fill me-2"></i>Manajemen Mahasiswa
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/admin/manage_lecturers.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-people me-2"></i>Manajemen Dosen
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/admin/manage_courses.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-book me-2"></i>Manajemen Matkul
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/admin/manage_schedules.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-calendar-week me-2"></i>Manajemen Jadwal
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/admin/manage_announcements.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-megaphone me-2"></i>Pengumuman
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/admin/manage_unblock_requests.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-unlock me-2"></i>Permohonan Buka Blokir
                                    </a>
                        <?php elseif ($user_role == 'dosen'): ?>
                                    <a href="<?php echo BASE_URL; ?>modules/dosen/" class="list-group-item list-group-item-action bg-dark text-white active">
                                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/dosen/input_nilai.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-pencil-square me-2"></i>Input Nilai
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/dosen/absensi.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-check-circle-fill me-2"></i>Absensi
                                    </a>
                        <?php elseif ($user_role == 'mahasiswa'): ?>
                                    <a href="<?php echo BASE_URL; ?>modules/mahasiswa/" class="list-group-item list-group-item-action bg-dark text-white active">
                                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/mahasiswa/krs.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-card-list me-2"></i>Isi KRS
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/mahasiswa/khs.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-journal-text me-2"></i>Lihat KHS
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/mahasiswa/jadwal_kuliah.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-calendar-check me-2"></i>Lihat Jadwal
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>modules/mahasiswa/absensi.php" class="list-group-item list-group-item-action bg-dark text-white">
                                                <i class="bi bi-person-check-fill me-2"></i>Lihat Absensi
                                    </a>
                        <?php endif; ?>

                        <a href="<?php echo BASE_URL; ?>logout.php" class="list-group-item list-group-item-action bg-dark text-danger mt-auto">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
            </div>
</div>
<div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                        <div class="d-flex align-items-center">
                                    <i class="bi bi-list fs-4 me-3" id="menu-toggle"></i>
                                    <h2 class="fs-2 m-0"><?php echo $page_title ?? 'Dashboard'; ?></h2>
                        </div>
            </nav>

            <div class="container-fluid px-4">