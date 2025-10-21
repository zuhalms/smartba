<?php
// templates/report_header.php
// Pastikan Anda memiliki file logo di folder assets/ dengan nama logo_uin.png
$pdf->Image('assets/logo_uin.png', 10, 8, 25); 
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 7, 'KEMENTERIAN AGAMA REPUBLIK INDONESIA', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 7, 'UNIVERSITAS ISLAM NEGERI PALOPO', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Jalan Agatis Kel. Balandai Kec. Bara Kota Palopo, Sulawesi Selatan 91914', 0, 1, 'C');
$pdf->Cell(0, 5, 'Website: www.uinpalopo.ac.id', 0, 1, 'C');
$pdf->Line(10, 36, 200, 36); // Garis kop surat
$pdf->Ln(10); // Spasi setelah kop surat
?>