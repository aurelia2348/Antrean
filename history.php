<?php
date_default_timezone_set('Asia/Jakarta');
$file = 'results.json';
$data = [];

if (file_exists($file)) {
    $fileData = file_get_contents($file);
    if (!empty($fileData)) {
        $data = json_decode($fileData, true) ?: [];
    }
}

$max_session_id = 1;
if (file_exists('max_session.txt')) {
    $max_session_id = (int)file_get_contents('max_session.txt');
}

// Group by session_id
$sessions = [];

$deleted_sessions = [];
if (file_exists('deleted_sessions.json')) {
    $content = file_get_contents('deleted_sessions.json');
    if (!empty($content)) {
        $deleted_sessions = json_decode($content, true) ?: [];
    }
}

// Initialize all sessions up to max_session_id so they always appear
for ($i = 1; $i <= $max_session_id; $i++) {
    if (in_array($i, $deleted_sessions)) continue;

    $sessions[$i] = [
        'id' => $i,
        'name' => 'Vaksinasi ' . $i,
        'start_time' => null,
        'patient_count' => 0
    ];
}

foreach ($data as $user) {
    // Fallback for old data without session_id
    $s_id = isset($user['session_id']) ? $user['session_id'] : 1;
    
    // In case the DB has a session ID higher than the txt file somehow
    if (!isset($sessions[$s_id])) {
        if (in_array($s_id, $deleted_sessions)) continue;
        
        $sessions[$s_id] = [
            'id' => $s_id,
            'name' => 'Vaksinasi ' . $s_id,
            'start_time' => null,
            'patient_count' => 0
        ];
    }
    
    $sessions[$s_id]['patient_count']++;
    
    // Find earliest queue time
    foreach ($user['history'] as $h) {
        if ($h['stage'] == 1 && isset($h['masuk_queue'])) {
            if ($sessions[$s_id]['start_time'] === null || $h['masuk_queue'] < $sessions[$s_id]['start_time']) {
                $sessions[$s_id]['start_time'] = $h['masuk_queue'];
            }
        }
    }
}

