<?php
$page_title = 'Dashboard Dosen';
require 'templates/header.php';
if ($_SESSION['user_role'] != 'dosen') { header("Location: login.php"); exit(); }

$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

$id_dosen_login = $_SESSION['user_id'];
$nama_dosen = $_SESSION['user_name'] ?? 'Dosen';

// Ambil data foto dosen
$foto_dosen = '';
$stmt_foto = $conn->prepare("SELECT foto_dosen FROM dosen WHERE id_dosen = ?");
$stmt_foto->bind_param("i", $id_dosen_login);
$stmt_foto->execute();
$result_foto = $stmt_foto->get_result();
if ($result_foto->num_rows > 0) {
    $dosen_data = $result_foto->fetch_assoc();
    $foto_dosen = $dosen_data['foto_dosen'];
}
$stmt_foto->close();

$foto_path = 'assets/uploads/default-profile.png';
if (!empty($foto_dosen) && file_exists('assets/uploads/' . $foto_dosen)) {
    $foto_path = 'assets/uploads/' . $foto_dosen;
}

// Data untuk Ringkasan
$total_mhs = $conn->query("SELECT COUNT(*) as total FROM mahasiswa WHERE id_dosen_pa = $id_dosen_login")->fetch_assoc()['total'];
$total_notif = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_dosen = $id_dosen_login AND pengisi = 'Mahasiswa' AND status_baca = 'Belum Dibaca'")->fetch_assoc()['total'];
$total_peringatan = $conn->query("SELECT COUNT(*) as total FROM mahasiswa WHERE id_dosen_pa = $id_dosen_login AND (ipk < 2.75 OR status_semester = 'N')")->fetch_assoc()['total'];

// Sisa query data tetap sama
$notif_stmt = $conn->prepare("SELECT l.nim_mahasiswa, m.nama_mahasiswa, COUNT(l.id_log) as jumlah FROM logbook l JOIN mahasiswa m ON l.nim_mahasiswa = m.nim WHERE l.id_dosen = ? AND l.pengisi = 'Mahasiswa' AND l.status_baca = 'Belum Dibaca' GROUP BY l.nim_mahasiswa, m.nama_mahasiswa ORDER BY m.nama_mahasiswa ASC");
$notif_stmt->bind_param("i", $id_dosen_login); $notif_stmt->execute(); $notifikasi_result = $notif_stmt->get_result();
$peringatan_stmt = $conn->prepare("SELECT nim, nama_mahasiswa, ipk, status_semester FROM mahasiswa WHERE id_dosen_pa = ? AND (ipk < 2.75 OR status_semester = 'N') ORDER BY ipk ASC");
$peringatan_stmt->bind_param("i", $id_dosen_login); $peringatan_stmt->execute(); $mahasiswa_bermasalah_result = $peringatan_stmt->get_result();
$angkatan_result = $conn->query("SELECT DISTINCT angkatan FROM mahasiswa WHERE id_dosen_pa = $id_dosen_login ORDER BY angkatan DESC");

// ### PERUBAHAN 1: Ubah query untuk menghitung dokumen yang 'Belum Dilihat' ###
$angkatan_filter = $_GET['angkatan'] ?? ''; $search_query = $_GET['search'] ?? '';
$sql = "SELECT m.nim, m.nama_mahasiswa, m.angkatan, m.ipk, m.krs_disetujui, m.status_semester, 
        (SELECT COUNT(*) FROM pencapaian p WHERE p.nim_mahasiswa = m.nim AND p.status = 'Selesai') AS milestones_completed, 
        (SELECT COUNT(*) FROM logbook l WHERE l.nim_mahasiswa = m.nim AND l.pengisi = 'Mahasiswa' AND l.status_baca = 'Belum Dibaca') AS unread_logs,
        (SELECT COUNT(*) FROM dokumen d WHERE d.nim_mahasiswa = m.nim AND d.status_baca_dosen = 'Belum Dilihat') AS unread_dokumen
        FROM mahasiswa m WHERE m.id_dosen_pa = ?";
