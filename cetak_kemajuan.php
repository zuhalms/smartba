<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['nim'])) { exit('Akses ditolak'); }

require('fpdf/fpdf.php');
$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);

$nim = $_GET['nim'];
$id_dosen = $_SESSION['user_id'];

// Ambil data mahasiswa dan dosen
$mhs_data = $conn->query("SELECT m.nama_mahasiswa, p.nama_prodi FROM mahasiswa m JOIN program_studi p ON m.id_prodi = p.id_prodi WHERE m.nim = '$nim'")->fetch_assoc();
$dosen_data = $conn->query("SELECT nama_dosen, nidn_dosen FROM dosen WHERE id_dosen = $id_dosen")->fetch_assoc();
$dosen_pa_name = $dosen_data['nama_dosen'];
$dosen_pa_nidn = $dosen_data['nidn_dosen'];

// Ambil data kemajuan
$daftar_pencapaian = ['Seminar Proposal', 'Penelitian Selesai', 'Seminar Hasil', 'Ujian Skripsi (Yudisium)', 'Publikasi Jurnal'];
$result_pencapaian = $conn->query("SELECT nama_pencapaian, status, tanggal_selesai FROM pencapaian WHERE nim_mahasiswa = '$nim'");
$status_pencapaian = [];
while($row = $result_pencapaian->fetch_assoc()) { $status_pencapaian[$row['nama_pencapaian']] = $row; }

class PDF extends FPDF {
    function Header() { /* Dibiarkan kosong karena kita pakai template */ }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Halaman '.$this->PageNo(),0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
include 'templates/report_header.php';

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'LAPORAN KEMAJUAN STUDI MAHASISWA', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(40, 7, 'Nama Mahasiswa', 0, 0); $pdf->Cell(5, 7, ':', 0, 0); $pdf->Cell(0, 7, $mhs_data['nama_mahasiswa'], 0, 1);
$pdf->Cell(40, 7, 'NIM', 0, 0); $pdf->Cell(5, 7, ':', 0, 0); $pdf->Cell(0, 7, $nim, 0, 1);
$pdf->Cell(40, 7, 'Program Studi', 0, 0); $pdf->Cell(5, 7, ':', 0, 0); $pdf->Cell(0, 7, $mhs_data['nama_prodi'], 0, 1);
$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
$pdf->Cell(100, 10, 'Pencapaian', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Status', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Tanggal Selesai', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 12);
$no = 1;
foreach ($daftar_pencapaian as $item) {
    $status = 'Belum Selesai';
    $tanggal = '-';
    if (isset($status_pencapaian[$item]) && $status_pencapaian[$item]['status'] == 'Selesai') {
        $status = 'Selesai';
        $tanggal = !empty($status_pencapaian[$item]['tanggal_selesai']) ? date('d-m-Y', strtotime($status_pencapaian[$item]['tanggal_selesai'])) : '-';
    }
    $pdf->Cell(10, 10, $no++, 1, 0, 'C');
    $pdf->Cell(100, 10, $item, 1, 0);
    $pdf->Cell(40, 10, $status, 1, 0, 'C');
    $pdf->Cell(40, 10, $tanggal, 1, 1, 'C');
}

include 'templates/report_footer.php';
$pdf->Output('D', 'Laporan_Kemajuan_Studi_' . $nim . '.pdf');
$conn->close();
?>