<?php
$page_title = 'Lengkapi Riwayat Akademik';
require 'templates/header.php'; // Memanggil Navbar

// Keamanan: Pastikan yang mengakses adalah mahasiswa
if ($_SESSION['user_role'] != 'mahasiswa') {
    header("Location: login.php");
    exit();
}

$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

$nim_mahasiswa_login = $_SESSION['user_id'];
$pesan_sukses = '';

// Proses penyimpanan data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ips_values = $_POST['ips'];
    $sks_values = $_POST['sks'];

    $stmt = $conn->prepare("INSERT INTO riwayat_akademik (nim_mahasiswa, semester, ip_semester, sks_semester) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE ip_semester = VALUES(ip_semester), sks_semester = VALUES(sks_semester)");

    for ($i = 1; $i <= 14; $i++) {
        if (isset($ips_values[$i]) && is_numeric($ips_values[$i]) && isset($sks_values[$i]) && is_numeric($sks_values[$i])) {
            $ip = (float)str_replace(',', '.', $ips_values[$i]);
            $sks = (int)$sks_values[$i];
            if ($ip > 0 && $sks > 0) {
                $stmt->bind_param("sidi", $nim_mahasiswa_login, $i, $ip, $sks);
                $stmt->execute();
            }
        }
    }
    $pesan_sukses = "Data riwayat akademik Anda telah berhasil diperbarui!";
    $stmt->close();
    
    // ### FITUR BARU: LOGIKA PERHITUNGAN IPK & SKS OTOMATIS ###
    // Setelah menyimpan, kita hitung ulang IPK dan Total SKS

    // 1. Ambil semua riwayat yang valid dari database
    $riwayat_result = $conn->query("SELECT ip_semester, sks_semester FROM riwayat_akademik WHERE nim_mahasiswa = '{$nim_mahasiswa_login}' AND ip_semester > 0 AND sks_semester > 0");

    $total_sks = 0;
    $total_bobot_kali_sks = 0;

    while ($row = $riwayat_result->fetch_assoc()) {
        // Akumulasi Total SKS
        $total_sks += $row['sks_semester'];
        // Akumulasi (IP Semester * SKS Semester)
        $total_bobot_kali_sks += ($row['ip_semester'] * $row['sks_semester']);
    }

    // 2. Hitung IPK baru
    $ipk_baru = ($total_sks > 0) ? ($total_bobot_kali_sks / $total_sks) : 0;
    
    // 3. Update data IPK dan Total SKS di tabel utama mahasiswa
    $update_stmt = $conn->prepare("UPDATE mahasiswa SET ipk = ?, total_sks = ? WHERE nim = ?");
    $ipk_formatted = number_format($ipk_baru, 2, '.', '');
    $update_stmt->bind_param("dis", $ipk_formatted, $total_sks, $nim_mahasiswa_login);
    $update_stmt->execute();
    $update_stmt->close();
}

// Ambil data riwayat yang sudah ada untuk ditampilkan
$stmt_fetch = $conn->prepare("SELECT semester, ip_semester, sks_semester FROM riwayat_akademik WHERE nim_mahasiswa = ?");
$stmt_fetch->bind_param("s", $nim_mahasiswa_login);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();
$riwayat_tersimpan = [];
while ($row = $result_fetch->fetch_assoc()) {
    $riwayat_tersimpan[$row['semester']] = $row;
}
$stmt_fetch->close();
$conn->close();
?>

<div class="container my-5">
    <div class="mb-4">
        <h1 class="h3">Lengkapi Riwayat Akademik</h1>
        <p class="text-muted">Isi IP dan SKS yang Anda peroleh di setiap semester. IPK dan Total SKS di profil Anda akan ter-update secara otomatis.</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!empty($pesan_sukses)): ?>
                <div class="alert alert-success"><?= $pesan_sukses; ?></div>
            <?php endif; ?>
            <form method="POST" action="input_riwayat.php">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-dark">
                            <tr><th>Semester</th><th>Indeks Prestasi (IP)</th><th>Jumlah SKS</th></tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i <= 14; $i++): // Dibatasi hingga 8 semester untuk tampilan yang lebih ringkas ?>
                                <tr>
                                    <td><strong>Semester <?= $i; ?></strong></td>
                                    <td><input type="text" pattern="[0-9]+([.,][0-9]+)?" class="form-control" name="ips[<?= $i; ?>]" placeholder="Contoh: 3.52" value="<?= htmlspecialchars($riwayat_tersimpan[$i]['ip_semester'] ?? ''); ?>"></td>
                                    <td><input type="number" min="0" max="24" class="form-control" name="sks[<?= $i; ?>]" placeholder="Contoh: 21" value="<?= htmlspecialchars($riwayat_tersimpan[$i]['sks_semester'] ?? ''); ?>"></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-grid mt-3">
                    <button type="submit" class="btn btn-primary btn-lg">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require 'templates/footer.php'; // Memanggil footer
?>