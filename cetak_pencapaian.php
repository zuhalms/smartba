<?php
session_start();
require('fpdf/fpdf.php'); // Panggil library FPDF

// Keamanan: Pastikan yang mengakses adalah mahasiswa yang sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'mahasiswa') {
    die("Akses ditolak. Silakan login sebagai mahasiswa.");
}

// Koneksi ke database
$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Koneksi gagal."); }

$nim_mahasiswa_login = $_SESSION['user_id'];

// Ambil data mahasiswa dan dosen PA
$stmt_mhs = $conn->prepare("SELECT m.nama_mahasiswa, m.nim, d.nama_dosen FROM mahasiswa m JOIN dosen d ON m.id_dosen_pa = d.id_dosen WHERE m.nim = ?");
$stmt_mhs->bind_param("s", $nim_mahasiswa_login);
$stmt_mhs->execute();
$mahasiswa = $stmt_mhs->get_result()->fetch_assoc();
if (!$mahasiswa) { die("Data mahasiswa tidak ditemukan."); }

// Ambil data pencapaian (milestones)
$daftar_pencapaian = ['Seminar Proposal', 'Penelitian Selesai', 'Seminar Hasil', 'Ujian Skripsi (Yudisium)', 'Publikasi Jurnal'];
$stmt_pencapaian = $conn->prepare("SELECT nama_pencapaian, status, tanggal_selesai FROM pencapaian WHERE nim_mahasiswa = ?");
$stmt_pencapaian->bind_param("s", $nim_mahasiswa_login);
$stmt_pencapaian->execute();
$result_pencapaian = $stmt_pencapaian->get_result();
$status_pencapaian = [];
$jumlah_selesai = 0;
while($row = $result_pencapaian->fetch_assoc()) {
    $status_pencapaian[$row['nama_pencapaian']] = $row;
    if ($row['status'] == 'Selesai') { $jumlah_selesai++; }
}
$total_pencapaian = count($daftar_pencapaian);
$persentase_kemajuan = ($total_pencapaian > 0) ? round(($jumlah_selesai / $total_pencapaian) * 100) : 0;

// Mulai membuat PDF
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Laporan Kemajuan Studi Mahasiswa', 0, 1, 'C');
        $this->Ln(5);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Dicetak pada ' . date('d M Y, H:i') . ' | Halaman ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Inisiasi PDF
$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Tampilkan detail mahasiswa
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'Nama Mahasiswa', 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, ': ' . $mahasiswa['nama_mahasiswa'], 0, 1);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'NIM', 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, ': ' . $mahasiswa['nim'], 0, 1);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'Dosen PA', 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, ': ' . $mahasiswa['nama_dosen'], 0, 1);
$pdf->Ln(10);

// Tampilkan Progress Bar
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'Persentase Kemajuan', 0, 1);
$pdf->SetFillColor(230, 230, 230); // Warna latar bar
$pdf->Rect(10, $pdf->GetY(), 190, 8, 'F'); // Latar bar
$pdf->SetFillColor(40, 167, 69); // Warna hijau untuk bar
if ($persentase_kemajuan > 0) {
    $pdf->Rect(10, $pdf->GetY(), 190 * ($persentase_kemajuan / 100), 8, 'F'); // Bar kemajuan
}
$pdf->SetY($pdf->GetY() - 0.5); // Sesuaikan posisi teks
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, $persentase_kemajuan . '% Selesai', 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(10);

// Tampilkan Checklist Pencapaian
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 10, 'Detail Pencapaian (Milestones)', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(20, 8, 'Status', 1, 0, 'C', true);
$pdf->Cell(120, 8, 'Nama Pencapaian', 1, 0, 'C', true);
$pdf->Cell(50, 8, 'Tanggal Selesai', 1, 1, 'C', true);

foreach ($daftar_pencapaian as $item) {
    $is_selesai = isset($status_pencapaian[$item]) && $status_pencapaian[$item]['status'] == 'Selesai';
    $tanggal = $is_selesai && !empty($status_pencapaian[$item]['tanggal_selesai']) 
               ? date('d F Y', strtotime($status_pencapaian[$item]['tanggal_selesai'])) 
               : '-';
    $status_icon = $is_selesai ? "Selesai" : "-";
    
    $pdf->Cell(20, 8, $status_icon, 1, 0, 'C');
    $pdf->Cell(120, 8, ' ' . $item, 1, 0);
    $pdf->Cell(50, 8, $tanggal, 1, 1, 'C');
}

// Tampilkan PDF di browser
$pdf->Output('I', 'Laporan_Kemajuan_' . $mahasiswa['nim'] . '.pdf');
$conn->close();
?>