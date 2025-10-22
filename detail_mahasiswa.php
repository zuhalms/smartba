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
        if ($stmt_insert->execute()) { 
            $pesan_sukses = "Catatan bimbingan berhasil disimpan!";
            
            // Jika ini logbook peringatan, tandai semua nilai sebagai "Sudah" ditindaklanjuti
            if ($topik == 'Peringatan Akademik Terkait Nilai') {
                $conn->query("UPDATE nilai_bermasalah SET status_perbaikan = 'Sudah' WHERE nim_mahasiswa = '{$nim_mahasiswa}'");
            }
        }
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
        // Hapus semua laporan nilai bermasalah sebelumnya untuk mahasiswa ini
        $stmt_delete = $conn->prepare("DELETE FROM nilai_bermasalah WHERE nim_mahasiswa = ?");
        $stmt_delete->bind_param("s", $nim_mahasiswa);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        // Loop dan masukkan semua laporan nilai yang baru dari form
        if (isset($_POST['nama_mk'])) {
            $nama_mk_list = $_POST['nama_mk'];
            $nilai_huruf_list = $_POST['nilai_huruf'];
            $semester_diambil_list = $_POST['semester_diambil'];
            
            $stmt_insert = $conn->prepare("INSERT INTO nilai_bermasalah (nim_mahasiswa, nama_mk, nilai_huruf, semester_diambil) VALUES (?, ?, ?, ?)");
            
            for ($i = 0; $i < count($nama_mk_list); $i++) {
                $nama_mk = $nama_mk_list[$i];
                $nilai_huruf = $nilai_huruf_list[$i];
                $semester_diambil = $semester_diambil_list[$i];
                
                if (!empty($nama_mk) && !empty($nilai_huruf) && !empty($semester_diambil)) {
                    $stmt_insert->bind_param("sssi", $nim_mahasiswa, $nama_mk, $nilai_huruf, $semester_diambil);
                    $stmt_insert->execute();
                }
            }
            $stmt_insert->close();
        }
        $pesan_sukses = "Laporan nilai bermasalah berhasil diperbarui.";
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
$daftar_pencapaian = ['Seminar Proposal', 'Ujian Komperehensif', 'Seminar Hasil', 'Ujian Skripsi (Yudisium)', 'Publikasi Jurnal'];
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
                <a href="cetak_laporan_lengkap.php?nim=<?= $mahasiswa['nim']; ?>" class="btn btn-light" target="_blank"><i class="bi bi-printer-fill me-2"></i>Cetak Laporan Lengkap</a>
            </div>
        </div>
    </div>

    <?php if ($pesan_sukses): ?><div class="alert alert-success animate__animated animate__fadeInUp"><?= $pesan_sukses; ?></div><?php endif; ?>
    <?php if ($pesan_error): ?><div class="alert alert-danger animate__animated animate__fadeInUp"><?= $pesan_error; ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4 animate__animated animate__fadeInUp"><div class="card-header"><h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Riwayat Bimbingan</h5></div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if ($result_log->num_rows > 0): mysqli_data_seek($result_log, 0); while($log = $result_log->fetch_assoc()): 
                        $is_dosen = ($log['pengisi'] == 'Dosen');
                        $is_peringatan = ($log['topik_bimbingan'] == 'Peringatan Akademik Terkait Nilai');
                        
                        $border_color = $is_dosen ? '#0d6efd' : '#198754'; // Biru untuk dosen, Hijau untuk mahasiswa
                        if ($is_peringatan) {
                            $border_color = '#ffc107'; // Kuning untuk peringatan
                        }
                    ?>
                        <div class="p-3 mb-2 rounded bg-light" style="border-left: 4px solid <?= $border_color; ?>;">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-0">
                                    <?php if($is_peringatan): ?>
                                        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($log['topik_bimbingan']); ?>
                                </h6>
                                <small class="text-muted"><?= date('d M Y', strtotime($log['tanggal_bimbingan'])); ?></small>
                            </div>
                            <small class="badge bg-white text-dark border my-1">Oleh: <?= $log['pengisi']; ?></small>
                            <p class="mb-1"><b>Pembahasan:</b><br><?= nl2br(htmlspecialchars($log['isi_bimbingan'])); ?></p>
                            <?php if (!empty($log['tindak_lanjut'])): ?><p class="mb-0"><b>Tindak Lanjut:</b><br><?= nl2br(htmlspecialchars($log['tindak_lanjut'])); ?></p><?php endif; ?>
                        </div>
                    <?php endwhile; else: ?><p class="text-center text-muted">Belum ada riwayat bimbingan.</p><?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-4 animate__animated animate__fadeInUp">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-paperclip me-2"></i>Dokumen Terunggah</h5></div>
                <div class="card-body">
                    <?php if ($result_dokumen->num_rows > 0): mysqli_data_seek($result_dokumen, 0);?>
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
                <div class="card-header"><ul class="nav nav-tabs card-header-tabs" id="aksiTab" role="tablist"><li class="nav-item" role="presentation"><button class="nav-link active" id="logbook-tab" data-bs-toggle="tab" data-bs-target="#logbook-panel" type="button">Tambah Logbook</button></li><li class="nav-item" role="presentation"><button class="nav-link" id="lapor-nilai-tab" data-bs-toggle="tab" data-bs-target="#lapor-nilai-panel" type="button">Lapor Nilai</button></li><li class="nav-item" role="presentation"><button class="nav-link" id="penilaian-tab" data-bs-toggle="tab" data-bs-target="#penilaian-panel" type="button">Penilaian</button></li></ul></div>
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
                    <div class="tab-pane fade" id="lapor-nilai-panel" role="tabpanel">
                        <h5 class="mb-3">Lapor Nilai Bermasalah (C/D/E)</h5>
                        <p class="text-muted small">Masukkan semua mata kuliah bermasalah sekaligus. Laporan ini akan menggantikan laporan sebelumnya.</p>
                        <form method="POST" action="detail_mahasiswa.php?nim=<?= urlencode($mahasiswa['nim']); ?>">
                            <div id="laporan-container">
                                <div class="row g-2 align-items-center laporan-baris mb-2">
                                    <div class="col-12"><input class="form-control" list="datalistOptions" name="nama_mk[]" placeholder="Ketik nama mata kuliah..." required></div>
                                    <div class="col"><select class="form-select" name="nilai_huruf[]" required><option value="">Nilai</option><option value="C">C</option><option value="D">D</option><option value="E">E</option></select></div>
                                    <div class="col"><input type="number" class="form-control" name="semester_diambil[]" min="1" max="14" placeholder="Smt" required></div>
                                    <div class="col-auto"><button type="button" class="btn btn-sm btn-danger btn-hapus-baris" style="display:none;"><i class="bi bi-trash"></i></button></div>
                                </div>
                            </div>
                            <datalist id="datalistOptions"><?php foreach ($daftar_matakuliah as $matkul): ?><option value="<?= htmlspecialchars($matkul); ?>"><?php endforeach; ?></datalist>
                            <div class="d-flex justify-content-start mt-2">
                                <button type="button" id="btn-tambah-baris" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-circle-fill me-1"></i>Tambah Mata Kuliah</button>
                            </div>
                            <div class="d-grid mt-3">
                                <button type="submit" name="submit_nilai_bermasalah" class="btn btn-danger">Simpan Laporan</button>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="penilaian-panel" role="tabpanel"><h5 class="mb-3">Form Penilaian Soft Skill</h5><form method="POST" action="detail_mahasiswa.php?nim=<?= urlencode($mahasiswa['nim']); ?>"><input type="hidden" name="periode_evaluasi" value="<?= $periode_sekarang; ?>"><p class="text-muted small">Periode: <strong><?= $periode_sekarang; ?></strong>. Beri skor 1-5.</p><?php foreach ($kategori_softskill as $kategori): ?><div class="mb-3"><label class="form-label"><?= htmlspecialchars($kategori); ?></label><select class="form-select" name="skor[<?= htmlspecialchars($kategori); ?>]" required><option value="">Pilih Skor</option><?php for ($i=1; $i<=5; $i++) echo "<option value='{$i}'>{$i}</option>"; ?></select></div><?php endforeach; ?><div class="d-grid"><button type="submit" name="submit_evaluasi" class="btn btn-primary">Kirim Penilaian</button></div></form></div>
                </div></div>
            </div>
            
            <div class="card shadow-sm mt-4 animate__animated animate__fadeInUp">
                <div class="card-header bg-warning text-dark"><h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Peringatan Akademik</h5></div>
                <div class="card-body p-0">
                    <?php if ($result_nilai_bermasalah->num_rows > 0): ?>
                        <p class="small text-muted p-3 mb-0">Daftar mata kuliah dengan nilai C, D, atau E.</p>
                        <ul class="list-group list-group-flush">
                            <?php mysqli_data_seek($result_nilai_bermasalah, 0); while($nilai = $result_nilai_bermasalah->fetch_assoc()): ?>
                            <li class="list-group-item nilai-item" data-mk="<?= htmlspecialchars($nilai['nama_mk']); ?>" data-nilai="<?= htmlspecialchars($nilai['nilai_huruf']); ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="d-block"><?= htmlspecialchars($nilai['nama_mk']); ?></strong>
                                        <small class="text-muted">Semester <?= htmlspecialchars($nilai['semester_diambil']); ?></small>
                                    </div>
                                    <span class="badge bg-danger rounded-pill fs-6"><?= htmlspecialchars($nilai['nilai_huruf']); ?></span>
                                </div>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                        <div class="card-footer bg-transparent border-0 p-3">
                            <div class="d-grid">
                                <button id="kirimPeringatanMassal" class="btn btn-primary"><i class="bi bi-send-fill me-2"></i>Kirim Peringatan ke Logbook</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted p-4 mb-0">Tidak ada laporan nilai bermasalah.</p>
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

    const tombolPeringatan = document.getElementById('kirimPeringatanMassal');
    if(tombolPeringatan) {
        const tabLogbook = new bootstrap.Tab(document.getElementById('logbook-tab'));
        const inputTopik = document.getElementById('topik_bimbingan');
        const inputIsi = document.getElementById('isi_bimbingan');
        
        tombolPeringatan.addEventListener('click', function(e) {
            e.preventDefault();
            
            const nilaiItems = document.querySelectorAll('.nilai-item');
            if(nilaiItems.length === 0) return;

            let pesan = 'Berdasarkan laporan, terdapat beberapa nilai yang perlu mendapat perhatian khusus:\n\n';
            nilaiItems.forEach(item => {
                const mataKuliah = item.dataset.mk;
                const nilaiHuruf = item.dataset.nilai;
                pesan += '- ' + mataKuliah + ' (Nilai: ' + nilaiHuruf + ')\n';
            });
            pesan += '\nMohon segera diskusikan rencana perbaikan untuk mata kuliah di atas.';

            tabLogbook.show();
            inputTopik.value = 'Peringatan Akademik Terkait Nilai';
            inputIsi.value = pesan;
            inputIsi.focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    
    // JAVASCRIPT BARU UNTUK FORM LAPOR NILAI DINAMIS
    const container = document.getElementById('laporan-container');
    const tombolTambah = document.getElementById('btn-tambah-baris');

    const updateTombolHapus = () => {
        const semuaBaris = container.querySelectorAll('.laporan-baris');
        semuaBaris.forEach((baris, index) => {
            const tombolHapus = baris.querySelector('.btn-hapus-baris');
            tombolHapus.style.display = (semuaBaris.length > 1) ? 'inline-block' : 'none';
        });
    };

    tombolTambah.addEventListener('click', function() {
        const barisPertama = container.querySelector('.laporan-baris');
        const barisBaru = barisPertama.cloneNode(true);
        barisBaru.querySelectorAll('input, select').forEach(input => input.value = '');
        container.appendChild(barisBaru);
        updateTombolHapus();
    });

    container.addEventListener('click', function(e) {
        if (e.target.closest('.btn-hapus-baris')) {
            e.target.closest('.laporan-baris').remove();
            updateTombolHapus();
        }
    });

    updateTombolHapus();
});
</script>
<?php 
$stmt_mhs->close();
$stmt_dokumen->close();
$stmt_chart->close();
$conn->close();
require 'templates/footer.php'; 
?>