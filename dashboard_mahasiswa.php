<?php
// Tentukan judul halaman sebelum memanggil header
$page_title = 'Dashboard Mahasiswa';
require 'templates/header.php'; // Memanggil Navbar

// Keamanan: Pastikan yang mengakses adalah mahasiswa
if ($_SESSION['user_role'] != 'mahasiswa') {
    header("Location: login.php");
    exit();
}

// ===================================================================
// == BAGIAN LOGIKA PHP (LENGKAP) ==
// ===================================================================
$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

$nim_mahasiswa_login = $_SESSION['user_id'];
$pesan_sukses_logbook = '';
$pesan_sukses_evaluasi = '';

// Menentukan periode evaluasi saat ini
$current_year = date('Y');
$current_month = date('n');
$periode_sekarang = $current_year . ' ' . (($current_month >= 2 && $current_month <= 7) ? 'Genap' : 'Ganjil');

// Proses form JIKA ada yang disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_logbook_mahasiswa'])) {
        $id_dosen = $_POST['id_dosen']; $tanggal = $_POST['tanggal_bimbingan'];
        $topik = $_POST['topik_bimbingan']; $isi = $_POST['isi_bimbingan'];
        $stmt_insert = $conn->prepare("INSERT INTO logbook (nim_mahasiswa, id_dosen, pengisi, tanggal_bimbingan, topik_bimbingan, isi_bimbingan) VALUES (?, ?, 'Mahasiswa', ?, ?, ?)");
        $stmt_insert->bind_param("sisss", $nim_mahasiswa_login, $id_dosen, $tanggal, $topik, $isi);
        if ($stmt_insert->execute()) { $pesan_sukses_logbook = "Catatan bimbingan Anda berhasil disimpan!"; }
        $stmt_insert->close();
    } elseif (isset($_POST['submit_evaluasi_dosen'])) {
        $id_dosen = $_POST['id_dosen']; $skor_komunikasi = $_POST['skor_komunikasi'];
        $skor_membantu = $_POST['skor_membantu']; $skor_solusi = $_POST['skor_solusi'];
        $saran_kritik = $_POST['saran_kritik'];
        $stmt_insert = $conn->prepare("INSERT INTO evaluasi_dosen (nim_mahasiswa, id_dosen, periode_evaluasi, skor_komunikasi, skor_membantu, skor_solusi, saran_kritik) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("sisiiis", $nim_mahasiswa_login, $id_dosen, $periode_sekarang, $skor_komunikasi, $skor_membantu, $skor_solusi, $saran_kritik);
        if ($stmt_insert->execute()) { $pesan_sukses_evaluasi = "Terima kasih! Evaluasi Anda telah berhasil dikirim."; }
        $stmt_insert->close();
    }
}

// 1. Ambil Notifikasi
$notif_krs_result = $conn->query("SELECT krs_disetujui FROM mahasiswa WHERE nim = '$nim_mahasiswa_login' AND krs_disetujui = TRUE AND krs_notif_dilihat = FALSE");
$ada_notif_krs = $notif_krs_result->num_rows > 0;
$notif_logbook_result = $conn->query("SELECT COUNT(id_log) as jumlah FROM logbook WHERE nim_mahasiswa = '$nim_mahasiswa_login' AND pengisi = 'Dosen' AND status_baca = 'Belum Dibaca'");
$notif_logbook = $notif_logbook_result->fetch_assoc();
$jumlah_notif_logbook = $notif_logbook['jumlah'];

// 2. Ambil semua data mahasiswa & dosen PA
$stmt_mhs = $conn->prepare("SELECT m.*, d.id_dosen, d.nama_dosen FROM mahasiswa m JOIN dosen d ON m.id_dosen_pa = d.id_dosen WHERE m.nim = ?");
$stmt_mhs->bind_param("s", $nim_mahasiswa_login); $stmt_mhs->execute();
$mahasiswa = $stmt_mhs->get_result()->fetch_assoc(); $stmt_mhs->close();

// ### PERUBAHAN 1: Logika untuk menentukan path foto ###
$foto_path = 'assets/uploads/default-profile.png';
if (!empty($mahasiswa['foto_mahasiswa']) && file_exists('assets/uploads/' . $mahasiswa['foto_mahasiswa'])) {
    $foto_path = 'assets/uploads/' . $mahasiswa['foto_mahasiswa'];
}

