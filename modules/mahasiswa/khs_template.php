<div class="card shadow-sm">
            <div class="card-header text-center">
                        <h4 class="mb-0">KARTU HASIL STUDI (KHS)</h4>
            </div>
            <div class="card-body">
                        <div class="row mb-4">
                                    <div class="col-md-6">
                                                <p class="mb-1"><strong>NIM:</strong> <?= htmlspecialchars($mahasiswa['nim']) ?></p>
                                                <p class="mb-0"><strong>Nama Lengkap:</strong> <?= htmlspecialchars($mahasiswa['nama_lengkap']) ?></p>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                                <p class="mb-1"><strong>Jurusan:</strong> <?= htmlspecialchars($mahasiswa['jurusan']) ?></p>
                                                <p class="mb-0"><strong>Indeks Prestasi Kumulatif (IPK):</strong> <span class="badge bg-success fs-6"><?= number_format($ipk, 2); ?></span></p>
                                    </div>
                        </div>

                        <h5 class="mb-3">Semester <?= $current_semester . ' ' . $current_tahun_ajaran ?></h5>

                        <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                                <thead class="table-light">
                                                            <tr>
                                                                        <th>#</th>
                                                                        <th>Kode MK</th>
                                                                        <th>Nama Mata Kuliah</th>
                                                                        <th>SKS</th>
                                                                        <th>Nilai</th>
                                                                        <th>Bobot</th>
                                                            </tr>
                                                </thead>
                                                <tbody>
                                                            <?php if ($khs_data_semester): $i = 1;
                                                                        foreach ($khs_data_semester as $row): ?>
                                                                                    <tr>
                                                                                                <th><?= $i++; ?></th>
                                                                                                <td><?= htmlspecialchars($row['kode_matkul'] ?? 'N/A'); ?></td>
                                                                                                <td><?= htmlspecialchars($row['nama_matkul'] ?? 'N/A'); ?></td>
                                                                                                <td><?= $sks = $row['sks']; ?></td>
                                                                                                <td><?= $grade = $row['grade_huruf'] ?? '-'; ?></td>
                                                                                                <td><?= number_format($sks * getAngkaMutu($grade), 2); ?></td>
                                                                                    </tr>
                                                                        <?php endforeach;
                                                            else: ?>
                                                                        <tr>
                                                                                    <td colspan="6" class="text-center">Data KHS belum tersedia untuk semester ini.</td>
                                                                        </tr>
                                                            <?php endif; ?>
                                                </tbody>
                                                <tfoot class="fw-bold">
                                                            <tr>
                                                                        <td colspan="3" class="text-end">Total SKS Semester</td>
                                                                        <td><?= $total_sks_semester; ?></td>
                                                                        <td class="text-end">Total Bobot Semester</td>
                                                                        <td><?= number_format($total_bobot_semester, 2); ?></td>
                                                            </tr>
                                                            <tr>
                                                                        <td colspan="5" class="text-end">Indeks Prestasi Semester (IPS)</td>
                                                                        <td class="bg-light"><?= number_format($ips, 2); ?></td>
                                                            </tr>
                                                </tfoot>
                                    </table>
                        </div>
            </div>
</div>