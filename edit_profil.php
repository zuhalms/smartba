<?php
$page_title = 'Edit Profil';
require 'templates/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$data_profil = [];

$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

// Ambil data profil yang ada untuk ditampilkan di form
if ($user_role == 'dosen') {
    $stmt = $conn->prepare("SELECT * FROM dosen WHERE id_dosen = ?");
    $stmt->bind_param("i", $user_id);
} else { // Mahasiswa
    $stmt = $conn->prepare("SELECT * FROM mahasiswa WHERE nim = ?");
    $stmt->bind_param("s", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $data_profil = $result->fetch_assoc();
}
$stmt->close();
$conn->close();
?>

<div class="container my-5">
    <div class="card shadow-sm col-lg-8 mx-auto">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Edit Profil <?= ucfirst($user_role); ?></h4>
        </div>
        <div class="card-body p-4">
            <form action="update_profil.php" method="POST">
                
                <?php if ($user_role == 'dosen'): ?>
                <div class="mb-3">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama" name="nama_dosen" value="<?= htmlspecialchars($data_profil['nama_dosen'] ?? ''); ?>" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nip" class="form-label">NIP/NIDN</label>
                        <input type="text" class="form-control" id="nip" value="<?= htmlspecialchars($data_profil['nip'] ?? $data_profil['nidn_dosen'] ?? ''); ?>" readonly>
                        <small class="text-muted">NIP/NIDN tidak dapat diubah.</small>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email_dosen" value="<?= htmlspecialchars($data_profil['email_dosen'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="telp" class="form-label">Nomor Telepon</label>
                    <input type="text" class="form-control" id="telp" name="telp_dosen" value="<?= htmlspecialchars($data_profil['telp_dosen'] ?? ''); ?>">
                </div>

                <?php elseif ($user_role == 'mahasiswa'): ?>
                <div class="mb-3">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama" name="nama_mahasiswa" value="<?= htmlspecialchars($data_profil['nama_mahasiswa'] ?? ''); ?>" required>
                </div>
                 <div class="mb-3">
                    <label for="nim" class="form-label">NIM</label>
                    <input type="text" class="form-control" id="nim" value="<?= htmlspecialchars($data_profil['nim'] ?? ''); ?>" readonly>
                    <small class="text-muted">NIM tidak dapat diubah.</small>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email_mahasiswa" value="<?= htmlspecialchars($data_profil['email_mahasiswa'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="telp" class="form-label">Nomor Telepon</label>
                    <input type="text" class="form-control" id="telp" name="telp_mahasiswa" value="<?= htmlspecialchars($data_profil['telp_mahasiswa'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="alamat" class="form-label">Alamat</label>
                    <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= htmlspecialchars($data_profil['alamat'] ?? ''); ?></textarea>
                </div>
                <?php endif; ?>

                <div class="mt-4 d-flex justify-content-end">
                    <a href="profil.php" class="btn btn-secondary me-2">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>