$params = [$id_dosen_login]; $types = 'i';
if (!empty($angkatan_filter)) { $sql .= " AND m.angkatan = ?"; $params[] = $angkatan_filter; $types .= 's'; }
if (!empty($search_query)) { $sql .= " AND (m.nama_mahasiswa LIKE ? OR m.nim LIKE ?)"; $search_term = "%{$search_query}%"; $params[] = $search_term; $params[] = $search_term; $types .= 'ss'; }
$sql .= " ORDER BY m.nama_mahasiswa ASC";
$main_stmt = $conn->prepare($sql); $main_stmt->bind_param($types, ...$params); $main_stmt->execute(); $semua_mahasiswa_result = $main_stmt->get_result();
$total_pencapaian = 5;
?>
<style>
    :root { --green-primary: #00A86B; --green-dark: #008F5A; }
    body {
        background: linear-gradient(120deg, #e0f2f1, #f1f8e9);
    }
    .futuristic-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 1rem;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
        transition: transform 0.2s ease-in-out;
    }
    .futuristic-card:hover {
        transform: translateY(-5px);
    }
    .summary-card h1 {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--green-primary);
    }
    .table-hover tbody tr:hover {
        background-color: rgba(0, 168, 107, 0.05);
    }
    .status-dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; margin-right: 8px;}
    .status-active { background-color: #198754; }
    .status-inactive { background-color: #dc3545; }
    .progress { height: 8px; }
</style>

<div class="container my-5">
    <style>
        /* Smart & Green Campus - local tweaks for Dosen dashboard */
        .sg-hero { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:18px; border-radius:12px; background: linear-gradient(90deg, rgba(30,86,80,0.06), rgba(20,64,59,0.03)); box-shadow: 0 8px 30px rgba(7,18,16,0.04); }
        .sg-hero h2 { margin:0; font-family: 'Playfair Display', serif; color: var(--campus-green-2); }
        .sg-hero .lead { margin:0; color:#26544a; }
        .sg-tiles { display:flex; gap:12px; }
        .sg-tile { background:#fff; border-radius:10px; padding:10px 14px; min-width:140px; box-shadow: 0 8px 20px rgba(9,20,18,0.04); border-left:4px solid rgba(30,86,80,0.12); }
        .sg-tile .label { font-size:12px; color:#556a63; }
        .sg-tile .value { font-size:18px; font-weight:700; color:var(--campus-green-2); }
        .glass-card { background: linear-gradient(180deg, rgba(255,255,255,0.85), rgba(246,252,250,0.85)); border-radius:12px; }
        .table thead { background: linear-gradient(90deg, rgba(30,86,80,0.04), rgba(20,64,59,0.02)); }
        .table tbody tr:hover { background: rgba(30,86,80,0.02); }
        @media (max-width:768px) { .sg-tiles { flex-direction:column; } .sg-tile { width:100%; } }
    </style>

    <div class="sg-hero mb-4">
        <div>
            <h2>Dashboard Dosen</h2>
            <p class="lead">Ringkasan cepat bimbingan dan notifikasi mahasiswa Anda</p>
        </div>
        <div class="sg-tiles">
            <div class="sg-tile">
                <div class="label">Total Mahasiswa</div>
                <div class="value"><?= $total_mhs; ?></div>
            </div>
            <div class="sg-tile">
                <div class="label">Perhatian Segera</div>
                <div class="value"><?= $total_peringatan; ?></div>
            </div>
            <div class="sg-tile">
                <div class="label">Notifikasi Logbook</div>
                <div class="value"><?= $total_notif; ?></div>
            </div>
        </div>
    </div>
    
    <div class="d-flex align-items-center mb-4" data-aos="fade-down">
        <img src="<?= $foto_path ?>" alt="Foto Profil" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover; border: 3px solid var(--green-primary);">
        <div>
            <h2 class="h4 mb-0">Selamat Datang, <?= htmlspecialchars($nama_dosen); ?>!</h2>
            <p class="text-muted mb-0">Ini adalah pusat kendali bimbingan akademik Anda.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card futuristic-card" data-aos="fade-up" data-aos-delay="300">
                <div class="card-header bg-transparent border-0 pt-3"><h5 class="mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Daftar Mahasiswa Bimbingan</h5></div>
                <div class="card-body">
                    <form method="GET" action="dashboard_dosen.php" class="d-flex mb-3">
                        <select name="angkatan" class="form-select me-2" style="max-width: 180px;" onchange="this.form.submit()"><option value="">Semua Angkatan</option><?php mysqli_data_seek($angkatan_result, 0); while($row = $angkatan_result->fetch_assoc()): ?><option value="<?= $row['angkatan']; ?>" <?= ($angkatan_filter == $row['angkatan']) ? 'selected' : ''; ?>>Angkatan <?= $row['angkatan']; ?></option><?php endwhile; ?></select>
                        <input type="text" name="search" class="form-control me-2" placeholder="Cari nama atau NIM..." value="<?= htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn btn-primary" style="background-color: var(--green-primary); border: none;">Cari</button>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>Nama Mahasiswa</th><th class="text-center">Kemajuan</th><th class="text-center">Notifikasi</th><th class="text-center">Aksi</th></tr></thead>
                            <tbody>
                            <?php if ($semua_mahasiswa_result->num_rows > 0): while($mhs = $semua_mahasiswa_result->fetch_assoc()): 
                                $progress = ($total_pencapaian > 0) ? round(($mhs['milestones_completed'] / $total_pencapaian) * 100) : 0;
                                $is_active = ($mhs['status_semester'] ?? 'A') == 'A';
                            ?>
                                <tr>
                                    <td><div class="d-flex align-items-center"><span class="status-dot <?= $is_active ? 'status-active' : 'status-inactive'; ?>" title="Status: <?= $is_active ? 'Aktif' : 'Non-Aktif'; ?>"></span><div><a href="detail_mahasiswa.php?nim=<?= urlencode($mhs['nim']); ?>" class="text-decoration-none"><strong class="d-block text-dark"><?= htmlspecialchars($mhs['nama_mahasiswa']); ?></strong></a><small class="text-muted">NIM: <?= htmlspecialchars($mhs['nim']); ?> | IPK: <?= number_format($mhs['ipk'], 2); ?></small></div></div></td>
                                    <td class="text-center" style="min-width: 150px;"><div class="d-flex align-items-center"><div class="progress flex-grow-1 me-2" title="<?= $progress; ?>% Selesai"><div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress; ?>%;"></div></div><span class="fw-bold small"><?= $progress; ?>%</span></div></td>
                                    
                                    <td class="text-center" style="width: 150px;">
                                        <?php if ($mhs['unread_logs'] > 0): ?>
                                            <span class="badge bg-danger rounded-pill me-1" title="<?= $mhs['unread_logs'] ?> logbook baru">
                                                <i class="bi bi-chat-left-text-fill"></i> <?= $mhs['unread_logs'] ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($mhs['unread_dokumen'] > 0): ?>
                                            <span class="badge bg-primary rounded-pill" title="<?= $mhs['unread_dokumen'] ?> dokumen baru">
                                                <i class="bi bi-paperclip"></i> <?= $mhs['unread_dokumen'] ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($mhs['unread_logs'] == 0 && $mhs['unread_dokumen'] == 0): ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center" style="width: 150px;"><div class="btn-group"><button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="detail_mahasiswa.php?nim=<?= urlencode($mhs['nim']); ?>"><i class="bi bi-person-lines-fill me-2"></i>Lihat Detail</a></li><li><hr class="dropdown-divider"></li><?php if (!$mhs['krs_disetujui']): ?><li><a class="dropdown-item text-success" href="approve_krs.php?nim=<?= urlencode($mhs['nim']); ?>" onclick="return confirm('Setujui KRS untuk <?= htmlspecialchars($mhs['nama_mahasiswa']); ?>?');"><i class="bi bi-check-circle-fill me-2"></i>Setujui KRS</a></li><?php else: ?><li><a class="dropdown-item text-danger" href="reject_krs.php?nim=<?= urlencode($mhs['nim']); ?>" onclick="return confirm('Batalkan persetujuan KRS untuk <?= htmlspecialchars($mhs['nama_mahasiswa']); ?>?');"><i class="bi bi-x-circle-fill me-2"></i>Tolak KRS</a></li><?php endif; ?><li><a class="dropdown-item" href="toggle_status.php?nim=<?= urlencode($mhs['nim']); ?>" onclick="return confirm('Ubah status mahasiswa ini menjadi <?= $is_active ? 'Non-Aktif' : 'Aktif'; ?>?');"><i class="bi bi-arrow-repeat me-2"></i>Jadikan <?= $is_active ? 'Non-Aktif' : 'Aktif'; ?></a></li></ul></div></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center text-muted p-4">Tidak ada mahasiswa yang cocok dengan kriteria.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card futuristic-card mb-4" data-aos="fade-up" data-aos-delay="400"><div class="card-header bg-transparent border-0 pt-3"><h5 class="mb-0"><i class="bi bi-bell-fill me-2 text-warning"></i>Notifikasi Logbook</h5></div><div class="card-body">
                <?php if ($notifikasi_result->num_rows > 0): mysqli_data_seek($notifikasi_result, 0); ?><div class="list-group list-group-flush"><?php while($notif = $notifikasi_result->fetch_assoc()): ?><a href="detail_mahasiswa.php?nim=<?= urlencode($notif['nim_mahasiswa']); ?>" class="list-group-item list-group-item-action bg-transparent d-flex justify-content-between align-items-center"><?= htmlspecialchars($notif['nama_mahasiswa']); ?><span class="badge bg-danger rounded-pill"><?= $notif['jumlah']; ?></span></a><?php endwhile; ?></div><?php else: ?><p class="text-muted text-center mb-0">Tidak ada notifikasi baru.</p><?php endif; ?>
            </div></div>
            <div class="card futuristic-card" data-aos="fade-up" data-aos-delay="500"><div class="card-header bg-danger text-white"><h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Peringatan Dini</h5></div><div class="card-body">
                <?php if ($mahasiswa_bermasalah_result->num_rows > 0): ?><div class="list-group list-group-flush"><?php while($mhs = $mahasiswa_bermasalah_result->fetch_assoc()): ?><a href="detail_mahasiswa.php?nim=<?= urlencode($mhs['nim']); ?>" class="list-group-item list-group-item-action bg-transparent d-flex justify-content-between align-items-center"><strong><?= htmlspecialchars($mhs['nama_mahasiswa']); ?></strong><div><?php if($mhs['status_semester'] == 'N'): ?><span class="badge bg-secondary">Non-Aktif</span><?php endif; ?><?php if($mhs['ipk'] < 2.75): ?><span class="badge bg-danger">IPK: <?= $mhs['ipk']; ?></span><?php endif; ?></div></a><?php endwhile; ?></div><?php else: ?><p class="text-muted text-center mb-0">Tidak ada mahasiswa yang perlu perhatian khusus.</p><?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
<?php
$conn->close();
require 'templates/footer.php'; 
?>