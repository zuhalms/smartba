<?php
// Tentukan judul halaman sebelum memanggil header
$page_title = 'Detail Mahasiswa';
require 'templates/header.php';

// Keamanan: Pastikan yang mengakses adalah dosen dan ada NIM yang dikirim
if ($_SESSION['user_role'] != 'dosen' || !isset($_GET['nim'])) {
    header("Location: dashboard_dosen.php");
    exit();
}

$nim_mahasiswa = $_GET['nim'];
$id_dosen_login = $_SESSION['user_id'];
$pesan_sukses = '';
$pesan_error = '';

// Koneksi ke database
$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

// Saat halaman ini dibuka, tandai notifikasi sebagai "Dibaca"
$conn->query("UPDATE logbook SET status_baca = 'Dibaca' WHERE nim_mahasiswa = '{$nim_mahasiswa}' AND id_dosen = {$id_dosen_login} AND pengisi = 'Mahasiswa'");
$conn->query("UPDATE dokumen SET status_baca_dosen = 'Sudah Dilihat' WHERE nim_mahasiswa = '{$nim_mahasiswa}' AND id_dosen = {$id_dosen_login}");

// Proses form jika ada yang disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_logbook'])) {
        $tanggal = $_POST['tanggal_bimbingan']; $topik = $_POST['topik_bimbingan'];
        $isi = $_POST['isi_bimbingan']; $tindak_lanjut = $_POST['tindak_lanjut'];
        $stmt_insert = $conn->prepare("INSERT INTO logbook (nim_mahasiswa, id_dosen, pengisi, status_baca, tanggal_bimbingan, topik_bimbingan, isi_bimbingan, tindak_lanjut) VALUES (?, ?, 'Dosen', 'Belum Dibaca', ?, ?, ?, ?)");
        $stmt_insert->bind_param("sissss", $nim_mahasiswa, $id_dosen_login, $tanggal, $topik, $isi, $tindak_lanjut);
        if ($stmt_insert->execute()) { $pesan_sukses = "Catatan bimbingan berhasil disimpan!"; }
        $stmt_insert->close();
    } elseif (isset($_POST['submit_evaluasi'])) {
        $periode = $_POST['periode_evaluasi']; $skor_evaluasi = $_POST['skor'];
        $stmt_eval = $conn->prepare("INSERT INTO evaluasi_softskill (nim_mahasiswa, id_dosen, periode_evaluasi, kategori, skor) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE skor = VALUES(skor)");
        foreach ($skor_evaluasi as $kategori => $skor) {
            $stmt_eval->bind_param("sissi", $nim_mahasiswa, $id_dosen_login, $periode, $kategori, $skor);
            $stmt_eval->execute();
        }
        $pesan_sukses = "Evaluasi soft skill berhasil disimpan!";
        $stmt_eval->close();
    } 
    elseif (isset($_POST['submit_nilai_bermasalah'])) {
        $nama_mk = $_POST['nama_mk']; $nilai_huruf = $_POST['nilai_huruf']; $semester_diambil = $_POST['semester_diambil'];
        $check_stmt = $conn->prepare("SELECT id_nilai FROM nilai_bermasalah WHERE nim_mahasiswa = ? AND nama_mk = ?");
        $check_stmt->bind_param("ss", $nim_mahasiswa, $nama_mk);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        if ($result->num_rows > 0) {
            $update_stmt = $conn->prepare("UPDATE nilai_bermasalah SET nilai_huruf = ?, semester_diambil = ? WHERE nim_mahasiswa = ? AND nama_mk = ?");
            $update_stmt->bind_param("siss", $nilai_huruf, $semester_diambil, $nim_mahasiswa, $nama_mk);
            if ($update_stmt->execute()) { $pesan_sukses = "Laporan nilai berhasil diperbarui!"; }
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO nilai_bermasalah (nim_mahasiswa, nama_mk, nilai_huruf, semester_diambil) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("sssi", $nim_mahasiswa, $nama_mk, $nilai_huruf, $semester_diambil);
            if ($insert_stmt->execute()) { $pesan_sukses = "Laporan nilai berhasil disimpan!"; }
        }
    }
}

