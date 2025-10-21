<?php
// Mulai sesi jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil nama pengguna untuk ditampilkan di Navbar
$user_name = '';
if (isset($_SESSION['user_role'])) {
    $conn_header = new mysqli('localhost', 'root', '', 'db_pa_akademi');
    if (!$conn_header->connect_error) {
        if ($_SESSION['user_role'] == 'dosen') {
            $stmt = $conn_header->prepare("SELECT nama_dosen FROM dosen WHERE id_dosen = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $user_name = $stmt->get_result()->fetch_assoc()['nama_dosen'] ?? 'Dosen';
        } elseif ($_SESSION['user_role'] == 'mahasiswa') {
            $stmt = $conn_header->prepare("SELECT nama_mahasiswa FROM mahasiswa WHERE nim = ?");
            $stmt->bind_param("s", $_SESSION['user_id']);
            $stmt->execute();
            $user_name = $stmt->get_result()->fetch_assoc()['nama_mahasiswa'] ?? 'Mahasiswa';
        }
        $conn_header->close();
    }
}

// Tentukan halaman dashboard utama berdasarkan peran
$dashboard_link = ($_SESSION['user_role'] == 'dosen') ? 'dashboard_dosen.php' : 'dashboard_mahasiswa.php';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>SMART-BA</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        /* Smart & Green Campus theme (typography-focused enhancements) */
        :root {
            --campus-green: #1E6A59; /* primary */
            --campus-green-2: #145046; /* darker */
            --campus-accent: #E6F6F2; /* soft background */
            --muted: #6c757d;
            --card-radius: 12px;
            --brand-font: 'Playfair Display', serif;
            --ui-font: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
        }
        html, body {
            font-family: var(--ui-font);
            color: #0f2b25;
            font-size: 15.5px;
            line-height: 1.65;
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
            background-color: #fbfdfb;
        }
        /* Headings use Playfair Display for a scholarly, elegant look */
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--brand-font);
            color: var(--campus-green-2);
            margin-top: 0.25rem;
            margin-bottom: 0.5rem;
            letter-spacing: 0.2px;
            line-height: 1.2;
            font-weight: 600;
        }
        h1 { font-size: 2.05rem; }
        h2 { font-size: 1.6rem; }
        h3 { font-size: 1.25rem; }
        .lead { font-size: 1.03rem; color: #275146; }

        .navbar-brand { font-family: var(--brand-font); letter-spacing: 1px; font-weight: 600; color: #fff !important; font-size: 1.25rem; }
        .navbar { background: linear-gradient(90deg, var(--campus-green), var(--campus-green-2)); }

        .btn-primary { background: var(--campus-green); border-color: var(--campus-green); }
        .btn-primary:hover { background: var(--campus-green-2); border-color: var(--campus-green-2); }
        .bg-primary { background-color: var(--campus-green) !important; }
        .text-primary { color: var(--campus-green) !important; }

        .card { border-radius: var(--card-radius); }
        .card.shadow-sm { box-shadow: 0 8px 26px rgba(12,20,16,0.06); transition: transform .18s ease, box-shadow .18s ease; }
        .card.shadow-sm:hover { transform: translateY(-4px); }

        .small-muted { color: var(--muted); }
        footer { background: linear-gradient(180deg, #ffffff, var(--campus-accent)); }

        .notif-badge { position: absolute; top: 0; right: -8px; font-size: 0.65em; padding: 0.18em 0.45em; }
        a.nav-link { color: rgba(255,255,255,0.94); }
        a.nav-link:hover { color: #fff; }
        .navbar-text { color: rgba(255,255,255,0.92); }

        /* subtle glass button for profile/logout */
        .btn-outline-light { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.12); color: #fff; }
        .btn-outline-light:hover { background: rgba(255,255,255,0.12); }

        /* utility: make small headings stand out in cards */
        .card h5, .card h6 { color: var(--campus-green-2); }

        /* link accent */
        a { color: var(--campus-green); }
        a:hover { color: var(--campus-green-2); text-decoration: underline; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="<?= $dashboard_link; ?>"><strong>SMART-BA</strong></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= $dashboard_link; ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="profil.php">Profil Saya</a></li>
                <?php if ($_SESSION['user_role'] == 'mahasiswa'): ?>
                <li class="nav-item"><a class="nav-link" href="input_riwayat.php">Lengkapi Riwayat</a></li>
                <?php endif; ?>
            </ul>
            <a href="<?= $dashboard_link ?>" class="text-white me-3 position-relative" title="Lihat Notifikasi" style="text-decoration: none; font-size: 1.4rem;">
                <i class="bi bi-bell-fill"></i>
                <span id="notifCount" class="badge rounded-pill bg-danger notif-badge" style="display: none;">0</span>
            </a>
            <span class="navbar-text me-3">Halo, <?= htmlspecialchars(strtok($user_name, " ")); ?>!</span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<main>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const notifBadge = document.getElementById('notifCount');
        function checkNotifications() {
            fetch('check_notif.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        notifBadge.innerText = data.count;
                        notifBadge.style.display = 'block';
                    } else {
                        notifBadge.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }
        checkNotifications();
        setInterval(checkNotifications, 7000); 
    });
</script>