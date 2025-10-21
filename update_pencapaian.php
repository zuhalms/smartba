<?php
session_start();

// Keamanan: Pastikan yang mengakses adalah dosen yang sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'dosen' || !isset($_POST['nim_mahasiswa'])) {
    header("Location: login.php");
    exit();
}

$nim_mahasiswa = $_POST['nim_mahasiswa'];
$pencapaian_dikirim = $_POST['pencapaian'] ?? [];
$tanggal_dikirim = $_POST['tanggal_pencapaian'] ?? []; // Ambil data tanggal

// Koneksi ke database
$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

// Definisikan daftar pencapaian yang valid
$daftar_pencapaian_valid = ['Seminar Proposal', 'Penelitian Selesai', 'Seminar Hasil', 'Ujian Skripsi (Yudisium)', 'Publikasi Jurnal'];

// Siapkan query
$stmt = $conn->prepare("
    INSERT INTO pencapaian (nim_mahasiswa, nama_pencapaian, status, tanggal_selesai) 
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status), tanggal_selesai = VALUES(tanggal_selesai)
");

// Loop melalui semua pencapaian yang valid
foreach ($daftar_pencapaian_valid as $item) {
    // Cek apakah item ini dicentang di form yang dikirim
    if (isset($pencapaian_dikirim[$item]) && $pencapaian_dikirim[$item] == 'Selesai') {
        // Jika dicentang, statusnya 'Selesai'
        $status = 'Selesai';
        // Ambil tanggal dari input, jika kosong gunakan tanggal hari ini sebagai fallback
        $tanggal = !empty($tanggal_dikirim[$item]) ? $tanggal_dikirim[$item] : date('Y-m-d');
    } else {
        // Jika tidak dicentang, statusnya 'Belum Selesai' dan tidak ada tanggal
        $status = 'Belum Selesai';
        $tanggal = null;
    }
    
    // Jalankan query untuk item ini
    $stmt->bind_param("ssss", $nim_mahasiswa, $item, $status, $tanggal);
    $stmt->execute();
}

$stmt->close();
$conn->close();

// Setelah selesai, kembalikan dosen ke halaman detail
header("Location: detail_mahasiswa.php?nim=" . urlencode($nim_mahasiswa));
exit();
?>