<?php
date_default_timezone_set('Asia/Jakarta');
$file = 'results.json';
$data = [];

if (file_exists($file)) {
    $fileData = file_get_contents($file);
    if (!empty($fileData)) {
        $raw = json_decode($fileData, true) ?: [];
        $targetSession = isset($_GET['session']) ? intval($_GET['session']) : 1;
        
        foreach ($raw as $u) {
            $sid = isset($u['session_id']) ? $u['session_id'] : 1;
            if ($sid == $targetSession) {
                $data[] = $u;
            }
        }
        
        // Sort user data ascending by ID
        usort($data, function($a, $b) {
            return $a['id'] <=> $b['id'];
        });
    }
}

function formatDuration($ms) {
    if ($ms == -1) return 'Tidak Lanjut';
    if ($ms <= 0) return '00:00:00';
    $totalSeconds = round($ms / 1000);
    $hours = floor($totalSeconds / 3600);
    $minutes = floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

function calculateStageWait($history, $stageNum) {
    foreach ($history as $h) {
        if ($h['stage'] == $stageNum && isset($h['masuk_queue']) && isset($h['masuk_stage'])) {
            return $h['masuk_stage'] - $h['masuk_queue'];
        }
    }
    return -1; 
}

function calculateStageService($history, $stageNum) {
    foreach ($history as $h) {
        if ($h['stage'] == $stageNum && isset($h['masuk_stage']) && isset($h['keluar_stage'])) {
            return $h['keluar_stage'] - $h['masuk_stage'];
        }
    }
    return -1; 
}

function getStageEntryTime($user, $stageNum) {
    foreach ($user['history'] as $h) {
        if ($h['stage'] == $stageNum && isset($h['masuk_stage'])) {
            return $h['masuk_stage'];
        }
    }
    return -1;
}

function calculateStageSpecificInterarrival($data, $currentIndex, $stageNum) {
    $currEntry = getStageEntryTime($data[$currentIndex], $stageNum);
    if ($currEntry == -1) return 'Tidak Lanjut';
    
    $nextEntry = -1;
    for ($j = $currentIndex + 1; $j < count($data); $j++) {
        $entry = getStageEntryTime($data[$j], $stageNum);
        if ($entry != -1) {
            $nextEntry = $entry;
            break;
        }
    }
    
    if ($nextEntry != -1) {
        $diff = max(0, $nextEntry - $currEntry);
        return formatDuration($diff);
    }
    
    return formatDuration(0);
}

function getArrivalTime($user) {
    foreach ($user['history'] as $h) {
        if ($h['stage'] == 1 && isset($h['masuk_queue'])) {
            return $h['masuk_queue'];
        }
    }
    return 0;
}

function formatTimestamp($ms) {
    if ($ms == 0) return '-';
    return date('H.i.s', (int)($ms / 1000));
}

function getTotalTime($user) {
    $firstQueue = 0;
    foreach ($user['history'] as $h) {
        if ($h['stage'] == 1 && isset($h['masuk_queue'])) {
            $firstQueue = $h['masuk_queue'];
            break;
        }
    }
    
    if ($firstQueue > 0 && isset($user['selesai'])) {
        return $user['selesai'] - $firstQueue;
    }
    return 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report - Queue Simulation</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: #f8f9fa; }
        .table-custom th { background-color: #2c3e50; color: white; border-color: #34495e; font-weight: 600; }
        .table-custom td { vertical-align: middle; }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-0">Hasil Simulasi (Vaksinasi #<?php echo isset($targetSession) ? $targetSession : 1; ?>)</h2>
            <p class="text-muted mb-0">Data ini difilter spesifik untuk batch sesi ini.</p>
        </div>
        <a href="history.php" class="btn btn-primary d-flex align-items-center gap-2">
            <span>⬅</span> Kembali ke Riwayat
        </a>
    </div>

    <div class="card border-0 shadow rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered table-custom mb-0">
                    <thead class="text-center">
                        <tr>
                            <th rowspan="2" class="align-middle fs-5">Pasien ID</th>
                            <th rowspan="2" class="align-middle fs-5 text-nowrap" style="background-color: #16a085; color: white;">Arrival Time</th>
                            <th rowspan="2" class="align-middle fs-5 text-nowrap" style="background-color: #8e44ad; color: white;">Global Interarrival<br><small class="fw-normal">(Masuk Sistem)</small></th>
                            <th colspan="4" class="py-3 text-white" style="background-color: #9b59b6;">Interarrival Time (Masuk Stage)</th>
                            <th colspan="5" class="py-3 text-white" style="background-color: #34495e;">Waiting Time (Masuk Stage - Masuk Queue)</th>
                            <th colspan="5" class="py-3 text-white" style="background-color: #2980b9;">Service Time (Keluar Stage - Masuk Stage)</th>
                            <th rowspan="2" class="align-middle fs-5">Total Time (Selesai - Masuk Queue 1)</th>
                        </tr>
                        <tr>
                            <th style="background-color: #9b59b6; color: white;">Stage 1</th>
                            <th style="background-color: #9b59b6; color: white;">Stage 2</th>
                            <th style="background-color: #9b59b6; color: white;">Stage 3</th>
                            <th style="background-color: #9b59b6; color: white;">Stage 4</th>
                            
                            <th style="background-color: #34495e; color: white;">Stage 1</th>
                            <th style="background-color: #34495e; color: white;">Stage 2</th>
                            <th style="background-color: #34495e; color: white;">Stage 3</th>
                            <th style="background-color: #34495e; color: white;">Stage 4</th>
                            <th style="background-color: #2c3e50; color: white;">Total WQ</th>
                            
                            <th style="background-color: #2980b9; color: white;">Stage 1</th>
                            <th style="background-color: #2980b9; color: white;">Stage 2</th>
                            <th style="background-color: #2980b9; color: white;">Stage 3</th>
                            <th style="background-color: #2980b9; color: white;">Stage 4</th>
                            <th style="background-color: #2471a3; color: white;">Total SRV</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php 
                            $sWait = [1=>0, 2=>0, 3=>0, 4=>0, 'total'=>0];
                            $cWait = [1=>0, 2=>0, 3=>0, 4=>0, 'total'=>0];
                            $sSrv = [1=>0, 2=>0, 3=>0, 4=>0, 'total'=>0];
                            $cSrv = [1=>0, 2=>0, 3=>0, 4=>0, 'total'=>0];
                            $sTot = 0; $cTot = 0;
                        ?>
                        <?php if (empty($data)): ?>
                            <tr><td colspan="18" class="text-muted py-5 fst-italic">Belum ada data pasien yang selesai (completed Stage 4). Silakan jalankan simulasi.</td></tr>
                        <?php else: ?>
                            <?php foreach($data as $index => $user): ?>
                                <tr>
                                    <td class="fw-bold fs-5 text-primary align-middle border-end">#<?php echo $user['id']; ?></td>
                                    
                                    <td class="align-middle fw-bold border-end" style="color: #16a085; font-family: monospace; font-size: 1.1rem;">
                                        <?php echo formatTimestamp(getArrivalTime($user)); ?>
                                    </td>

                                    <td class="align-middle fw-bold border-end" style="color: #8e44ad; font-family: monospace; font-size: 1.1rem;">
                                        <?php 
                                            $nextUser = isset($data[$index + 1]) ? $data[$index + 1] : null;
                                            $currArr = getArrivalTime($user);
                                            $nextArr = $nextUser ? getArrivalTime($nextUser) : 0;
                                            
                                            $interarrival = 0;
                                            if ($nextArr > 0 && $currArr > 0) {
                                                $interarrival = $nextArr - $currArr;
                                            }
                                            
                                            echo formatDuration($interarrival);
                                        ?>
                                    </td>
                                    
                                    <!-- Interarrival Times (Per Stage) -->
                                    <?php for($i = 1; $i <= 4; $i++): ?>
                                        <td class="align-middle fw-bold" style="color: #8e44ad; background-color: #fcf6fd; border-right: 1px solid #dee2e6;">
                                            <?php 
                                            // Call calculateStageSpecificInterarrival
                                            $val = calculateStageSpecificInterarrival($data, $index, $i);
                                            if ($val == 'Tidak Lanjut') {
                                                echo '<span class="badge bg-secondary fs-6 rounded-3 px-3 py-2 shadow-sm">Tidak Lanjut</span>';
                                            } else {
                                                echo $val;
                                            }
                                            ?>
                                        </td>
                                    <?php endfor; ?>
                                    
                                    <!-- Waiting Times -->
                                    <?php $totalWait = 0; ?>
                                    <?php for($i = 1; $i <= 4; $i++): ?>
                                        <?php 
                                            $wait = calculateStageWait($user['history'], $i); 
                                            if ($wait != -1) {
                                                $totalWait += $wait;
                                                if ($wait > 0) { $sWait[$i] += $wait; $cWait[$i]++; }
                                            }
                                        ?>
                                        <td class="align-middle" style="background-color: #fdfefe;">
                                            <?php if ($wait == -1): ?>
                                                <span class="badge bg-secondary fs-6 rounded-3 px-3 py-2 shadow-sm">
                                                    Tidak Lanjut
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-<?php echo ($wait > 10000) ? 'danger' : (($wait > 4000) ? 'warning text-dark' : 'success'); ?> fs-6 rounded-3 px-3 py-2 shadow-sm">
                                                    <?php echo formatDuration($wait); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                    
                                    <?php if ($totalWait > 0) { $sWait['total'] += $totalWait; $cWait['total']++; } ?>
                                    <td class="align-middle" style="background-color: #fdfefe; border-left: 2px solid #34495e;">
                                        <span class="badge bg-dark fs-6 rounded-3 px-3 py-2 shadow-sm">
                                            <?php echo formatDuration($totalWait); ?>
                                        </span>
                                    </td>
                                    
                                    <!-- Service Times -->
                                    <?php $totalService = 0; ?>
                                    <?php for($i = 1; $i <= 4; $i++): ?>
                                        <?php 
                                            $service = calculateStageService($user['history'], $i); 
                                            if ($service != -1) {
                                                $totalService += $service;
                                                if ($service > 0) { $sSrv[$i] += $service; $cSrv[$i]++; }
                                            }
                                        ?>
                                        <td class="align-middle" style="background-color: #f4f9f9; border-left: 1px solid #dee2e6;">
                                            <?php if ($service == -1): ?>
                                                <span class="badge bg-secondary fs-6 rounded-3 px-3 py-2 shadow-sm">
                                                    Tidak Lanjut
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-info text-dark fs-6 rounded-3 px-3 py-2 shadow-sm">
                                                    <?php echo formatDuration($service); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>

                                    <?php if ($totalService > 0) { $sSrv['total'] += $totalService; $cSrv['total']++; } ?>
                                    <td class="align-middle" style="background-color: #f4f9f9; border-left: 2px solid #2980b9;">
                                        <span class="badge bg-primary text-white fs-6 rounded-3 px-3 py-2 shadow-sm">
                                            <?php echo formatDuration($totalService); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="bg-light align-middle border-start">
                                        <h4 class="mb-0 text-dark fw-bold">
                                            <?php 
                                                $tSys = getTotalTime($user);
                                                if ($tSys > 0) { $sTot += $tSys; $cTot++; }
                                                echo formatDuration($tSys); 
                                            ?>
                                        </h4>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- RATA - RATA -->
                            <tr style="background-color: #e9ecef; border-top: 3px solid #dee2e6;">
                                <td colspan="7" class="fw-bold fs-5 text-end pe-4 align-middle text-dark">RATA - RATA :</td>
                                
                                <!-- Wait Times -->
                                <?php for($i = 1; $i <= 4; $i++): ?>
                                    <td class="align-middle fw-bold text-dark" style="background-color: #fdfefe;">
                                        <?php echo $cWait[$i] > 0 ? formatDuration($sWait[$i] / $cWait[$i]) : '-'; ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="align-middle fw-bold text-dark fs-6" style="background-color: #fdfefe; border-left: 2px solid #34495e;">
                                    <?php echo $cWait['total'] > 0 ? formatDuration($sWait['total'] / $cWait['total']) : '-'; ?>
                                </td>
                                
                                <!-- Service Times -->
                                <?php for($i = 1; $i <= 4; $i++): ?>
                                    <td class="align-middle fw-bold text-dark" style="background-color: #f4f9f9; border-left: 1px solid #dee2e6;">
                                        <?php echo $cSrv[$i] > 0 ? formatDuration($sSrv[$i] / $cSrv[$i]) : '-'; ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="align-middle fw-bold text-primary fs-6" style="background-color: #f4f9f9; border-left: 2px solid #2980b9;">
                                    <?php echo $cSrv['total'] > 0 ? formatDuration($sSrv['total'] / $cSrv['total']) : '-'; ?>
                                </td>
                                
                                <!-- Total System Time -->
                                <td class="bg-light align-middle border-start">
                                    <h5 class="mb-0 text-dark fw-bold">
                                        <?php echo $cTot > 0 ? formatDuration($sTot / $cTot) : '-'; ?>
                                    </h5>
                                </td>
                            </tr>
                            
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Desktop DES Section -->
    <div class="mt-5 border-top pt-5">
        <div class="d-flex align-items-center mb-4">
            <h2 class="fw-bold mb-0 me-3">📈 Discrete Event Simulation (DES)</h2>
            <span class="badge bg-primary fs-6 rounded-pill shadow-sm py-2 px-3">Predictive Analysis</span>
        </div>
        
        <p class="text-secondary lead mb-4">
            Engineered using historical queue data from Vaksinasi #<?php echo isset($targetSession) ? $targetSession : 1; ?> to simulate future states and analyze hypothetical system limits.
        </p>

        <!-- Control Panel -->
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);">
            <div class="card-body p-4">
                <div class="row align-items-end mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark">REPS (Replikasi)</label>
                        <input type="number" id="desReps" class="form-control form-control-lg border-0 shadow-sm rounded-3" value="200">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark">WARMUP (Abaikan)</label>
                        <input type="number" id="desWarmup" class="form-control form-control-lg border-0 shadow-sm rounded-3" value="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark">OBS (Diobservasi)</label>
                        <input type="number" id="desObs" class="form-control form-control-lg border-0 shadow-sm rounded-3" value="30">
                    </div>
                </div>
                
                <div class="p-3 bg-white rounded-4 shadow-sm border border-light">
                    <h6 class="fw-bold mb-3 text-secondary border-bottom pb-2">⚙️ Advanced Scenario (G/G/c)</h6>
                    <div class="row align-items-end">
                        <div class="col-md-4 mb-2 mb-md-0">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.9rem;">Batas Kapasitas per Jam (Quota)</label>
                            <input type="number" id="desQuota" class="form-control border-light bg-light" value="0" placeholder="0 = Unlimited">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold text-dark mb-2" style="font-size: 0.9rem;">Jumlah Server (Pelayan) per Tahap</label>
                            <div class="d-flex gap-2">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-light text-muted" style="font-size: 0.8rem;">Stg 1</span>
                                    <input type="number" id="srv1" class="form-control border-light" value="1" min="1">
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-light text-muted" style="font-size: 0.8rem;">Stg 2</span>
                                    <input type="number" id="srv2" class="form-control border-light" value="1" min="1">
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-light text-muted" style="font-size: 0.8rem;">Stg 3</span>
                                    <input type="number" id="srv3" class="form-control border-light" value="1" min="1">
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-light text-muted" style="font-size: 0.8rem;">Stg 4</span>
                                    <input type="number" id="srv4" class="form-control border-light" value="1" min="1">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button id="btnStartDes" class="btn btn-primary btn-lg shadow rounded-pill fw-bold px-5">🌟 Start Simulation</button>
                    <button id="btnResetDes" class="btn btn-outline-danger btn-lg shadow-sm rounded-pill px-4">Reset</button>
                </div>
            </div>
        </div>

        <!-- Results Container -->
        <div id="desResultsContainer" class="d-none animate-fade-in">
            <!-- Table 1 & 3 row -->
            <div class="row g-4 mb-4">
                <!-- Table 1: Per Stage Performance -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow rounded-4 h-100">
                        <div class="card-header bg-white border-0 pt-4 pb-0">
                            <h5 class="fw-bold text-dark">📊 Table 1: Per Stage Performance</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-secondary">
                                    <tr>
                                        <th>Stage</th>
                                        <th>Avg Waiting Time</th>
                                        <th>Avg Service Time</th>
                                    </tr>
                                </thead>
                                <tbody id="t1Body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Table 3: Overal System -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
                        <div class="card-header border-0 pt-4 pb-0 bg-transparent">
                            <h5 class="fw-bold text-white">🌐 Table 3: Overall System Metrics</h5>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center">
                            <div class="row text-center mt-2 flex-grow-1 align-items-center">
                                <div class="col-6 mb-4">
                                    <h6 class="text-white-50 text-uppercase fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">Wq (Wait Queue)</h6>
                                    <h2 class="fw-bold mb-0 text-warning" id="sysWq">0</h2>
                                </div>
                                <div class="col-6 mb-4">
                                    <h6 class="text-white-50 text-uppercase fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">W (Total Time)</h6>
                                    <h2 class="fw-bold mb-0 text-warning" id="sysW">0</h2>
                                </div>
                                <div class="col-6">
                                    <h6 class="text-white-50 text-uppercase fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">Lq (Queue Length)</h6>
                                    <h2 class="fw-bold mb-0 text-info" id="sysLq">0</h2>
                                </div>
                                <div class="col-6">
                                    <h6 class="text-white-50 text-uppercase fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">L (System Length)</h6>
                                    <h2 class="fw-bold mb-0 text-info" id="sysL">0</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table 2: Queue Parameters -->
            <div class="card border-0 shadow rounded-4 mb-5">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="fw-bold text-dark">📈 Table 2: Queue Parameters per Stage</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center mb-4">
                            <thead class="table-light text-secondary">
                                <tr>
                                    <th>Stage</th>
                                    <th>Ai <br><small>(Mean Arrival)</small></th>
                                    <th>σAi <br><small>(Std. Dev)</small></th>
                                    <th>CAi <br><small>(CoV)</small></th>
                                    <th>Si <br><small>(Mean Service)</small></th>
                                    <th>σSi <br><small>(Std. Dev)</small></th>
                                    <th>CSi <br><small>(CoV)</small></th>
                                    <th>λi <br><small>(Arrival Rate)</small></th>
                                    <th>ρi <br><small>(Utilization)</small></th>
                                    <th>μi <br><small>(Service Rate)</small></th>
                                </tr>
                            </thead>
                            <tbody id="t2Body"></tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-info border-0 rounded-4 shadow-sm bg-opacity-25" style="background-color: #e3f2fd;" role="alert">
                        <div class="d-flex align-items-center mb-2">
                            <h5 class="alert-heading fw-bold mb-0 text-primary">💡 Insight Teori Antrean</h5>
                        </div>
                        <hr class="border-primary opacity-25 mt-0">
                        <div class="text-dark">
                            <div class="mb-4">
                                <h6 class="fw-bold mb-2" style="color: #2c3e50;">Tingkat Keacakan Waktu (CA / CS)</h6>
                                <p class="mb-2 text-muted" style="font-size: 0.95rem;">Parameter ini mengukur seberapa besar fluktuasi (ketidakpastian) waktu antrean terbentuk maupun waktu layanan diselesaikan.</p>
                                <ul class="mb-0 text-muted" style="font-size: 0.95rem;">
                                    <li><strong>Bernilai &gt; 1</strong> : Durasi layanan sangat bervariasi. Hal ini secara matematis akan memicu penumpukan antrean yang sulit diprediksi.</li>
                                    <li><strong>Bernilai &lt; 1</strong> : Laju kedatangan tipe pasien dan waktu penanganan tergolong cukup konsisten dan stabil.</li>
                                </ul>
                            </div>
                            
                            <div>
                                <h6 class="fw-bold mb-2" style="color: #2c3e50;">Beban Kapasitas Layanan (ρ / Utilisasi)</h6>
                                <p class="mb-2 text-muted" style="font-size: 0.95rem;">Parameter ini menunjukkan utilisasi (proporsi) kesibukan staf terhadap jumlah pasien yang harus ditanggung pada tahap tertentu.</p>
                                <ul class="mb-0 text-muted" style="font-size: 0.95rem;">
                                    <li><strong>Di bawah 1</strong> : Kapasitas tenaga kerja masih sangat memadai untuk menangani kedatangan pasien.</li>
                                    <li><strong>Mendekati 1</strong> : Beban kerja staf mencapai batas maksimal tanpa jeda istirahat; antrean rentan terbentuk.</li>
                                    <li><strong>Melebihi 1</strong> : Kapasitas berlebih (<em>Overload / Bottleneck</em>). Volume pasien jauh melampaui kemampuan maksimal staf, dipastikan menyebabkan kemacetan sistemik.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div id="kingmanWarning" class="alert alert-danger border-danger shadow-sm rounded-4 d-none mt-4" style="background-color: #fff5f5;">
                        <h5 class="fw-bold mb-2 text-danger">⚠️ Peringatan Kestabilan Sistem & Analitik G/G/1</h5>
                        <p class="mb-0 text-dark" id="kingmanWarningText"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.animate-fade-in {
    animation: fadeIn 0.5s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
    const targetSessionId = <?php echo isset($targetSession) ? $targetSession : 1; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="des.js"></script>

</body>
</html>