// 3. Cek status evaluasi
$stmt_check = $conn->prepare("SELECT id_evaluasi_dosen FROM evaluasi_dosen WHERE nim_mahasiswa = ? AND periode_evaluasi = ?");
$stmt_check->bind_param("ss", $nim_mahasiswa_login, $periode_sekarang); $stmt_check->execute();
$sudah_mengisi_evaluasi = $stmt_check->get_result()->num_rows > 0; $stmt_check->close();

// 4. Ambil data logbook, chart, evaluasi soft skill
$result_log = $conn->query("SELECT * FROM logbook WHERE nim_mahasiswa = '$nim_mahasiswa_login' ORDER BY tanggal_bimbingan DESC, created_at DESC");
$result_chart_data = $conn->query("SELECT semester, ip_semester FROM riwayat_akademik WHERE nim_mahasiswa = '$nim_mahasiswa_login' ORDER BY semester ASC")->fetch_all(MYSQLI_ASSOC);
$result_eval_softskill = $conn->query("SELECT * FROM evaluasi_softskill WHERE nim_mahasiswa = '$nim_mahasiswa_login' ORDER BY periode_evaluasi DESC, kategori ASC");
$evaluasi_per_periode = []; while($row = $result_eval_softskill->fetch_assoc()) { $evaluasi_per_periode[$row['periode_evaluasi']][] = $row; }
$chart_labels = json_encode(array_column($result_chart_data, 'semester'));
$chart_data = json_encode(array_column($result_chart_data, 'ip_semester'));

// 5. Ambil data dokumen
function formatBytes($bytes, $precision = 2) { $units = ['B', 'KB', 'MB', 'GB', 'TB']; $bytes = max($bytes, 0); $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); $pow = min($pow, count($units) - 1); $bytes /= (1 << (10 * $pow)); return round($bytes, $precision) . ' ' . $units[$pow]; }
$result_dokumen = $conn->query("SELECT * FROM dokumen WHERE nim_mahasiswa = '$nim_mahasiswa_login' ORDER BY tanggal_unggah DESC");

// 6. Ambil data pencapaian (milestones)
$daftar_pencapaian = ['Seminar Proposal', 'Ujian Komperehensif', 'Seminar Hasil', 'Ujian Skripsi (Yudisium)', 'Publikasi Jurnal'];
$stmt_pencapaian = $conn->prepare("SELECT nama_pencapaian, status, tanggal_selesai FROM pencapaian WHERE nim_mahasiswa = ?");
$stmt_pencapaian->bind_param("s", $nim_mahasiswa_login); $stmt_pencapaian->execute();
$result_pencapaian = $stmt_pencapaian->get_result();
$status_pencapaian = []; $jumlah_selesai = 0;
while($row = $result_pencapaian->fetch_assoc()) {
    $status_pencapaian[$row['nama_pencapaian']] = $row;
    if ($row['status'] == 'Selesai') { $jumlah_selesai++; }
}
$total_pencapaian = count($daftar_pencapaian);
$persentase_kemajuan = ($total_pencapaian > 0) ? round(($jumlah_selesai / $total_pencapaian) * 100) : 0;

// Tandai notifikasi sebagai "Sudah Dilihat"
if ($ada_notif_krs) { $conn->query("UPDATE mahasiswa SET krs_notif_dilihat = TRUE WHERE nim = '$nim_mahasiswa_login'"); }
if ($jumlah_notif_logbook > 0) { $conn->query("UPDATE logbook SET status_baca = 'Dibaca' WHERE nim_mahasiswa = '$nim_mahasiswa_login' AND pengisi = 'Dosen'"); }

$conn->close();
?>

