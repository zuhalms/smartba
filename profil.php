<?php
// Tentukan judul halaman sebelum memanggil header
$page_title = 'Profil Saya';
require 'templates/header.php'; // Memanggil Navbar

// Siapkan koneksi
$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
$data_profil = [];

// Ambil data profil berdasarkan peran pengguna
if ($user_role == 'mahasiswa') {
    $stmt = $conn->prepare("
        SELECT m.*, p.nama_prodi, d.nama_dosen as nama_dosen_pa
        FROM mahasiswa m
        LEFT JOIN program_studi p ON m.id_prodi = p.id_prodi
        LEFT JOIN dosen d ON m.id_dosen_pa = d.id_dosen
        WHERE m.nim = ?
    ");
    $stmt->bind_param("s", $user_id);
} elseif ($user_role == 'dosen') {
    $stmt = $conn->prepare("SELECT * FROM dosen WHERE id_dosen = ?");
    $stmt->bind_param("i", $user_id);
}

if (isset($stmt)) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $data_profil = $result->fetch_assoc();
    }
    $stmt->close();
}
$conn->close();

// Logika untuk path foto, menggunakan foto default jika tidak ada
$foto_path = 'assets/uploads/default-profile.png'; // Pastikan Anda punya file ini
if ($user_role == 'dosen' && !empty($data_profil['foto_dosen']) && file_exists('assets/uploads/' . $data_profil['foto_dosen'])) {
    $foto_path = 'assets/uploads/' . $data_profil['foto_dosen'];
} elseif ($user_role == 'mahasiswa' && !empty($data_profil['foto_mahasiswa']) && file_exists('assets/uploads/' . $data_profil['foto_mahasiswa'])) {
    $foto_path = 'assets/uploads/' . $data_profil['foto_mahasiswa'];
}
?>

<div class="container my-5">
    <div class="card shadow-sm col-lg-10 mx-auto">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Profil <?= ucfirst($user_role); ?></h4>
            <a href="edit_profil.php" class="btn btn-sm btn-light">Edit Profil</a>
        </div>
        <div class="card-body p-4">
            <?php if ($user_role == 'mahasiswa' && !empty($data_profil)): ?>
                <div class="row">
                    <div class="col-lg-4">
                        <div class="card p-3 text-center">
                            <img id="profilePhoto" src="<?= $foto_path; ?>" class="rounded mb-3" style="width:220px;height:220px;object-fit:cover;border-radius:8px;border:6px solid #f8f9fa;" alt="Foto Profil">
                            <form action="upload_photo.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="nim" value="<?= htmlspecialchars($data_profil['nim']); ?>">
                                <div class="mb-2"><input type="file" name="photo" accept="image/*" class="form-control form-control-sm" required></div>
                                <div class="d-grid"><button type="submit" class="btn btn-outline-secondary">Ganti Foto</button></div>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="table-responsive">
                            <table class="table table-borderless">
                                <tbody>
                                    <tr><th style="width:30%">NIM</th><td>: <?= htmlspecialchars($data_profil['nim']); ?></td></tr>
                                    <tr><th>Nama</th><td>: <?= htmlspecialchars($data_profil['nama_mahasiswa']); ?></td></tr>
                                    <tr><th>Alamat</th><td>: <?= htmlspecialchars($data_profil['alamat'] ?? '-'); ?></td></tr>
                                    <tr><th>Tempat/Tgl Lahir</th><td>: <?= htmlspecialchars(($data_profil['tempat_lahir'] ?? '-') . ', ' . (!empty($data_profil['tgl_lahir']) ? date('d F Y', strtotime($data_profil['tgl_lahir'])) : '-')); ?></td></tr>
                                    <tr><th>Email</th><td>: <?= htmlspecialchars($data_profil['email_mahasiswa'] ?? '-'); ?></td></tr>
                                    <tr><th>No. Telp</th><td>: <?= htmlspecialchars($data_profil['telp_mahasiswa'] ?? '-'); ?></td></tr>
                                    <tr><th>Status</th><td>: <?= htmlspecialchars($data_profil['status_semester'] == 'A' ? 'Aktif' : 'Tidak Aktif'); ?></td></tr>
                                    <tr><th>Jenis Kelamin</th><td>: <?= htmlspecialchars($data_profil['jenis_kelamin'] ?? '-'); ?></td></tr>
                                    <tr><th>Prodi</th><td>: <?= htmlspecialchars($data_profil['nama_prodi'] ?? '-'); ?></td></tr>
                                    <tr><th>Jenjang</th><td>: <?= htmlspecialchars($data_profil['jenjang'] ?? '-'); ?></td></tr>
                                    <tr><th>Penasihat Akademik</th><td>: <?= htmlspecialchars($data_profil['nama_dosen_pa'] ?? '-'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($user_role == 'dosen' && !empty($data_profil)): ?>
                <div class="row">
                    <div class="col-md-4 text-center">
                       <img src="<?= $foto_path ?>" class="rounded-circle img-thumbnail mb-3" alt="Foto Profil" style="width:180px; height:180px; object-fit:cover;">
                       <h5 class="card-title"><?= htmlspecialchars($data_profil['nama_dosen']); ?></h5>
                       <p class="card-text text-muted">Dosen Pembimbing Akademik</p>
                       
                       <form action="upload_foto_dosen.php" method="POST" enctype="multipart/form-data" class="mt-3">
                            <input type="hidden" name="id_dosen" value="<?= htmlspecialchars($data_profil['id_dosen']); ?>">
                            <div class="input-group">
                                <input type="file" name="photo" accept="image/*" class="form-control form-control-sm" required>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Ganti</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-8">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>NIP/NIDN:</strong> <?= htmlspecialchars($data_profil['nip'] ?? $data_profil['nidn_dosen'] ?? '-'); ?></li>
                            <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($data_profil['email_dosen'] ?? 'Belum diisi'); ?></li>
                            <li class="list-group-item"><strong>Nomor Telepon:</strong> <?= htmlspecialchars($data_profil['telp_dosen'] ?? 'Belum diisi'); ?></li>
                        </ul>
                    </div>
                </div>

            <?php else: ?>
                <p class="text-danger text-center">Tidak dapat memuat data profil.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>