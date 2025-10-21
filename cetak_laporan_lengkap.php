<?php
session_start();

// Keamanan: Pastikan yang mengakses adalah dosen yang login dan ada NIM
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'dosen' || !isset($_GET['nim'])) {
    exit('Akses ditolak. Silakan login terlebih dahulu.');
}

// Pastikan library FPDF ada dan di-require sekali saja.
require_once('fpdf/fpdf.php'); 

// Koneksi ke database
$host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'db_pa_akademi';
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

$nim = $_GET['nim'];
$id_dosen = $_SESSION['user_id'];

// === AMBIL SEMUA DATA YANG DIPERLUKAN ===

// 1. Data Mahasiswa dan Dosen PA
$mhs_stmt = $conn->prepare("SELECT m.nama_mahasiswa, p.nama_prodi, d.nama_dosen, d.nidn_dosen FROM mahasiswa m JOIN dosen d ON m.id_dosen_pa = d.id_dosen JOIN program_studi p ON m.id_prodi = p.id_prodi WHERE m.nim = ? AND m.id_dosen_pa = ?");
$mhs_stmt->bind_param("si", $nim, $id_dosen);
$mhs_stmt->execute();
$mhs_data = $mhs_stmt->get_result()->fetch_assoc();
if (!$mhs_data) { exit('Data mahasiswa tidak ditemukan atau Anda tidak memiliki akses.'); }
$dosen_pa_name = $mhs_data['nama_dosen'];
$dosen_pa_nidn = $mhs_data['nidn_dosen'];

// 2. Data Riwayat Bimbingan (Logbook)
$logbook_result = $conn->query("SELECT * FROM logbook WHERE nim_mahasiswa = '{$nim}' ORDER BY tanggal_bimbingan ASC");

// 3. Data Kemajuan Studi (Milestones)
$daftar_pencapaian = ['Seminar Proposal', 'Penelitian Selesai', 'Seminar Hasil', 'Ujian Skripsi (Yudisium)', 'Publikasi Jurnal'];
$result_pencapaian = $conn->query("SELECT nama_pencapaian, status, tanggal_selesai FROM pencapaian WHERE nim_mahasiswa = '{$nim}'");
$status_pencapaian = [];
while($row = $result_pencapaian->fetch_assoc()) { $status_pencapaian[$row['nama_pencapaian']] = $row; }

// 4. Data Nilai Bermasalah (C/D/E)
$nilai_kritis_result = $conn->query("SELECT nama_mk, semester_diambil, nilai_huruf FROM nilai_bermasalah WHERE nim_mahasiswa = '{$nim}' ORDER BY semester_diambil ASC");


// ### PERUBAHAN UTAMA: Modifikasi Class PDF untuk Tabel Dinamis ###
class PDF_MC_Table extends FPDF {
    var $widths;
    var $aligns;

    function SetWidths($w) { $this->widths = $w; }
    function SetAligns($a) { $this->aligns = $a; }

