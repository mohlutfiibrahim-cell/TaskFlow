<?php
require_once __DIR__ . '/auth.php';

$accountMode = isset($_GET['mode']) && $_GET['mode'] === 'akun';

if (taskflow_is_demo_mode() || taskflow_current_user_id() > 0) {
    taskflow_redirect('index.php');
}

$error = '';
$email = '';
$pdo = null;

if ($accountMode) {
    require_once __DIR__ . '/config.php';
    try {
        $pdo = taskflow_db();
    } catch (PDOException $exception) {
        error_log('TaskFlow database connection failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Database TaskFlow belum siap. Periksa petunjuk instalasi di README.md.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(isset($_POST['email']) ? (string) $_POST['email'] : '');
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    if (!taskflow_verify_csrf($csrfToken)) {
        http_response_code(400);
        exit('Permintaan tidak valid. Muat ulang halaman, lalu coba lagi.');
    }

    if ($accountMode) {
        $email = strtolower($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $error = 'Email atau password salah.';
        } else {
            $statement = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1');
            $statement->execute(array(':email' => $email));
            $user = $statement->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                taskflow_sign_in($user);
                taskflow_redirect('index.php');
            }

            $error = 'Email atau password salah.';
        }
    } else {
        $nameLength = function_exists('mb_strlen') ? mb_strlen($email, 'UTF-8') : strlen($email);
        if ($email === '' || $nameLength > 100 || $password === '') {
            $error = 'Nama dan password wajib diisi; nama maksimal 100 karakter.';
        } else {
            $name = $email;
            if (strpos($email, '@') !== false) {
                $name = strstr($email, '@', true);
            }
            $name = trim($name);
            taskflow_sign_in_demo($name !== '' ? $name : 'Mahasiswa');
            taskflow_redirect('index.php');
        }
    }
}

$csrfToken = taskflow_csrf_token();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f6f5fb">
    <title>Masuk — TaskFlow</title>
    <script>
        var savedTheme = window.localStorage.getItem('taskflow-theme');
        if (savedTheme === 'dark' || savedTheme === 'light') {
            document.documentElement.setAttribute('data-theme', savedTheme);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body class="auth-page">
    <button class="theme-toggle auth-theme-toggle" id="theme-toggle" type="button" aria-label="Aktifkan tema gelap">
        <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.6 8.6 0 1 0 20.2 15.1Z"/></svg>
        <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
        <span class="theme-toggle-label">Mode gelap</span>
    </button>

    <main class="auth-layout">
        <section class="auth-story">
            <a class="brand auth-brand" href="login.php" aria-label="TaskFlow">
                <span class="brand-mark" aria-hidden="true">T</span>
                <span>task<span class="brand-light">flow</span></span>
            </a>
            <p class="auth-kicker">RUANG BELAJAR PRIBADIMU</p>
            <h1>Semua tugasmu,<br>lebih terarah.</h1>
            <p class="auth-story-copy">Simpan tugas kuliah, atur deadline, dan lihat progresmu dari satu tempat.</p>
            <div class="auth-story-note">
                <span class="auth-note-mark" aria-hidden="true">✦</span>
                <span>Mulai dari satu tugas. Lanjutkan dengan konsisten.</span>
            </div>
        </section>

        <section class="auth-card" aria-labelledby="login-title">
            <p class="auth-card-kicker"><?php echo $accountMode ? 'AKUN TASKFLOW' : 'SELAMAT DATANG'; ?></p>
            <h2 id="login-title">Masuk ke TaskFlow</h2>
            <p class="auth-card-copy"><?php echo $accountMode ? 'Masuk menggunakan email dan password akunmu.' : 'Satu ruang untuk tugas, deadline, dan progres kuliahmu.'; ?></p>

            <?php if ($error !== ''): ?>
                <div class="flash-message flash-error auth-error" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo $accountMode ? 'login.php?mode=akun' : 'login.php'; ?>" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                <label for="email"><?php echo $accountMode ? 'Email' : 'Nama / Email'; ?></label>
                <input id="email" name="email" type="<?php echo $accountMode ? 'email' : 'text'; ?>" value="<?php echo e($email); ?>" autocomplete="<?php echo $accountMode ? 'email' : 'off'; ?>" maxlength="<?php echo $accountMode ? '254' : '100'; ?>" placeholder="<?php echo $accountMode ? 'nama@email.com' : 'Nama atau alamat email'; ?>" required>

                <div class="auth-label-row">
                    <label for="password">Password</label>
                </div>
                <input id="password" name="password" type="password" autocomplete="<?php echo $accountMode ? 'current-password' : 'off'; ?>" placeholder="Password" required>

                <button class="auth-submit" type="submit">Masuk <span aria-hidden="true">→</span></button>
            </form>

            <?php if ($accountMode): ?>
                <p class="auth-switch">Belum punya akun? <a href="register.php">Buat akun</a><br><a href="login.php">Kembali ke akses demo</a></p>
            <?php else: ?>
                <p class="auth-switch">TaskFlow · Ruang belajar produktif<br><a href="login.php?mode=akun">Masuk dengan akun terdaftar</a></p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
