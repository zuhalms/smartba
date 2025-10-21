<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

// Proses update berdasarkan peran
if ($user_role == 'dosen') {
    $nama = $_POST['nama_dosen'];
    $email = $_POST['email_dosen'];
    $telp = $_POST['telp_dosen'];
    
    $stmt = $conn->prepare("UPDATE dosen SET nama_dosen = ?, email_dosen = ?, telp_dosen = ? WHERE id_dosen = ?");
    $stmt->bind_param("sssi", $nama, $email, $telp, $user_id);
    
} elseif ($user_role == 'mahasiswa') {
    $nama = $_POST['nama_mahasiswa'];
    $email = $_POST['email_mahasiswa'];
    $telp = $_POST['telp_mahasiswa'];
    $alamat = $_POST['alamat'];
    
    $stmt = $conn->prepare("UPDATE mahasiswa SET nama_mahasiswa = ?, email_mahasiswa = ?, telp_mahasiswa = ?, alamat = ? WHERE nim = ?");
    $stmt->bind_param("sssss", $nama, $email, $telp, $alamat, $user_id);
}

if (isset($stmt)) {
    $stmt->execute();
    $stmt->close();
}

$conn->close();

// Setelah selesai, kembalikan ke halaman profil
header("Location: profil.php?status=sukses");
exit();
?>