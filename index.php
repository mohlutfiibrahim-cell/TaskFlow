<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

taskflow_require_login();
$isDemoMode = taskflow_is_demo_mode();
$userId = taskflow_current_user_id();
$csrfToken = taskflow_csrf_token();
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Mahasiswa';
$userInitial = function_exists('mb_substr') ? mb_substr($userName, 0, 1, 'UTF-8') : substr($userName, 0, 1);

if (!$isDemoMode) {
    try {
        taskflow_db();
    } catch (PDOException $exception) {
        error_log('TaskFlow database connection failed: ' . $exception->getMessage());
        http_response_code(500);
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>TaskFlow - Koneksi database</title>
            <link rel="stylesheet" href="assets/css/style.css">
        </head>
        <body class="setup-page">
            <main class="setup-card">
                <span class="brand-mark" aria-hidden="true">T</span>
                <p class="eyebrow">TaskFlow · Persiapan</p>
                <h1>Database belum siap</h1>
                <p>TaskFlow belum dapat terhubung ke MySQL. Nyalakan MySQL di XAMPP. Untuk instalasi baru, impor <code>database.sql</code>; untuk database versi lama, impor <code>database-auth-migration.sql</code> satu kali melalui phpMyAdmin.</p>
                <p class="setup-hint">Pengaturan koneksi default: host <code>127.0.0.1</code>, user <code>root</code>, tanpa password, database <code>taskflow</code>.</p>
            </main>
        </body>
        </html>
        <?php
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!taskflow_verify_csrf($submittedToken)) {
        http_response_code(400);
        exit('Permintaan tidak valid. Muat ulang halaman, lalu coba lagi.');
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'add') {
        $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
        $course = isset($_POST['course']) ? trim((string) $_POST['course']) : '';
        $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
        $dueDate = isset($_POST['due_date']) ? trim((string) $_POST['due_date']) : '';
        $priority = isset($_POST['priority']) ? (string) $_POST['priority'] : 'sedang';
        $titleLength = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        $courseLength = function_exists('mb_strlen') ? mb_strlen($course, 'UTF-8') : strlen($course);

        if ($title === '' || $titleLength > 180) {
            taskflow_set_flash('Nama tugas wajib diisi dan maksimal 180 karakter.', 'error');
            taskflow_redirect('index.php');
        }

        if ($courseLength > 120 || strlen($description) > 4000) {
            taskflow_set_flash('Mata kuliah maksimal 120 karakter dan deskripsi maksimal 4.000 byte.', 'error');
            taskflow_redirect('index.php');
        }

        if (!in_array($priority, array('rendah', 'sedang', 'tinggi'), true)) {
            taskflow_set_flash('Prioritas yang dipilih tidak valid.', 'error');
            taskflow_redirect('index.php');
        }

        if ($dueDate !== '') {
            $parsedDate = DateTime::createFromFormat('!Y-m-d', $dueDate);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $dueDate) {
                taskflow_set_flash('Tanggal deadline tidak valid.', 'error');
                taskflow_redirect('index.php');
            }
        } else {
            $dueDate = null;
        }

        if ($isDemoMode) {
            $taskId = (int) $_SESSION['demo_next_task_id'];
            $_SESSION['demo_next_task_id'] = $taskId + 1;
            $_SESSION['demo_tasks'][] = array(
                'id' => $taskId,
                'user_id' => 'demo',
                'title' => $title,
                'course' => $course !== '' ? $course : null,
                'description' => $description !== '' ? $description : null,
                'priority' => $priority,
                'due_date' => $dueDate,
                'completed' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            );
        } else {
            $statement = taskflow_db()->prepare(
                'INSERT INTO tasks (user_id, title, course, description, priority, due_date) VALUES (:user_id, :title, :course, :description, :priority, :due_date)'
            );
            $statement->execute(array(
                ':user_id' => $userId,
                ':title' => $title,
                ':course' => $course !== '' ? $course : null,
                ':description' => $description !== '' ? $description : null,
                ':priority' => $priority,
                ':due_date' => $dueDate,
            ));
        }

        taskflow_set_flash('Tugas berhasil ditambahkan ke daftar.', 'success');
        taskflow_redirect('index.php');
    }

    if ($action === 'toggle') {
        $taskId = filter_var(isset($_POST['task_id']) ? $_POST['task_id'] : null, FILTER_VALIDATE_INT);
        if (!$taskId || $taskId < 1) {
            taskflow_set_flash('Tugas yang dipilih tidak valid.', 'error');
            taskflow_redirect('index.php');
        }

        if ($isDemoMode) {
            $updated = false;
            foreach ($_SESSION['demo_tasks'] as &$task) {
                if ((int) $task['id'] === $taskId) {
                    $task['completed'] = 1 - (int) $task['completed'];
                    $updated = true;
                    break;
                }
            }
            unset($task);
        } else {
            $statement = taskflow_db()->prepare('UPDATE tasks SET completed = 1 - completed WHERE id = :id AND user_id = :user_id');
            $statement->execute(array(':id' => $taskId, ':user_id' => $userId));
            $updated = $statement->rowCount() > 0;
        }
        taskflow_set_flash(
            $updated ? 'Status tugas berhasil diperbarui.' : 'Tugas tidak ditemukan atau bukan milik akun ini.',
            $updated ? 'success' : 'error'
        );
        taskflow_redirect('index.php');
    }

    if ($action === 'delete_completed') {
        $taskId = filter_var(isset($_POST['task_id']) ? $_POST['task_id'] : null, FILTER_VALIDATE_INT);
        if (!$taskId || $taskId < 1) {
            taskflow_set_flash('Tugas yang dipilih tidak valid.', 'error');
            taskflow_redirect('index.php');
        }

        if ($isDemoMode) {
            $deleted = false;
            foreach ($_SESSION['demo_tasks'] as $index => $task) {
                if ((int) $task['id'] === $taskId && (int) $task['completed'] === 1) {
                    unset($_SESSION['demo_tasks'][$index]);
                    $_SESSION['demo_tasks'] = array_values($_SESSION['demo_tasks']);
                    $deleted = true;
                    break;
                }
            }
        } else {
            $statement = taskflow_db()->prepare('DELETE FROM tasks WHERE id = :id AND completed = 1 AND user_id = :user_id');
            $statement->execute(array(':id' => $taskId, ':user_id' => $userId));
            $deleted = $statement->rowCount() > 0;
        }
        taskflow_set_flash(
            $deleted ? 'Tugas selesai berhasil dihapus.' : 'Tugas tidak ditemukan atau bukan milik akun ini.',
            $deleted ? 'success' : 'error'
        );
        taskflow_redirect('index.php');
    }

    http_response_code(400);
    exit('Aksi tidak dikenali.');
}