// Sort sessions naturally by descending ID (newest top)
krsort($sessions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QueueFlow Pro - History</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="style.css" rel="stylesheet">
</head>
<body class="bg-app">

<div class="d-flex vw-100 vh-100 overflow-hidden">
    
    <!-- Sidebar -->
    <aside class="sidebar bg-white border-end d-flex flex-column flex-shrink-0">
        <!-- Brand -->
        <div class="p-4 d-flex align-items-center gap-3">
            <div class="brand-icon bg-primary text-white rounded d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="bi bi-bar-chart-fill" style="font-size: 1rem;"></i>
            </div>
            <h5 class="fw-bold mb-0 text-dark">QueueFlow<br>Pro</h5>
        </div>

        <!-- System Status -->
        <div class="px-4 py-3 mx-2 bg-light-subtle rounded-3 mb-4 d-flex align-items-center gap-3">
            <div class="status-icon bg-primary-subtle text-primary rounded d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="bi bi-cpu"></i>
            </div>
            <div>
                <small class="text-uppercase text-secondary fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">SYSTEM ARCHITECT</small>
                <div class="fw-bold text-dark" style="font-size: 0.85rem;">Simulation Engine</div>
                <div class="text-primary fw-semibold" style="font-size: 0.7rem;">v2.4 Active</div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="nav flex-column gap-2 px-3 fw-semibold">
            <a href="index.php" class="nav-link text-secondary rounded-1 d-flex align-items-center gap-3 py-3 px-3">
                <i class="bi bi-grid-1x2-fill"></i> DASHBOARD
            </a>
            <a href="#" class="nav-link active rounded-1 d-flex align-items-center gap-3 py-3 px-3">
                <i class="bi bi-clock-history"></i> HISTORY
            </a>
            <a href="#" class="nav-link text-secondary rounded-1 d-flex align-items-center gap-3 py-3 px-3">
                <i class="bi bi-bar-chart-line-fill"></i> ANALYSIS
            </a>
        </nav>

        <!-- Spacer -->
        <div class="mt-auto border-top mx-3 mb-3 pt-3">
            <a href="index.php" class="btn btn-primary w-100 fw-bold d-flex align-items-center justify-content-center gap-2 mb-4 py-2">
                <i class="bi bi-plus-lg"></i> New Simulation
            </a>
            <a href="#" class="nav-link text-secondary rounded-1 d-flex align-items-center gap-3 px-3 py-2 fw-semibold">
                <i class="bi bi-gear-fill"></i> SETTINGS
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="d-flex flex-column flex-grow-1 overflow-auto bg-app">
        <div class="container-fluid p-5" style="max-width: 1100px;">
            
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Riwayat Simulasi (Daftar Sesi)</h2>
                    <p class="text-secondary mb-0">Kelola dan tinjau semua rekaman aktivitas simulasi sistem.</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="index.php" class="btn btn-primary fw-bold text-white px-4 py-2 d-flex align-items-center gap-2 shadow-sm rounded-2 border-0">
                        <i class="bi bi-arrow-left"></i> Kembali ke Dasbor
                    </a>
                    <a href="reset_all.php" class="btn bg-white text-danger fw-bold px-4 py-2 d-flex align-items-center gap-2 shadow-sm rounded-2 border" onclick="return confirm('KOSONGKAN SEMUA DATA?\nAnda yakin ingin menghapus SELURUH riwayat dan mengulang sistem simulasi kembali dari Vaksinasi 1?');">
                        <i class="bi bi-arrow-clockwise"></i> Reset Sistem Global
                    </a>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-5">
                <!-- Toolbar -->
                <div class="d-flex justify-content-between align-items-center p-4 border-bottom bg-white">
                    <div class="position-relative" style="width: 300px;">
                        <i class="bi bi-search position-absolute top-50 translate-middle-y text-secondary ms-3"></i>
                        <input type="text" class="form-control bg-light border-0 ps-5 py-2 rounded-3 text-secondary" placeholder="Cari ID Vaksinasi...">
                    </div>
                    <div>
                        <button class="btn btn-link text-dark text-decoration-none fw-semibold d-flex align-items-center gap-2">
                            <i class="bi bi-filter-right fs-5"></i> Filter Sesi
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle border-0">
                        <thead class="bg-light text-secondary" style="font-size: 0.75rem; letter-spacing: 1px;">
                            <tr>
                                <th class="py-3 px-4 fw-bold border-bottom-0 text-uppercase">VAKSINASI-ID</th>
                                <th class="py-3 px-4 fw-bold border-bottom-0 text-uppercase">TANGGAL & WAKTU MULAI</th>
                                <th class="py-3 px-4 fw-bold border-bottom-0 text-uppercase">JUMLAH PASIEN TERCATAT</th>
                                <th class="py-3 px-4 fw-bold border-bottom-0 text-uppercase text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                            <?php if (empty($sessions)): ?>
                                <tr>
                                    <td colspan="4" class="text-muted py-5 text-center fst-italic">Belum ada sesi rekaman simulasi.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($sessions as $s): ?>
                                    <tr>
                                        <td class="px-4 py-4 border-light">
                                            <span class="text-primary fw-bold"><?php echo htmlspecialchars($s['name']); ?></span>
                                        </td>
                                        <td class="px-4 py-4 border-light text-dark fw-medium">
                                            <?php 
                                                if ($s['start_time']) {
                                                    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
                                                    $m = (int)date('n', (int)($s['start_time'] / 1000)) - 1;
                                                    echo date('d ', (int)($s['start_time'] / 1000)) . $months[$m] . date(' Y, H:i ', (int)($s['start_time'] / 1000)) . 'WIB';
                                                } else {
                                                    echo '<span class="text-muted fst-italic">TBA</span>';
                                                }
                                            ?>
                                        </td>
                                        <td class="px-4 py-4 border-light text-dark fw-medium">
                                            <?php echo $s['patient_count']; ?>
                                        </td>
                                        <td class="px-4 py-4 border-light text-center">
                                            <div class="d-flex justify-content-center align-items-center gap-3">
                                                <a href="results.php?session=<?php echo urlencode($s['id']); ?>" class="btn btn-sm shadow-none text-decoration-none" style="background-color: #e0f2fe; color: #0284c7; font-weight: 600; border-radius: 20px; padding: 6px 16px; font-size: 0.8rem;">
                                                    Lihat Hasil Simulasi <i class="bi bi-arrow-right ms-1 fw-bold"></i>
                                                </a>
                                                <form method="POST" action="delete.php" class="m-0" onsubmit="return confirm('HAPUS DATA?\nApakah Anda yakin ingin menghapus Riwayat Simulasi ini? Semua data pasien di sesi ini akan hangus dan tidak dapat dikembalikan.');">
                                                    <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($s['id']); ?>">
                                                    <button type="submit" class="btn btn-link text-danger p-0 border-0 text-decoration-none" title="Hapus Sesi">
                                                        <i class="bi bi-trash3-fill fs-6" style="color: #ef4444;"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Static Pagination Placeholder -->
                <div class="d-flex justify-content-between align-items-center p-4 border-top bg-light">
                    <div class="text-secondary" style="font-size: 0.85rem;">
                        Menampilkan <span class="fw-bold text-dark">Semua sesi</span> riwayat simulasi
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-white border bg-white shadow-sm fw-bold px-3 py-1 rounded-2 text-secondary"><i class="bi bi-chevron-left"></i></button>
                        <button class="btn btn-primary fw-bold px-3 py-1 rounded-2 shadow-sm text-white border-0">1</button>
                        <button class="btn btn-white border bg-white shadow-sm fw-bold px-3 py-1 rounded-2 text-secondary">2</button>
                        <button class="btn btn-white border bg-white shadow-sm fw-bold px-3 py-1 rounded-2 text-secondary">3</button>
                        <button class="btn btn-white border bg-white shadow-sm fw-bold px-3 py-1 rounded-2 text-secondary"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

</body>
</html>