<div class="container my-5">

    <?php if ($ada_notif_krs || $jumlah_notif_logbook > 0): ?>
    <div class="alert alert-success border-success mb-4 animate__animated animate__fadeIn">
        <h5 class="alert-heading">🔔 Pemberitahuan Baru</h5>
        <ul class="mb-0">
            <?php if ($ada_notif_krs): ?><li><strong>KRS Anda telah disetujui</strong> oleh Dosen PA.</li><?php endif; ?>
            <?php if ($jumlah_notif_logbook > 0): ?><li>Dosen PA Anda telah menambahkan <strong><?= $jumlah_notif_logbook; ?> catatan bimbingan baru</strong>.</li><?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm animate__animated animate__fadeInUp">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <img src="<?= $foto_path ?>" alt="Foto Profil" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 4px solid #e9ecef;">
                        <div>
                            <p class="text-muted mb-0">Selamat Datang,</p>
                            <h4 class="mb-1"><?= htmlspecialchars($mahasiswa['nama_mahasiswa']); ?></h4>
                            <p class="text-muted mb-0">NIM: <?= htmlspecialchars($mahasiswa['nim']); ?> | Dosen PA: <?= htmlspecialchars($mahasiswa['nama_dosen']); ?></p>
                        </div>
                        <div class="ms-auto d-flex gap-3 text-center">
                            <div class="px-3"><p class="text-muted mb-0 small">IPK</p><h4 class="mb-0"><?= number_format($mahasiswa['ipk'], 2); ?></h4></div>
                            <div class="border-start"></div>
                            <div class="px-3"><p class="text-muted mb-0 small">Total SKS</p><h4 class="mb-0"><?= $mahasiswa['total_sks']; ?></h4></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm animate__animated animate__fadeInUp" data-aos-delay="100">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <ul class="nav nav-tabs card-header-tabs" id="logbookTab" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" id="riwayat-tab" data-bs-toggle="tab" data-bs-target="#riwayat" type="button" role="tab"><i class="bi bi-list-ul me-1"></i>Riwayat</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="tambah-tab" data-bs-toggle="tab" data-bs-target="#tambah" type="button" role="tab"><i class="bi bi-pencil-square me-1"></i>Tambah Catatan</button></li>
                    </ul>
                    <?php if($result_log->num_rows > 0): ?>
                    <a href="delete_all_history.php" class="btn btn-danger btn-sm" onclick="return confirm('PERINGATAN: Hapus SELURUH riwayat bimbingan Anda? Aksi ini tidak dapat dibatalkan.');"><i class="bi bi-trash"></i> Hapus Semua</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="logbookTabContent">
                        <div class="tab-pane fade show active" id="riwayat" role="tabpanel" style="max-height: 450px; overflow-y: auto; padding: 5px;">
                            <?php if ($result_log->num_rows > 0): while($log = $result_log->fetch_assoc()): $is_dosen = ($log['pengisi'] == 'Dosen'); ?>
                            <div class="p-3 mb-2 rounded bg-white shadow-sm" style="border-left: 4px solid <?= $is_dosen ? '#0d6efd' : '#198754'; ?>;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 <?= $is_dosen ? 'text-primary' : 'text-success'; ?>"><?= htmlspecialchars($log['topik_bimbingan']); ?></h6>
                                    <?php if ($log['pengisi'] == 'Mahasiswa'): ?><a href="delete_logbook_mahasiswa.php?id=<?= $log['id_log']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Anda yakin ingin menghapus catatan ini?');" title="Hapus Catatan"><i class="bi bi-trash"></i></a><?php endif; ?>
                                </div>
                                <small class="text-muted"><?= date('d F Y', strtotime($log['tanggal_bimbingan'])); ?></small>
                                <small class="badge bg-light text-dark ms-2">Dicatat oleh: <?= $log['pengisi']; ?></small>
                                <hr class="my-1"><p><?= nl2br(htmlspecialchars($log['isi_bimbingan'])); ?></p>
                            </div>
                            <?php endwhile; else: ?><p class="text-center text-muted">Belum ada riwayat bimbingan.</p><?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="tambah" role="tabpanel">
                            <?php if (!empty($pesan_sukses_logbook)): ?><div class="alert alert-success"><?= $pesan_sukses_logbook; ?></div><?php endif; ?>
                            <form method="POST" action="dashboard_mahasiswa.php">
                                <input type="hidden" name="id_dosen" value="<?= $mahasiswa['id_dosen']; ?>">
                                <div class="mb-3"><label class="form-label">Tanggal Bimbingan</label><input type="date" class="form-control" name="tanggal_bimbingan" value="<?= date('Y-m-d'); ?>" required></div>
                                <div class="mb-3"><label class="form-label">Topik Utama</label><input type="text" class="form-control" name="topik_bimbingan" placeholder="Contoh: Diskusi Judul Skripsi" required></div>
                                <div class="mb-3"><label class="form-label">Catatan/Hasil Diskusi</label><textarea class="form-control" name="isi_bimbingan" rows="4" placeholder="Tuliskan poin-poin penting hasil diskusi..."></textarea></div>
                                <div class="d-grid"><button type="submit" name="submit_logbook_mahasiswa" class="btn btn-primary">Simpan Catatan</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mt-4 animate__animated animate__fadeInUp" data-aos-delay="200">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-folder-plus me-2"></i>Unggah & Lihat Dokumen</h5></div>
                <div class="card-body">
                    <?php if(isset($_SESSION['upload_message'])): ?>
                    <div class="alert alert-<?= $_SESSION['upload_status'] == 'success' ? 'success' : 'danger'; ?>"><?= $_SESSION['upload_message']; ?></div>
                    <?php unset($_SESSION['upload_message']); unset($_SESSION['upload_status']); endif; ?>
                    <form action="upload_dokumen.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3"><label for="judul_dokumen" class="form-label">Judul Dokumen</label><input type="text" class="form-control" id="judul_dokumen" name="judul_dokumen" placeholder="Contoh: Draft KRS Semester 7" required></div>
                        <div class="mb-3"><label for="file_dokumen" class="form-label">Pilih File</label><input class="form-control" type="file" id="file_dokumen" name="file_dokumen" required><div class="form-text">PDF, DOC, DOCX. Maks 5MB.</div></div>
                        <div class="d-grid"><button type="submit" class="btn btn-primary">Unggah File</button></div>
                    </form>
                    <hr>
                    <h6 class="mb-3">Dokumen Terunggah:</h6>
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php if ($result_dokumen->num_rows > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php while($dokumen = $result_dokumen->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div><strong class="d-block"><?= htmlspecialchars($dokumen['judul_dokumen']); ?></strong><small class="text-muted"><?= formatBytes($dokumen['ukuran_file']); ?></small></div>
                                <a href="<?= htmlspecialchars($dokumen['path_file']); ?>" class="btn btn-outline-primary btn-sm" download><i class="bi bi-download"></i></a>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                        <?php else: ?><p class="text-center text-muted small">Belum ada dokumen.</p><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4 animate__animated animate__fadeInUp" data-aos-delay="300">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-flag-fill me-2"></i>Kemajuan Pencapaian</h5>
                    <a href="cetak_pencapaian.php" class="btn btn-info btn-sm text-white" target="_blank"><i class="bi bi-printer-fill me-1"></i>Cetak</a>
                </div>
                <div class="card-body">
                    <div class="progress" role="progressbar" style="height: 20px;"><div class="progress-bar bg-success" style="width: <?= $persentase_kemajuan; ?>%" role="progressbar"><strong><?= $persentase_kemajuan; ?>%</strong></div></div>
                    <ul class="list-group list-group-flush mt-3">
                        <?php foreach ($daftar_pencapaian as $item): 
                            $is_selesai = isset($status_pencapaian[$item]) && $status_pencapaian[$item]['status'] == 'Selesai';
                            $tanggal = $is_selesai && !empty($status_pencapaian[$item]['tanggal_selesai']) ? date('d M Y', strtotime($status_pencapaian[$item]['tanggal_selesai'])) : '';
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center ps-0 <?= $is_selesai ? 'text-success' : 'text-muted'; ?>">
                            <span><span class="fw-bold"><?= $is_selesai ? '✔' : '⚪'; ?></span> <?= htmlspecialchars($item); ?></span>
                            <?php if ($is_selesai): ?><small><?= $tanggal; ?></small><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="card shadow-sm mb-4 animate__animated animate__fadeInUp" data-aos-delay="400">
                <div class="card-body">
                    <h6 class="mb-3"><i class="bi bi-graph-up-arrow me-2"></i>Grafik Perkembangan Studi</h6>
                    <canvas id="progressChart" style="display: <?= empty($result_chart_data) ? 'none' : 'block'; ?>"></canvas>
                    <p id="chartPlaceholder" class="text-center text-muted" style="display: <?= empty($result_chart_data) ? 'block' : 'none'; ?>">Data riwayat akademik belum tersedia.</p>
                </div>
            </div>
            <div class="accordion animate__animated animate__fadeInUp" id="accordionEvaluasi" data-aos-delay="500">
                <div class="accordion-item">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSatu"><i class="bi bi-award-fill me-2"></i>Hasil Evaluasi Soft Skill</button></h2>
                    <div id="collapseSatu" class="accordion-collapse collapse" data-bs-parent="#accordionEvaluasi">
                        <div class="accordion-body" style="max-height: 300px; overflow-y: auto;">
                             <?php if (!empty($evaluasi_per_periode)): foreach($evaluasi_per_periode as $periode => $evaluasi): ?>
                                <div class="mb-3"><strong>Periode: <?= htmlspecialchars($periode); ?></strong><ul class="list-group list-group-flush mt-2">
                                    <?php foreach($evaluasi as $item): ?><li class="list-group-item d-flex justify-content-between"><?= htmlspecialchars($item['kategori']); ?><span class="badge bg-primary rounded-pill">Skor: <?= $item['skor']; ?></span></li><?php endforeach; ?>
                                </ul></div>
                            <?php endforeach; else: ?><p class="text-center text-muted">Belum ada hasil evaluasi.</p><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDua"><i class="bi bi-person-check-fill me-2"></i>Evaluasi Kinerja Dosen PA</button></h2>
                    <div id="collapseDua" class="accordion-collapse collapse" data-bs-parent="#accordionEvaluasi">
                        <div class="accordion-body">
                            <p class="text-muted small">Evaluasi untuk: <strong><?= htmlspecialchars($mahasiswa['nama_dosen']); ?></strong> (Periode: <strong><?= $periode_sekarang; ?></strong>)</p>
                            <?php if ($sudah_mengisi_evaluasi || !empty($pesan_sukses_evaluasi)): ?>
                                <div class="alert alert-success text-center">Terima kasih! Evaluasi Anda telah dikirim.</div>
                            <?php else: ?>
                                <form method="POST" action="dashboard_mahasiswa.php">
                                    <input type="hidden" name="id_dosen" value="<?= $mahasiswa['id_dosen']; ?>">
                                    <div class="mb-3"><label class="form-label">Kemudahan Dosen dihubungi?</label><select class="form-select" name="skor_komunikasi" required><option value="">Pilih 1-5</option><?php for($i=1;$i<=5;$i++) echo "<option value='$i'>$i</option>"; ?></select></div>
                                    <div class="mb-3"><label class="form-label">Seberapa membantu bimbingan?</label><select class="form-select" name="skor_membantu" required><option value="">Pilih 1-5</option><?php for($i=1;$i<=5;$i++) echo "<option value='$i'>$i</option>"; ?></select></div>
                                    <div class="mb-3"><label class="form-label">Kejelasan arahan/solusi?</label><select class="form-select" name="skor_solusi" required><option value="">Pilih 1-5</option><?php for($i=1;$i<=5;$i++) echo "<option value='$i'>$i</option>"; ?></select></div>
                                    <div class="mb-3"><label class="form-label">Saran/Kritik (Opsional)</label><textarea name="saran_kritik" class="form-control" rows="2"></textarea></div>
                                    <div class="d-grid"><button type="submit" name="submit_evaluasi_dosen" class="btn btn-primary">Kirim Evaluasi</button></div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chartLabels = <?= $chart_labels; ?>;
        const chartData = <?= $chart_data; ?>;
        const canvas = document.getElementById('progressChart');
        const placeholder = document.getElementById('chartPlaceholder');
        if (chartLabels && chartLabels.length > 0) {
            canvas.style.display = 'block';
            placeholder.style.display = 'none';
            const ctx = canvas.getContext('2d');
            new Chart(ctx, { type: 'line', data: { labels: chartLabels.map(l => 'Smt ' + l), datasets: [{ label: 'IP Semester', data: chartData, fill: false, borderColor: '#1E5650', tension: 0.1 }] }, options: { scales: { y: { beginAtZero: true, max: 4.0 } } } });
        } else {
            canvas.style.display = 'none';
            placeholder.style.display = 'block';
        }
    });
</script>

<?php
$conn->close();
require 'templates/footer.php';
?>