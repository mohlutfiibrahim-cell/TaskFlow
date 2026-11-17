<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

if (taskflow_current_user_id() > 0) {
    taskflow_redirect('index.php');
}

$error = '';
$name = '';
$email = '';

try {
    $pdo = taskflow_db();
} catch (PDOException $exception) {
    error_log('TaskFlow database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Database TaskFlow belum siap. Periksa petunjuk instalasi di README.md.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(isset($_POST['name']) ? (string) $_POST['name'] : '');
    $email = strtolower(trim(isset($_POST['email']) ? (string) $_POST['email'] : ''));
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $passwordConfirmation = isset($_POST['password_confirmation']) ? (string) $_POST['password_confirmation'] : '';
    $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);

    if (!taskflow_verify_csrf($csrfToken)) {
        http_response_code(400);
        exit('Permintaan tidak valid. Muat ulang halaman, lalu coba lagi.');
    }

    if ($name === '' || $nameLength > 100) {
        $error = 'Nama wajib diisi dan maksimal 100 karakter.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $error = 'Masukkan alamat email yang valid.';
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = 'Password harus terdiri dari 8 sampai 72 karakter.';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'Konfirmasi password belum sama.';
    } else {
        $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)');

        try {
            $statement->execute(array(
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ));

            taskflow_sign_in(array(
                'id' => $pdo->lastInsertId(),
                'name' => $name,
            ));
            taskflow_set_flash('Akun berhasil dibuat. Selamat datang di TaskFlow!', 'success');
            taskflow_redirect('index.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $error = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';
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
    <title>Buat Akun — TaskFlow</title>
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
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
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
            <p class="auth-kicker">SATU LANGKAH LEBIH TERATUR</p>
            <h1>Bikin ruang<br>untuk fokus.</h1>
            <p class="auth-story-copy">Buat akun gratis dan mulai mengatur tugas kuliahmu dengan cara yang lebih sederhana.</p>
            <div class="auth-story-note">
                <span class="auth-note-mark" aria-hidden="true">✦</span>
                <span>Daftarkan akunmu, lalu semua tugasmu ada di satu tempat.</span>
            </div>
        </section>

        <section class="auth-card" aria-labelledby="register-title">
            <p class="auth-card-kicker">MULAI GRATIS</p>
            <h2 id="register-title">Buat akun</h2>
            <p class="auth-card-copy">Hanya perlu beberapa detail untuk memulai.</p>

            <?php if ($error !== ''): ?>
                <div class="flash-message flash-error auth-error" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                <label for="name">Nama</label>
                <input id="name" name="name" type="text" value="<?php echo e($name); ?>" autocomplete="name" maxlength="100" placeholder="Nama lengkap" required>

                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?php echo e($email); ?>" autocomplete="email" maxlength="254" placeholder="nama@email.com" required>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Minimal 8 karakter" required>

                <label for="password_confirmation">Ulangi password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Masukkan password sekali lagi" required>

                <button class="auth-submit" type="submit">Buat akun <span aria-hidden="true">→</span></button>
            </form>

            <p class="auth-switch">Sudah punya akun? <a href="login.php">Masuk</a></p>
        </section>
    </main>
</body>
</html>