// === AMBIL SEMUA DATA YANG DIPERLUKAN ===
$stmt_mhs = $conn->prepare("SELECT m.*, d.nama_dosen FROM mahasiswa m JOIN dosen d ON m.id_dosen_pa = d.id_dosen WHERE m.nim = ? AND m.id_dosen_pa = ?");
$stmt_mhs->bind_param("si", $nim_mahasiswa, $id_dosen_login);
$stmt_mhs->execute();
$result_mhs = $stmt_mhs->get_result();
if ($result_mhs->num_rows === 0) { header("Location: dashboard_dosen.php"); exit(); }
$mahasiswa = $result_mhs->fetch_assoc();
$result_log = $conn->query("SELECT * FROM logbook WHERE nim_mahasiswa = '{$nim_mahasiswa}' ORDER BY tanggal_bimbingan DESC, created_at DESC");
$daftar_pencapaian = ['Seminar Proposal', 'Penelitian Selesai', 'Seminar Hasil', 'Ujian Skripsi (Yudisium)', 'Publikasi Jurnal'];
$result_pencapaian = $conn->query("SELECT nama_pencapaian, status, tanggal_selesai FROM pencapaian WHERE nim_mahasiswa = '{$nim_mahasiswa}'");
$status_pencapaian = [];
while($row = $result_pencapaian->fetch_assoc()) { $status_pencapaian[$row['nama_pencapaian']] = $row; }
$result_mk = $conn->query("SELECT nama_mk FROM mata_kuliah ORDER BY nama_mk ASC");
$daftar_matakuliah = [];
while ($row = $result_mk->fetch_assoc()) { $daftar_matakuliah[] = $row['nama_mk']; }
$kategori_softskill = ['Disiplin & Komitmen', 'Partisipasi & Keaktifan', 'Etika & Sopan Santun', 'Kepemimpinan & Kerjasama'];
$current_year = date('Y'); $current_month = date('n');
$periode_sekarang = $current_year . ' ' . (($current_month >= 2 && $current_month <= 7) ? 'Genap' : 'Ganjil');
function formatBytes($bytes, $precision = 2) { $units = ['B', 'KB', 'MB', 'GB', 'TB']; $bytes = max($bytes, 0); $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); $pow = min($pow, count($units) - 1); $bytes /= (1 << (10 * $pow)); return round($bytes, $precision) . ' ' . $units[$pow]; }
$stmt_dokumen = $conn->prepare("SELECT * FROM dokumen WHERE nim_mahasiswa = ? ORDER BY tanggal_unggah DESC");
$stmt_dokumen->bind_param("s", $nim_mahasiswa);
$stmt_dokumen->execute();
$result_dokumen = $stmt_dokumen->get_result();
$stmt_chart = $conn->prepare("SELECT semester, ip_semester FROM riwayat_akademik WHERE nim_mahasiswa = ? ORDER BY semester ASC");
$stmt_chart->bind_param("s", $nim_mahasiswa);
$stmt_chart->execute();
$result_chart_data = $stmt_chart->get_result()->fetch_all(MYSQLI_ASSOC);
$chart_labels = json_encode(array_column($result_chart_data, 'semester'));
$chart_data = json_encode(array_column($result_chart_data, 'ip_semester'));
$conn->query("CREATE TABLE IF NOT EXISTS `nilai_bermasalah` (`id_nilai` int(11) NOT NULL AUTO_INCREMENT, `nim_mahasiswa` varchar(20) NOT NULL, `nama_mk` varchar(255) NOT NULL, `nilai_huruf` char(2) NOT NULL, `semester_diambil` int(2) NOT NULL, `status_perbaikan` enum('Belum','Sudah') NOT NULL DEFAULT 'Belum', `tanggal_lapor` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id_nilai`)) ENGINE=InnoDB;");
$result_nilai_bermasalah = $conn->query("SELECT * FROM nilai_bermasalah WHERE nim_mahasiswa = '{$nim_mahasiswa}' ORDER BY semester_diambil ASC");
?>
<style>
    :root { --green-primary: #00A86B; }
    body { background-color: #f4f7f6; }
    .profile-banner { background: linear-gradient(135deg, #00A86B, #008F5A); border-radius: 1rem; padding: 2rem; color: white; }
    .profile-banner h2 { font-family: 'Montserrat', sans-serif; font-weight: 800; }
    .nav-tabs .nav-link { color: var(--green-primary); }
    .nav-tabs .nav-link.active { color: #333; font-weight: bold; }
</style>

<div class="container my-5">
    <div class="profile-banner mb-4 animate__animated animate__fadeInDown">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <a href="dashboard_dosen.php" class="text-white text-decoration-none mb-2 d-inline-block"><i class="bi bi-arrow-left-circle me-2"></i>Kembali ke Dashboard</a>
                <h2 class="mb-1"><?= htmlspecialchars($mahasiswa['nama_mahasiswa']); ?></h2>
                <p class="lead mb-0">NIM: <?= htmlspecialchars($mahasiswa['nim']); ?> | IPK: <?= number_format($mahasiswa['ipk'], 2); ?></p>
            </div>
            <div>
                <a href="cetak_laporan_lengkap.php?nim=<?= $mahasiswa['nim']; ?>" class="btn btn-light" target="_blank">
                    <i class="bi bi-printer-fill me-2"></i>Cetak Laporan Lengkap
                </a>
            </div>
        </div>
    </div>

    <?php if ($pesan_sukses): ?><div class="alert alert-success animate__animated animate__fadeInUp"><?= $pesan_sukses; ?></div><?php endif; ?>
    <?php if ($pesan_error): ?><div class="alert alert-danger animate__animated animate__fadeInUp"><?= $pesan_error; ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4 animate__animated animate__fadeInUp"><div class="card-header"><h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Riwayat Bimbingan</h5></div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if ($result_log->num_rows > 0): while($log = $result_log->fetch_assoc()): $is_dosen = ($log['pengisi'] == 'Dosen'); ?>
                        <div class="p-3 mb-2 rounded bg-light" style="border-left: 4px solid <?= $is_dosen ? '#0d6efd' : '#198754'; ?>;">
                            <div class="d-flex justify-content-between"><h6 class="mb-0 <?= $is_dosen ? 'text-primary' : 'text-success'; ?>"><?= htmlspecialchars($log['topik_bimbingan']); ?></h6><small class="text-muted"><?= date('d M Y', strtotime($log['tanggal_bimbingan'])); ?></small></div><small class="badge bg-white text-dark border my-1">Oleh: <?= $log['pengisi']; ?></small>
                            <p class="mb-1"><b>Pembahasan:</b><br><?= nl2br(htmlspecialchars($log['isi_bimbingan'])); ?></p>
                            <?php if (!empty($log['tindak_lanjut'])): ?><p class="mb-0"><b>Tindak Lanjut:</b><br><?= nl2br(htmlspecialchars($log['tindak_lanjut'])); ?></p><?php endif; ?>
                        </div>
                    <?php endwhile; else: ?><p class="text-center text-muted">Belum ada riwayat bimbingan.</p><?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-4 animate__animated animate__fadeInUp">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-paperclip me-2"></i>Dokumen Terunggah</h5></div>
                <div class="card-body">
                    <?php if ($result_dokumen->num_rows > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php while($dokumen = $result_dokumen->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="d-block"><?= htmlspecialchars($dokumen['judul_dokumen']); ?></strong>
                                    <small class="text-muted">Diunggah: <?= date('d M Y, H:i', strtotime($dokumen['tanggal_unggah'])); ?> | Ukuran: <?= formatBytes($dokumen['ukuran_file']); ?></small>
                                </div>
                                <a href="<?= htmlspecialchars($dokumen['path_file']); ?>" class="btn btn-outline-primary btn-sm" download title="Unduh File"><i class="bi bi-download"></i> Unduh</a>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">Mahasiswa ini belum mengunggah dokumen apapun.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm animate__animated animate__fadeInUp"><div class="card-header"><h5 class="mb-0"><i class="bi bi-flag-fill me-2"></i>Update Kemajuan Studi</h5></div>
                <div class="card-body">
                    <form action="update_pencapaian.php" method="POST"><input type="hidden" name="nim_mahasiswa" value="<?= htmlspecialchars($mahasiswa['nim']); ?>"><ul class="list-group">
                        <?php foreach ($daftar_pencapaian as $item): $is_checked = isset($status_pencapaian[$item]) && $status_pencapaian[$item]['status'] == 'Selesai'; $tanggal_selesai = $is_checked ? $status_pencapaian[$item]['tanggal_selesai'] : ''; ?>
                            <li class="list-group-item"><div class="row align-items-center"><div class="col-7"><div class="form-check"><input class="form-check-input milestone-check" type="checkbox" name="pencapaian[<?= htmlspecialchars($item); ?>]" value="Selesai" id="check_<?= str_replace(' ', '_', $item); ?>" <?= $is_checked ? 'checked' : ''; ?>><label class="form-check-label" for="check_<?= str_replace(' ', '_', $item); ?>"><?= htmlspecialchars($item); ?></label></div></div><div class="col-5"><input type="date" class="form-control form-control-sm milestone-date" name="tanggal_pencapaian[<?= htmlspecialchars($item); ?>]" value="<?= htmlspecialchars($tanggal_selesai); ?>" <?= !$is_checked ? 'style="display:none;"' : ''; ?>></div></div></li>
                        <?php endforeach; ?>
                    </ul><div class="d-grid mt-3"><button type="submit" class="btn btn-primary" style="background-color: var(--green-primary); border: none;"><i class="bi bi-save-fill me-2"></i>Simpan Kemajuan</button></div></form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm animate__animated animate__fadeInRight">
                <div class="card-header"><ul class="nav nav-tabs card-header-tabs" id="aksiTab" role="tablist"><li class="nav-item" role="presentation"><button class="nav-link active" id="logbook-tab" data-bs-toggle="tab" data-bs-target="#logbook-panel" type="button">Tambah Logbook</button></li><li class="nav-item" role="presentation"><button class="nav-link" id="penilaian-tab" data-bs-toggle="tab" data-bs-target="#penilaian-panel" type="button">Penilaian</button></li><li class="nav-item" role="presentation"><button class="nav-link" id="lapor-nilai-tab" data-bs-toggle="tab" data-bs-target="#lapor-nilai-panel" type="button">Lapor Nilai</button></li></ul></div>
                <div class="card-body"><div class="tab-content" id="aksiTabContent">
                    <div class="tab-pane fade show active" id="logbook-panel" role="tabpanel"><h5 class="mb-3">Tambah Catatan Bimbingan</h5>
                        <form method="POST" action="detail_mahasiswa.php?nim=<?= urlencode($mahasiswa['nim']); ?>">
                            <div class="mb-3"><label class="form-label">Tanggal</label><input type="date" class="form-control" name="tanggal_bimbingan" value="<?= date('Y-m-d'); ?>" required></div>
                            <div class="mb-3"><label class="form-label">Topik Utama</label><input type="text" class="form-control" id="topik_bimbingan" name="topik_bimbingan" placeholder="Contoh: Diskusi Bab 1" required></div>
                            <div class="mb-3"><label class="form-label">Detail Pembahasan</label><textarea class="form-control" id="isi_bimbingan" name="isi_bimbingan" rows="3" required></textarea></div>
                            <div class="mb-3"><label class="form-label">Tindak Lanjut untuk Mahasiswa</label><textarea class="form-control" name="tindak_lanjut" rows="2"></textarea></div>
                            <div class="d-grid"><button type="submit" name="submit_logbook" class="btn btn-primary">Simpan</button></div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="penilaian-panel" role="tabpanel"><h5 class="mb-3">Form Penilaian Soft Skill</h5><form method="POST" action="detail_mahasiswa.php?nim=<?= urlencode($mahasiswa['nim']); ?>"><input type="hidden" name="periode_evaluasi" value="<?= $periode_sekarang; ?>"><p class="text-muted small">Periode: <strong><?= $periode_sekarang; ?></strong>. Beri skor 1-5.</p><?php foreach ($kategori_softskill as $kategori): ?><div class="mb-3"><label class="form-label"><?= htmlspecialchars($kategori); ?></label><select class="form-select" name="skor[<?= htmlspecialchars($kategori); ?>]" required><option value="">Pilih Skor</option><?php for ($i=1; $i<=5; $i++) echo "<option value='{$i}'>{$i}</option>"; ?></select></div><?php endforeach; ?><div class="d-grid"><button type="submit" name="submit_evaluasi" class="btn btn-primary">Kirim Penilaian</button></div></form></div>
                    <div class="tab-pane fade" id="lapor-nilai-panel" role="tabpanel">
                        <h5 class="mb-3">Lapor Nilai Bermasalah (C/D/E)</h5>
                        <p class="text-muted small">Laporkan jika mahasiswa mendapat nilai yang perlu perhatian.</p>
                        <form method="POST" action="detail_mahasiswa.php?nim=<?= urlencode($mahasiswa['nim']); ?>">
                            <div class="mb-3"><label for="nama_mk" class="form-label">Nama Mata Kuliah</label><input class="form-control" list="datalistOptions" id="nama_mk" name="nama_mk" placeholder="Ketik untuk mencari..." required><datalist id="datalistOptions"><?php foreach ($daftar_matakuliah as $matkul): ?><option value="<?= htmlspecialchars($matkul); ?>"><?php endforeach; ?></datalist></div>
                            <div class="row">
                                <div class="col"><div class="mb-3"><label for="nilai_huruf" class="form-label">Nilai</label><select class="form-select" id="nilai_huruf" name="nilai_huruf" required><option value="">Pilih...</option><option value="C">C</option><option value="D">D</option><option value="E">E</option></select></div></div>
                                <div class="col"><div class="mb-3"><label for="semester_diambil" class="form-label">Semester</label><input type="number" class="form-control" id="semester_diambil" name="semester_diambil" min="1" max="14" placeholder="Cth: 3" required></div></div>
                            </div>
                            <div class="d-grid"><button type="submit" name="submit_nilai_bermasalah" class="btn btn-danger">Simpan Laporan Nilai</button></div>
                        </form>
                    </div>
                </div></div>
            </div>
            
            <div class="card shadow-sm mt-4 animate__animated animate__fadeInUp">
                <div class="card-header bg-warning text-dark"><h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Peringatan Akademik</h5></div>
                <div class="card-body">
                    <?php if ($result_nilai_bermasalah->num_rows > 0): ?>
                        <p class="small text-muted">Daftar mata kuliah dengan nilai C, D, atau E. Klik untuk memberi arahan.</p>
                        <div class="list-group list-group-flush">
                            <?php mysqli_data_seek($result_nilai_bermasalah, 0); while($nilai = $result_nilai_bermasalah->fetch_assoc()): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="d-block"><?= htmlspecialchars($nilai['nama_mk']); ?></strong>
                                    <small class="text-muted">Semester <?= htmlspecialchars($nilai['semester_diambil']); ?></small>
                                </div>
                                <div>
                                    <span class="badge bg-danger rounded-pill fs-6 me-2"><?= htmlspecialchars($nilai['nilai_huruf']); ?></span>
                                    <a href="#" class="btn btn-sm btn-outline-primary btn-beri-arahan" data-mk="<?= htmlspecialchars($nilai['nama_mk']); ?>" data-nilai="<?= htmlspecialchars($nilai['nilai_huruf']); ?>" title="Beri Arahan Bimbingan"><i class="bi bi-send"></i></a>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">Tidak ada laporan nilai bermasalah.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mt-4 animate__animated animate__fadeInUp">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Grafik Perkembangan Studi</h5></div>
                <div class="card-body">
                    <canvas id="progressChart"></canvas>
                    <p id="chartPlaceholder" class="text-center text-muted" style="display: none;">Data riwayat akademik belum tersedia untuk ditampilkan.</p>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const checkboxes = document.querySelectorAll('.milestone-check');
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const dateInput = this.closest('.row').querySelector('.milestone-date');
            if (this.checked) { dateInput.style.display = 'block'; if (!dateInput.value) { dateInput.value = today; } } else { dateInput.style.display = 'none'; dateInput.value = ''; }
        });
    });

    const chartLabels = <?= $chart_labels; ?>;
    const chartData = <?= $chart_data; ?>;
    const canvas = document.getElementById('progressChart');
    const placeholder = document.getElementById('chartPlaceholder');
    
    if (chartLabels && chartLabels.length > 0) {
        const ctx = canvas.getContext('2d');
        new Chart(ctx, { type: 'line', data: { labels: chartLabels.map(l => 'Smt ' + l), datasets: [{ label: 'IP Semester', data: chartData, fill: false, borderColor: '#1E6A59', tension: 0.1 }] }, options: { scales: { y: { beginAtZero: true, max: 4.0 } } } });
    } else {
        canvas.style.display = 'none';
        placeholder.style.display = 'block';
    }

    const tombolArahan = document.querySelectorAll('.btn-beri-arahan');
    const tabLogbook = new bootstrap.Tab(document.getElementById('logbook-tab'));
    const inputTopik = document.getElementById('topik_bimbingan');
    const inputIsi = document.getElementById('isi_bimbingan');
    tombolArahan.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const mataKuliah = this.dataset.mk;
            const nilaiHuruf = this.dataset.nilai;
            tabLogbook.show();
            inputTopik.value = 'Tindak Lanjut Nilai: ' + mataKuliah;
            inputIsi.value = 'Berdasarkan laporan, nilai Anda untuk mata kuliah "' + mataKuliah + '" adalah ' + nilaiHuruf + '. Mohon segera diskusikan rencana perbaikannya.\n\nCatatan tambahan:\n';
            inputIsi.focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
});
</script>
<?php 
$stmt_mhs->close();
$stmt_dokumen->close();
$stmt_chart->close();
$conn->close();
require 'templates/footer.php'; 
?>