    function Row($data) {
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $h = 5 * $nb;
        $this->CheckPageBreak($h);
        for ($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, 5, $data[$i], 0, $a);
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function CheckPageBreak($h) {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++;
                continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
    
    function Footer() {
        $this->SetY(-15); $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Dokumen ini dicetak melalui sistem SMART-BA pada ' . date('d/m/Y H:i'),0,0,'L');
        $this->Cell(0,10,'Halaman '.$this->PageNo(),0,0,'R');
    }
}
// ### AKHIR PERUBAHAN CLASS PDF ###

$pdf = new PDF_MC_Table('P', 'mm', 'A4');
$pdf->AddPage();

// === KOP SURAT (Hanya sekali di halaman pertama) ===
// Diasumsikan Anda memiliki file ini untuk header
// include 'templates/report_header.php';
// Jika tidak, Anda bisa membuat header manual di sini
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'UNIVERSITAS ISLAM NEGERI KOTA PALOPO', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, 'FAKULTAS SYARIAH DAN HUKUM', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Jl. Agatis II, Balandai, Kec. Bara, Kota Palopo, Sulawesi Selatan 91914', 0, 1, 'C');
$pdf->SetLineWidth(1);
$pdf->Line(10, 36, 200, 36);
$pdf->SetLineWidth(0.2);
$pdf->Line(10, 37, 200, 37);
$pdf->Ln(10);


// === INFORMASI UMUM MAHASISWA ===
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'LAPORAN LENGKAP MAHASISWA', 0, 1, 'C');
$pdf->Ln(5);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(40, 7, 'Nama Mahasiswa', 0, 0); $pdf->Cell(5, 7, ':', 0, 0); $pdf->Cell(0, 7, $mhs_data['nama_mahasiswa'], 0, 1);
$pdf->Cell(40, 7, 'NIM', 0, 0); $pdf->Cell(5, 7, ':', 0, 0); $pdf->Cell(0, 7, $nim, 0, 1);
$pdf->Cell(40, 7, 'Program Studi', 0, 0); $pdf->Cell(5, 7, ':', 0, 0); $pdf->Cell(0, 7, $mhs_data['nama_prodi'], 0, 1);
$pdf->Ln(10);

// === BAGIAN 1: RIWAYAT BIMBINGAN (Menggunakan Class Baru) ===
$pdf->SetFont('Arial', 'B', 12); $pdf->SetFillColor(230, 230, 230);
$pdf->Cell(0, 10, 'BAGIAN I: RIWAYAT BIMBINGAN AKADEMIK', 1, 1, 'C', true);

// Set lebar kolom
$pdf->SetWidths([10, 25, 50, 105]);
// Set alignment header
$pdf->SetAligns(['C', 'C', 'C', 'C']);
// Header
$pdf->SetFont('Arial', 'B', 10);
$pdf->Row(['No', 'Tanggal', 'Topik', 'Pembahasan & Tindak Lanjut']);

// Data
$pdf->SetFont('Arial', '', 10);
$pdf->SetAligns(['C', 'C', 'L', 'L']);
$no = 1;
if ($logbook_result->num_rows > 0) {
    while ($log = $logbook_result->fetch_assoc()) {
        $pembahasan = "Pembahasan:\n" . trim($log['isi_bimbingan']);
        if (!empty($log['tindak_lanjut'])) {
            $pembahasan .= "\n\nTindak Lanjut:\n" . trim($log['tindak_lanjut']);
        }
        $pdf->Row([
            $no++,
            date('d-m-Y', strtotime($log['tanggal_bimbingan'])),
            trim($log['topik_bimbingan']),
            $pembahasan
        ]);
    }
} else {
    $pdf->Cell(190, 10, 'Tidak ada riwayat bimbingan.', 1, 1, 'C');
}
$pdf->Ln(10);

// === BAGIAN 2: KEMAJUAN STUDI ===
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'BAGIAN II: KEMAJUAN STUDI (MILESTONES)', 1, 1, 'C', true);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(10, 8, 'No', 1, 0, 'C'); $pdf->Cell(100, 8, 'Pencapaian', 1, 0, 'C');
$pdf->Cell(40, 8, 'Status', 1, 0, 'C'); $pdf->Cell(40, 8, 'Tanggal Selesai', 1, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$no = 1;
foreach ($daftar_pencapaian as $item) {
    $status = 'Belum Selesai'; $tanggal = '-';
    if (isset($status_pencapaian[$item]) && $status_pencapaian[$item]['status'] == 'Selesai') {
        $status = 'Selesai'; $tanggal = !empty($status_pencapaian[$item]['tanggal_selesai']) ? date('d-m-Y', strtotime($status_pencapaian[$item]['tanggal_selesai'])) : '-';
    }
    $pdf->Cell(10, 8, $no++, 1, 0, 'C'); $pdf->Cell(100, 8, $item, 1, 0);
    $pdf->Cell(40, 8, $status, 1, 0, 'C'); $pdf->Cell(40, 8, $tanggal, 1, 1, 'C');
}
$pdf->Ln(10);

// === BAGIAN 3: NILAI BERMASALAH ===
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'BAGIAN III: LAPORAN NILAI BERMASALAH (C/D/E)', 1, 1, 'C', true);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(15, 8, 'No', 1, 0, 'C'); $pdf->Cell(115, 8, 'Nama Mata Kuliah', 1, 0, 'C');
$pdf->Cell(30, 8, 'Semester', 1, 0, 'C'); $pdf->Cell(30, 8, 'Nilai', 1, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$no = 1;
if ($nilai_kritis_result->num_rows > 0) {
    while ($nilai = $nilai_kritis_result->fetch_assoc()) {
        $pdf->Cell(15, 8, $no++, 1, 0, 'C'); $pdf->Cell(115, 8, $nilai['nama_mk'], 1, 0);
        $pdf->Cell(30, 8, $nilai['semester_diambil'], 1, 0, 'C');
        $pdf->SetFont('Arial', 'B', 10); $pdf->SetTextColor(220, 53, 69); // Merah
        $pdf->Cell(30, 8, $nilai['nilai_huruf'], 1, 1, 'C');
        $pdf->SetFont('Arial', '', 10); $pdf->SetTextColor(0, 0, 0);
    }
} else { $pdf->Cell(190, 8, 'Tidak ada laporan nilai bermasalah.', 1, 1, 'C'); }

// === TANDA TANGAN ===
// Diasumsikan Anda memiliki file ini untuk footer tanda tangan
// include 'templates/report_footer.php';
// Jika tidak, Anda bisa membuat tanda tangan manual di sini
$pdf->Ln(20);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(120, 7, '', 0, 0);
$pdf->Cell(0, 7, 'Palopo, ' . date('d F Y'), 0, 1, 'L');
$pdf->Cell(120, 7, '', 0, 0);
$pdf->Cell(0, 7, 'Dosen Pembimbing Akademik,', 0, 1, 'L');
$pdf->Ln(20);
$pdf->Cell(120, 7, '', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, $dosen_pa_name, 0, 1, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(120, 7, '', 0, 0);
$pdf->Cell(0, 7, 'NIDN: ' . $dosen_pa_nidn, 0, 1, 'L');


$pdf->Output('D', 'Laporan_Lengkap_' . $nim . '.pdf');
$conn->close();
?>