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

// --- SEARCH LOGIC ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $sessions = array_filter($sessions, function($s) use ($search) {
        return stripos($s['name'], $search) !== false || stripos((string)$s['id'], $search) !== false;
    });
}

// --- SORTING LOGIC ---
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
usort($sessions, function($a, $b) use ($sort) {
    // Prioritize TBA (null start_time) to the top
    if ($a['start_time'] === null && $b['start_time'] !== null) return -1;
    if ($a['start_time'] !== null && $b['start_time'] === null) return 1;
    
    $timeA = $a['start_time'] ?? 0;
    $timeB = $b['start_time'] ?? 0;
    
    // If both are TBA or times are same, sort by ID descending
    if ($timeA == $timeB) {
        return $b['id'] <=> $a['id'];
    }
    
    return ($sort === 'oldest') ? ($timeA <=> $timeB) : ($timeB <=> $timeA);
});

// --- PAGINATION LOGIC ---
$limit = 7;
$total_items = count($sessions);
$total_pages = ceil($total_items / $limit);
$page = isset($_GET['page']) ? max(1, min($total_pages, (int)$_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$paginated_sessions = array_slice($sessions, $offset, $limit);
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
            <div class="brand-icon text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="bi bi-bar-chart-fill" style="font-size: 1.1rem;"></i>
            </div>
            <h5 class="fw-bold mb-0 text-dark" style="line-height: 1.2; letter-spacing: -0.5px;">QueueFlow<span class="text-primary">.</span><br><span class="fs-6 fw-semibold text-secondary">Pro Simulation</span></h5>
        </div>

        <!-- System Status -->
        <div class="px-4 py-3 mx-3 bg-light rounded-4 mb-4 d-flex align-items-center gap-3 border border-light shadow-sm">
            <div class="status-icon bg-white text-primary rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px; border: 1px solid rgba(79,70,229,0.15);">
                <i class="bi bi-cpu-fill fs-5"></i>
            </div>
            <div>
                <small class="text-uppercase text-secondary fw-bold" style="font-size: 0.6rem; letter-spacing: 0.8px;">SYSTEM ENGINE</small>
                <div class="fw-bold text-dark" style="font-size: 0.8rem; line-height: 1.2;">Simulation Core</div>
                <div class="text-primary fw-bold" style="font-size: 0.7rem;">v2.4 Active</div>
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
                <div class="d-flex gap-3 flex-shrink-0">
                    <a href="index.php" class="btn btn-primary fw-bold text-white px-4 py-2 d-flex align-items-center gap-2 shadow-sm rounded-2 border-0 text-nowrap">
                        <i class="bi bi-arrow-left"></i> Kembali ke Dasbor
                    </a>
                    <a href="reset_all.php" class="btn bg-white text-danger fw-bold px-4 py-2 d-flex align-items-center gap-2 shadow-sm rounded-2 border text-nowrap" onclick="return confirm('KOSONGKAN SEMUA DATA?\nAnda yakin ingin menghapus SELURUH riwayat dan mengulang sistem simulasi kembali dari Vaksinasi 1?');">
                        <i class="bi bi-arrow-clockwise"></i> Reset Sistem Global
                    </a>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-5">
                <!-- Toolbar -->
                <form method="GET" action="history.php" class="m-0">
                    <div class="d-flex justify-content-between align-items-center p-4 border-bottom bg-white flex-wrap gap-3">
                        <div class="position-relative" style="width: 300px;">
                            <i class="bi bi-search position-absolute top-50 translate-middle-y text-secondary ms-3"></i>
                            <input type="text" name="search" class="form-control bg-light border-0 ps-5 py-2 rounded-3 text-secondary" 
                                   placeholder="Cari ID Vaksinasi..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="dropdown">
                                <button class="btn btn-link text-dark text-decoration-none fw-semibold d-flex align-items-center gap-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-filter-right fs-5"></i> Filter Sesi: <?php echo ($sort === 'latest' ? 'Terbaru' : 'Terlama'); ?>
                                </button>
                                <ul class="dropdown-menu shadow border-0 rounded-3">
                                    <li><a class="dropdown-item fw-medium py-2 <?php echo $sort === 'latest' ? 'active bg-primary' : ''; ?>" href="?search=<?php echo urlencode($search); ?>&sort=latest">Terbaru</a></li>
                                    <li><a class="dropdown-item fw-medium py-2 <?php echo $sort === 'oldest' ? 'active bg-primary' : ''; ?>" href="?search=<?php echo urlencode($search); ?>&sort=oldest">Terlama</a></li>
                                </ul>
                            </div>
                            <?php if ($search !== '' || $sort !== 'latest'): ?>
                                <a href="history.php" class="btn btn-sm text-secondary text-decoration-none small">Reset Filter</a>
                            <?php endif; ?>
                            <button type="submit" class="d-none"></button>
                        </div>
                    </div>
                </form>

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
                            <?php if (empty($paginated_sessions)): ?>
                                <tr>
                                    <td colspan="4" class="text-muted py-5 text-center fst-italic">Tidak ditemukan data yang sesuai pencarian.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($paginated_sessions as $s): ?>
                                    <tr>
                                        <td class="px-4 py-4 border-light text-nowrap">
                                            <span class="text-primary fw-bold"><?php echo htmlspecialchars($s['name']); ?></span>
                                        </td>
                                        <td class="px-4 py-4 border-light text-dark fw-medium">
                                            <?php 
                                                if ($s['start_time']) {
                                                    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
                                                    $m = (int)date('n', (int)($s['start_time'] / 1000)) - 1;
                                                    echo date('d ', (int)($s['start_time'] / 1000)) . $months[$m] . date(' Y, H:i ', (int)($s['start_time'] / 1000)) . ' WIB';
                                                } else {
                                                    echo '<span class="text-muted fst-italic">TBA</span>';
                                                }
                                            ?>
                                        </td>
                                        <td class="px-4 py-4 border-light text-dark fw-medium">
                                            <?php echo $s['patient_count']; ?>
                                        </td>
                                        <td class="px-4 py-4 border-light text-center">
                                            <div class="d-flex justify-content-center align-items-center gap-3 text-nowrap">
                                                <a href="results.php?session=<?php echo urlencode($s['id']); ?>" class="btn btn-sm shadow-none text-decoration-none text-nowrap" style="background-color: #e0f2fe; color: #0284c7; font-weight: 600; border-radius: 20px; padding: 6px 16px; font-size: 0.8rem;">
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

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center p-4 border-top bg-light flex-wrap gap-3">
                    <div class="text-secondary" style="font-size: 0.85rem;">
                        <?php if ($total_items > 0): ?>
                            Menampilkan <span class="fw-bold text-dark"><?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $total_items); ?></span> dari <span class="fw-bold text-dark"><?php echo $total_items; ?></span> sesi
                        <?php else: ?>
                            Tidak ada data untuk ditampilkan
                        <?php endif; ?>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex gap-2">
                            <!-- Prev -->
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" 
                                   class="btn btn-white border bg-white shadow-sm fw-bold px-3 py-1 rounded-2 text-secondary pagination-link">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-white border bg-white shadow-sm fw-bold px-3 py-1 rounded-2 text-secondary opacity-50" disabled>
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                            <?php endif; ?>

                            <!-- Pages -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" 
                                   class="btn <?php echo ($i === $page) ? 'btn-primary' : 'btn-white border bg-white text-secondary'; ?> fw-bold px-3 py-1 rounded-2 shadow-sm border-0 pagination-link">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Next -->
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" 
                                   class="btn btn-white border bg-white shadow-sm fw-bold px-3 py-1 rounded-2 text-secondary pagination-link">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-white border bg-white shadow-sm fw-bold px-3 py-1 rounded-2 text-secondary opacity-50" disabled>
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
