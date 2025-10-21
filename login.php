<?php
// Selalu mulai sesi di awal
session_start();

// Jika sudah login, langsung arahkan ke dashboard yang sesuai
if (isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] == 'mahasiswa') {
        header("Location: dashboard_mahasiswa.php");
        exit();
    } elseif ($_SESSION['user_role'] == 'dosen') {
        header("Location: dashboard_dosen.php"); 
        exit();
    }
}

$error_message = '';

// Hanya proses jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Konfigurasi Database
    $host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'db_pa_akademi';
    $conn = new mysqli($host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) { die("Koneksi ke database GAGAL: " . $conn->connect_error); }

    $username_input = $_POST['username'];
    $password = $_POST['password'];
    $username_clean = str_replace(' ', '', $username_input);

    // Cek di Tabel Mahasiswa
    // ### PERUBAHAN 1: Ambil juga 'nama_mahasiswa' ###
    $stmt = $conn->prepare("SELECT nim, nama_mahasiswa, password FROM mahasiswa WHERE REPLACE(nim, ' ', '') = ?");
    $stmt->bind_param("s", $username_clean);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['nim'];
            $_SESSION['user_role'] = 'mahasiswa';
            $_SESSION['user_name'] = $user['nama_mahasiswa']; // <-- Sesi nama ditambahkan
            header("Location: dashboard_mahasiswa.php");
            exit();
        } else {
            $error_message = "Password salah. Coba lagi.";
        }
    }

    // Cek di Tabel Dosen
    if (empty($error_message)) {
        $has_nip = false;
        $colCheck = $conn->query("SHOW COLUMNS FROM dosen LIKE 'nip'");
        if ($colCheck && $colCheck->num_rows > 0) { $has_nip = true; }

        if ($has_nip) {
            // ### PERUBAHAN 2: Ambil juga 'nama_dosen' ###
            $stmt_dosen = $conn->prepare("SELECT id_dosen, nama_dosen, nidn_dosen, COALESCE(nip, '') AS nip, password FROM dosen WHERE REPLACE(nidn_dosen, ' ', '') = ? OR REPLACE(nip, ' ', '') = ?");
            $stmt_dosen->bind_param("ss", $username_clean, $username_clean);
        } else {
            $stmt_dosen = $conn->prepare("SELECT id_dosen, nama_dosen, nidn_dosen, password FROM dosen WHERE REPLACE(nidn_dosen, ' ', '') = ?");
            $stmt_dosen->bind_param("s", $username_clean);
        }
        $stmt_dosen->execute();
        $result_dosen = $stmt_dosen->get_result();

        if ($result_dosen && $result_dosen->num_rows >= 1) {
            $user_dosen = $result_dosen->fetch_assoc();
            if (password_verify($password, $user_dosen['password'])) {
                $_SESSION['user_id'] = $user_dosen['id_dosen'];
                $_SESSION['user_role'] = 'dosen';
                $_SESSION['user_name'] = $user_dosen['nama_dosen']; // <-- Sesi nama ditambahkan
                if (!empty($user_dosen['nip'])) {
                    $_SESSION['user_nip'] = $user_dosen['nip'];
                } else {
                    $_SESSION['user_nidn'] = $user_dosen['nidn_dosen'];
                }
                header("Location: dashboard_dosen.php");
                exit();
            } else {
                $error_message = "Password salah. Coba lagi.";
            }
        }
    }

    if (empty($error_message)) {
       $error_message = "NIM / NIDN / NIP tidak ditemukan.";
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - SMART-BA</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --green-primary: #00A86B; /* Vibrant, modern green */
            --green-dark: #008F5A;
        }
        body, html { height: 100%; }
        body {
            font-family: 'Lato', sans-serif;
            background: linear-gradient(135deg, #2ddb90, #00A86B);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            width: 100%;
            max-width: 450px;
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        .card-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
        }
        .form-control {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
        }
        .form-control:focus {
            border-color: var(--green-primary);
            box-shadow: 0 0 0 0.25rem rgba(0, 168, 107, 0.25);
        }
        .input-group-text {
            background-color: transparent;
            border-radius: 0.75rem 0 0 0.75rem;
        }
        .btn-login {
            background-color: var(--green-primary);
            border-color: var(--green-primary);
            border-radius: 50px;
            padding: 0.75rem;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.2s;
        }
        .btn-login:hover {
            background-color: var(--green-dark);
            border-color: var(--green-dark);
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

    <div class="login-card p-4 p-md-5">
        <div class="text-center mb-4">
            <img src="assets/logo_uin.png" alt="Logo UIN Palopo" style="width: 70px; margin-bottom: 0.5rem;">
            <h6 class="fw-bold mt-2 mb-0" style="font-family: 'Inter', sans-serif; color: #555; font-size: 0.9rem;">
                Universitas Islam Negeri Kota Palopo
            </h6>
            <p class="mb-2" style="color: #666; font-size: 0.8rem;">Fakultas Syariah dan Hukum</p>
            <h3 class="card-title mt-3">SMART-BA</h3>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">NIM MAHASISWA / ID DOSEN </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan ID Anda" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                    <button type="button" class="btn btn-outline-secondary" id="togglePass" aria-label="Tampilkan password" style="border-radius: 0 0.75rem 0.75rem 0;">
                        <i class="bi bi-eye-fill"></i>
                    </button>
                </div>
            </div>
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-login">Login</button>
            </div>
        </form>
        <div class="text-center mt-4">
            <a href="index.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left-circle me-1"></i>Kembali ke Halaman Utama</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const togglePassword = document.getElementById('togglePass');
            const passwordInput = document.getElementById('password');
            const icon = togglePassword.querySelector('i');

            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                icon.classList.toggle('bi-eye-fill');
                icon.classList.toggle('bi-eye-slash-fill');
            });

            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        });
    </script>
</body>
</html>