$filter = isset($_GET['filter']) ? (string) $_GET['filter'] : 'semua';
if (!in_array($filter, array('semua', 'aktif', 'selesai'), true)) {
    $filter = 'semua';
}

if ($isDemoMode) {
    $allTasks = isset($_SESSION['demo_tasks']) ? $_SESSION['demo_tasks'] : array();
    $counts = array('total' => count($allTasks), 'active' => 0, 'completed' => 0);
    foreach ($allTasks as $task) {
        if ((int) $task['completed'] === 1) {
            $counts['completed']++;
        } else {
            $counts['active']++;
        }
    }

    $tasks = array_values(array_filter($allTasks, function ($task) use ($filter) {
        return $filter === 'semua'
            || $filter === 'aktif' && (int) $task['completed'] === 0
            || $filter === 'selesai' && (int) $task['completed'] === 1;
    }));
    usort($tasks, function ($left, $right) {
        if ((int) $left['completed'] !== (int) $right['completed']) {
            return (int) $left['completed'] <=> (int) $right['completed'];
        }
        if (empty($left['due_date']) !== empty($right['due_date'])) {
            return empty($left['due_date']) ? 1 : -1;
        }
        if (!empty($left['due_date']) && $left['due_date'] !== $right['due_date']) {
            return strcmp($left['due_date'], $right['due_date']);
        }
        return strcmp($right['created_at'], $left['created_at']);
    });
} else {
    $countsStatement = taskflow_db()->prepare(
        'SELECT COUNT(*) AS total, COALESCE(SUM(completed = 0), 0) AS active, COALESCE(SUM(completed = 1), 0) AS completed FROM tasks WHERE user_id = :user_id OR user_id IS NULL'
    );
    $countsStatement->execute(array(':user_id' => $userId));
    $counts = $countsStatement->fetch(PDO::FETCH_ASSOC);

    $sql = 'SELECT id, user_id, title, course, description, priority, due_date, completed, created_at FROM tasks WHERE (user_id = :user_id OR user_id IS NULL)';
    if ($filter === 'aktif') {
        $sql .= ' AND completed = 0';
    } elseif ($filter === 'selesai') {
        $sql .= ' AND completed = 1';
    }
    $sql .= ' ORDER BY completed ASC, due_date IS NULL ASC, due_date ASC, created_at DESC';
    $tasksStatement = taskflow_db()->prepare($sql);
    $tasksStatement->execute(array(':user_id' => $userId));
    $tasks = $tasksStatement->fetchAll(PDO::FETCH_ASSOC);
}

$flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

$filterLabels = array(
    'semua' => 'Semua tugas',
    'aktif' => 'Belum selesai',
    'selesai' => 'Selesai',
);
$priorityLabels = array(
    'rendah' => 'Rendah',
    'sedang' => 'Sedang',
    'tinggi' => 'Tinggi',
);
$monthNames = array(
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
);
$weekdayNames = array(
    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
);
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f6f5fb">
    <title>TaskFlow — Atur tugas kuliahmu</title>
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
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="index.php" aria-label="TaskFlow beranda">
                <span class="brand-mark" aria-hidden="true">T</span>
                <span>task<span class="brand-light">flow</span></span>
            </a>

            <p class="sidebar-label">MENU</p>
            <nav class="side-nav is-expanded" id="task-navigation" aria-label="Navigasi tugas">
                <div class="nav-links" id="task-links">
                    <?php foreach ($filterLabels as $filterKey => $label): ?>
                        <a class="nav-link<?php echo $filter === $filterKey ? ' is-active' : ''; ?>"
                           href="index.php?filter=<?php echo e($filterKey); ?>"
                           <?php echo $filter === $filterKey ? 'aria-current="page"' : ''; ?>>
                            <span class="nav-icon nav-icon-<?php echo e($filterKey); ?>" aria-hidden="true">
                                <?php if ($filterKey === 'semua'): ?>
                                    <svg viewBox="0 0 24 24"><rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/></svg>
                                <?php elseif ($filterKey === 'aktif'): ?>
                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/></svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="m8.5 12 2.3 2.3 4.8-4.8"/></svg>
                                <?php endif; ?>
                            </span>
                            <span class="nav-label"><?php echo e($label); ?></span>
                            <span class="nav-count"><?php echo e($counts[$filterKey === 'semua' ? 'total' : ($filterKey === 'aktif' ? 'active' : 'completed')]); ?></span>
                        </a>
                    <?php endforeach; ?>
                    <a class="nav-link nav-add-link" href="#tambah-tugas">
                        <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
                        <span class="nav-label">Tambah tugas</span>
                    </a>
                </div>
                <button class="mobile-nav-toggle" type="button" aria-expanded="true" aria-controls="task-links" aria-label="Ciutkan navigasi">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
                </button>
            </nav>

            <div class="sidebar-note">
                <span class="note-sparkle" aria-hidden="true">✦</span>
                <p class="note-title">Sedikit demi sedikit.</p>
                <p>Fokus menyelesaikan satu tugas hari ini. Kamu pasti bisa!</p>
            </div>
            <div class="sidebar-footer"><span class="status-dot"></span> Ruang belajar produktif</div>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="breadcrumbs">Ruang belajar <span>/</span> <strong><?php echo e($filterLabels[$filter]); ?></strong></div>
                <div class="topbar-actions">
                    <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Aktifkan tema gelap">
                        <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.6 8.6 0 1 0 20.2 15.1Z"/></svg>
                        <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
                        <span class="theme-toggle-label">Mode gelap</span>
                    </button>
                    <div class="student-chip">
                        <span class="student-avatar" aria-hidden="true"><?php echo e($userInitial); ?></span>
                        <span><?php echo e($userName); ?></span>
                    </div>
                    <form class="logout-form" method="post" action="logout.php">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                        <button class="logout-button" type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                            <span>Keluar</span>
                        </button>
                    </form>
                </div>
            </header>

            <section class="welcome-section">
                <div>
                    <p class="eyebrow"><?php echo e($weekdayNames[(int) date('N')] . ', ' . date('d') . ' ' . $monthNames[(int) date('n')] . ' ' . date('Y')); ?></p>
                    <h1>Hai, semangat kuliahnya! <span class="wave" aria-hidden="true">✦</span></h1>
                    <p class="welcome-copy">Rencanakan tugasmu, satu langkah pada satu waktu.</p>
                </div>
            </section>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo e($flash['type']); ?>" role="status"><?php echo e($flash['message']); ?></div>
            <?php endif; ?>

            <section class="stats-grid" aria-label="Ringkasan tugas">
                <article class="stat-card stat-total">
                    <span class="stat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M8 5h11a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H8"/><path d="M5 8h10M5 12h10M5 16h6"/></svg>
                    </span>
                    <div><p>Total tugas</p><strong><?php echo e($counts['total']); ?></strong></div>
                    <span class="stat-caption">Semua mata kuliah</span>
                </article>
                <article class="stat-card stat-pending">
                    <span class="stat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/></svg>
                    </span>
                    <div><p>Belum selesai</p><strong><?php echo e($counts['active']); ?></strong></div>
                    <span class="stat-caption">Tetap fokus, ya</span>
                </article>
                <article class="stat-card stat-done">
                    <span class="stat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="m8.5 12 2.3 2.3 4.8-4.8"/></svg>
                    </span>
                    <div><p>Sudah selesai</p><strong><?php echo e($counts['completed']); ?></strong></div>
                    <span class="stat-caption">Kerja bagus!</span>
                </article>
            </section>

            <div class="content-grid">
                <section class="task-panel">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">DAFTAR TUGAS</p>
                            <h2><?php echo e($filterLabels[$filter]); ?></h2>
                        </div>
                        <span class="task-total"><?php echo e(count($tasks)); ?> tugas</span>
                    </div>

                    <nav class="filter-tabs" aria-label="Filter daftar tugas">
                        <?php foreach ($filterLabels as $filterKey => $label): ?>
                            <a class="filter-tab<?php echo $filter === $filterKey ? ' is-selected' : ''; ?>"
                               href="index.php?filter=<?php echo e($filterKey); ?>"
                               <?php echo $filter === $filterKey ? 'aria-current="page"' : ''; ?>>
                                <?php echo e($label); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <?php if (!$tasks): ?>
                        <div class="empty-state">
                            <span class="empty-icon" aria-hidden="true">✦</span>
                            <h3><?php echo $filter === 'selesai' ? 'Belum ada tugas selesai' : ($filter === 'aktif' ? 'Semua tugas sudah beres!' : 'Belum ada tugas'); ?></h3>
                            <p><?php echo $filter === 'selesai' ? 'Tugas yang kamu selesaikan akan muncul di sini.' : ($filter === 'aktif' ? 'Keren! Kamu bisa menambahkan tugas baru kapan saja.' : 'Tambahkan tugas kuliah pertamamu lewat formulir di samping.'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="task-list">
                            <?php foreach ($tasks as $task): ?>
                                <article class="task-item<?php echo $task['completed'] ? ' task-completed' : ''; ?><?php echo empty($task['user_id']) ? ' task-shared' : ''; ?>">
                                    <?php if (!empty($task['user_id'])): ?>
                                        <form method="post" class="task-check-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="task_id" value="<?php echo e($task['id']); ?>">
                                            <button class="task-check" type="submit" aria-label="<?php echo $task['completed'] ? 'Tandai belum selesai: ' : 'Tandai selesai: '; echo e($task['title']); ?>">
                                                <?php if ($task['completed']): ?><span aria-hidden="true">✓</span><?php endif; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="task-check task-check-locked" type="button" disabled aria-label="Tugas lama bersama, tidak dapat diubah">
                                            <?php if ($task['completed']): ?><span aria-hidden="true">✓</span><?php endif; ?>
                                        </button>
                                    <?php endif; ?>
                                    <div class="task-main">
                                        <div class="task-title-row">
                                            <h3><?php echo e($task['title']); ?></h3>
                                            <span class="priority priority-<?php echo e($task['priority']); ?>">
                                                <span class="priority-dot"></span><?php echo e($priorityLabels[$task['priority']]); ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($task['course'])): ?><p class="task-course"><?php echo e($task['course']); ?></p><?php endif; ?>
                                        <?php if (!empty($task['description'])): ?><p class="task-description"><?php echo nl2br(e($task['description'])); ?></p><?php endif; ?>
                                        <div class="task-meta">
                                            <?php if (!empty($task['due_date'])): ?>
                                                <span class="deadline<?php echo (!$task['completed'] && $task['due_date'] < date('Y-m-d')) ? ' deadline-late' : ''; ?>">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></svg>
                                                    Deadline <?php $dueTimestamp = strtotime($task['due_date']); echo e(date('d', $dueTimestamp) . ' ' . $monthNames[(int) date('n', $dueTimestamp)] . ' ' . date('Y', $dueTimestamp)); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="task-status"><?php echo $task['completed'] ? 'Selesai' : 'Belum selesai'; ?></span>
                                            <?php if (empty($task['user_id'])): ?>
                                                <span class="shared-task-badge">Tugas lama · bersama</span>
                                            <?php endif; ?>
                                            <?php if ($task['completed'] && !empty($task['user_id'])): ?>
                                                <form method="post" class="delete-task-form" data-confirm="Hapus tugas yang sudah selesai ini? Tindakan ini tidak dapat dibatalkan.">
                                                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="delete_completed">
                                                    <input type="hidden" name="task_id" value="<?php echo e($task['id']); ?>">
                                                    <button class="delete-task-button" type="submit" aria-label="Hapus tugas selesai: <?php echo e($task['title']); ?>">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m3 0-.8 13H6.8L6 7m4 4v5m4-5v5"/></svg>
                                                        <span>Hapus</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <aside class="form-panel" id="tambah-tugas">
                    <div class="form-heading">
                        <span class="form-heading-icon" aria-hidden="true">+</span>
                        <div>
                            <p class="eyebrow">MULAI DARI SINI</p>
                            <h2>Tambah tugas</h2>
                        </div>
                    </div>
                    <p class="form-intro">Catat tugas kuliahmu sebelum lupa.</p>
                    <form method="post" class="task-form">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                        <input type="hidden" name="action" value="add">
                        <label for="title">Nama tugas <span class="required-mark">*</span></label>
                        <input id="title" name="title" type="text" maxlength="180" placeholder="Contoh: Ringkasan jurnal" required>

                        <label for="course">Mata kuliah <span class="optional-mark">Opsional</span></label>
                        <input id="course" name="course" type="text" maxlength="120" placeholder="Contoh: Metode Penelitian">

                        <label for="description">Catatan <span class="optional-mark">Opsional</span></label>
                        <textarea id="description" name="description" rows="3" maxlength="4000" placeholder="Tambahkan detail kecil agar lebih jelas..."></textarea>

                        <div class="form-row">
                            <div>
                                <label for="due_date">Deadline <span class="optional-mark">Opsional</span></label>
                                <input id="due_date" name="due_date" type="date">
                            </div>
                            <div>
                                <label for="priority">Prioritas</label>
                                <select id="priority" name="priority">
                                    <option value="sedang">Sedang</option>
                                    <option value="tinggi">Tinggi</option>
                                    <option value="rendah">Rendah</option>
                                </select>
                            </div>
                        </div>
                        <button class="submit-button" type="submit"><span aria-hidden="true">+</span> Simpan tugas</button>
                        <p class="form-footnote"><span aria-hidden="true">⌁</span> <?php echo $isDemoMode ? 'Data demo tersimpan sementara selama sesi.' : 'Tugas hanya terlihat oleh akunmu.'; ?></p>
                    </form>
                </aside>
            </div>
            <footer class="page-footer">Dibuat untuk menemani perjalanan kuliahmu <span aria-hidden="true">✦</span></footer>
        </main>
    </div>
</body>
</html>
