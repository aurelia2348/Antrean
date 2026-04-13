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
    <title>History - Queue Simulation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-0">Riwayat Simulasi (Daftar Sesi)</h2>
            <p class="text-muted mb-0">Pilih sesi Vaksinasi untuk melihat hasil akhir secara spesifik.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="reset_all.php" class="btn btn-danger d-flex align-items-center gap-2 shadow-sm" onclick="return confirm('KOSONGKAN SEMUA DATA?\nAnda yakin ingin menghapus SELURUH riwayat dan mengulang sistem simulasi kembali dari Vaksinasi 1? Aksi ini akan melenyapkan semua rekaman pasien selama-lamanya!');">
                <span>💥</span> Reset Sistem Global
            </a>
            <a href="index.php" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm">
                <span>⬅</span> Kembali ke Dasbor
            </a>
        </div>
    </div>

    <div class="card border-0 shadow rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0 align-middle">
                    <thead class="table-dark text-center">
                        <tr>
                            <th class="py-3">Vaksinasi-ID</th>
                            <th>Tanggal & Waktu Mulai</th>
                            <th>Jumlah Pasien Tercatat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-center bg-white">
                        <?php if (empty($sessions)): ?>
                            <tr><td colspan="4" class="text-muted py-5 fst-italic">Belum ada sesi rekaman simulasi.</td></tr>
                        <?php else: ?>
                            <?php foreach($sessions as $s): ?>
                                <tr>
                                    <td class="fw-bold fs-5 text-primary"><?php echo htmlspecialchars($s['name']); ?></td>
                                    <td class="text-secondary fw-bold" style="font-family: monospace; font-size: 1.1rem;">
                                        <?php 
                                            if ($s['start_time']) {
                                                echo date('d M Y - H:i', (int)($s['start_time'] / 1000));
                                            } else {
                                                echo '<span class="text-muted fst-italic">TBA</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary fs-6 rounded-pill px-3"><?php echo $s['patient_count']; ?> Pasien</span>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="results.php?session=<?php echo urlencode($s['id']); ?>" class="btn btn-sm btn-info shadow-sm fw-bold">
                                                Lihat Hasil Simulasi ➔
                                            </a>
                                            <form method="POST" action="delete.php" onsubmit="return confirm('HAPUS DATA?\nApakah Anda yakin ingin menghapus Riwayat Simulasi ini? Semua data pasien di sesi ini akan hangus dan tidak dapat dikembalikan.');">
                                                <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($s['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm fw-bold">🗑 Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
