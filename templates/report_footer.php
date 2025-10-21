<?php
// templates/report_footer.php
$pdf->Ln(15); // Spasi sebelum tanda tangan
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(120); // Pindah ke kanan
$pdf->Cell(0, 7, 'Palopo, ' . date('d F Y'), 0, 1, 'L');
$pdf->Cell(120);
$pdf->Cell(0, 7, 'Dosen Pembimbing Akademik,', 0, 1, 'L');
$pdf->Ln(20); // Spasi untuk tanda tangan
$pdf->Cell(120);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, htmlspecialchars($dosen_pa_name), 0, 1, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(120);
$pdf->Cell(0, 7, 'NIDN: ' . htmlspecialchars($dosen_pa_nidn), 0, 1, 'L');
?>