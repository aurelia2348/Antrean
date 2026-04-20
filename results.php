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
    if ($ms == -1) return 'NR';
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
    if ($currEntry == -1) return -1;
    
    $nextEntry = -1;
    for ($j = $currentIndex + 1; $j < count($data); $j++) {
        $entry = getStageEntryTime($data[$j], $stageNum);
        if ($entry != -1) {
            $nextEntry = $entry;
            break;
        }
    }
    
    if ($nextEntry != -1) {
        return max(0, $nextEntry - $currEntry);
    }
    
    return 0;
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
    return date('H:i:s', (int)($ms / 1000));
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

// Calculate Summary Metrics
$totalPatients = count($data);
$simMin = PHP_INT_MAX;
$simMax = 0;

foreach ($data as $user) {
    if (isset($user['history'])) {
        foreach ($user['history'] as $h) {
            if ($h['stage'] == 1 && isset($h['masuk_queue'])) {
                $simMin = min($simMin, $h['masuk_queue']);
            }
            if ($h['stage'] == 4 && isset($h['keluar_stage'])) {
                $simMax = max($simMax, $h['keluar_stage']);
            }
        }
    }
}
$simTimeStr = '00h 00m';
if ($simMax > $simMin && $simMin !== PHP_INT_MAX) {
    $diffSec = round(($simMax - $simMin) / 1000);
    $h = floor($diffSec / 3600);
    $m = floor(($diffSec % 3600) / 60);
    $simTimeStr = sprintf('%02dh %02dm', $h, $m);
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
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; background-color: #eff6ff; color: #1e293b; }
        .table-custom th { color: white; font-weight: 700; font-size: 0.75rem; letter-spacing: 0.5px; border-color: rgba(255,255,255,0.1); }
        .table-custom td { vertical-align: middle; color: #334155; font-size: 0.9rem; padding: 1rem 0.75rem; border-color: #f1f5f9; }
        .report-card { border-radius: 12px; background: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border: none; }
        .text-purple { color: #8b5cf6 !important; }
        .text-warning-red { color: #ef4444 !important; } /* Simulated warnings for high times */
    </style>
</head>
<body class="bg-light">

<div class="container-fluid py-5 px-md-5" style="max-width: 1400px; background-color: #f8fafc;">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-5">
        <div>
            <div class="text-uppercase fw-bold text-secondary mb-2" style="font-size: 0.7rem; letter-spacing: 1.5px;">REPORTS <i class="bi bi-chevron-right mx-1"></i> SIMULATION RESULTS</div>
            <h1 class="fw-bold mb-3" style="font-size: 2.5rem; letter-spacing: -1px;">Hasil Simulasi <span class="text-primary">(Vaksinasi #<?php echo isset($targetSession) ? $targetSession : 1; ?>)</span></h1>
            <p class="text-secondary mb-0" style="max-width: 600px;">Detailed breakdown of patient queue dynamics across multiple service stages. Optimized for bottleneck identification and throughput analysis.</p>
        </div>
        <a href="history.php" class="btn btn-light shadow-sm text-primary fw-bold px-4 py-2 d-flex align-items-center gap-2" style="border-radius: 8px;">
            <i class="bi bi-arrow-left"></i> Kembali ke Riwayat
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <!-- Card 1 -->
        <div class="col-md-3">
            <div class="report-card p-4">
                <div class="text-secondary fw-bold text-uppercase mb-2" style="font-size: 0.65rem; letter-spacing: 1.5px;">TOTAL PATIENTS</div>
                <div class="fw-bold text-dark" style="font-size: 2.5rem; line-height: 1;"><?php echo $totalPatients; ?></div>
            </div>
        </div>
        <!-- Card 2 -->
        <div class="col-md-3">
            <div class="report-card p-4">
                <div class="text-secondary fw-bold text-uppercase mb-2" style="font-size: 0.65rem; letter-spacing: 1.5px; color: #2563eb !important;">SIMULATION TIME</div>
                <div class="fw-bold text-dark" style="font-size: 2.5rem; line-height: 1;"><?php echo $simTimeStr; ?></div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="report-card overflow-hidden">
        <div class="p-4 d-flex justify-content-between align-items-center border-bottom border-light">
            <h5 class="fw-bold mb-0 text-dark">Raw Execution Data</h5>
            <div class="d-flex gap-3">
                <button class="btn btn-link text-secondary p-0"><i class="bi bi-filter text-dark fs-5"></i></button>
                <button class="btn btn-link text-secondary p-0"><i class="bi bi-download text-dark fs-5"></i></button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-custom mb-0 text-center">
                <thead>
                    <tr>
                        <th rowspan="2" class="align-middle" style="background-color: #1a2332;">ID<br>PASIEN</th>
                        <th rowspan="2" class="align-middle" style="background-color: #1a2332;">WAKTU<br>KEDATANGAN</th>
                        <th rowspan="2" class="align-middle" style="background-color: #1a2332;">MASUK<br>SISTEM</th>
                        <th colspan="4" class="py-3" style="background-color: #0d9488;">INTERARRIVAL TIME (MASUK STAGE)</th>
                        <th colspan="5" class="py-3" style="background-color: #6366f1;">WAITING TIME (MASUK STAGE - MASUK QUEUE)</th>
                        <th colspan="5" class="py-3" style="background-color: #1e3a8a;">SERVICE TIME</th>
                        <th rowspan="2" class="align-middle" style="background-color: #1a2332;">TOTAL<br>TIME</th>
                    </tr>
                    <tr>
                        <th class="py-2 px-1" style="background-color: #14b8a6;">STAGE 1</th>
                        <th class="py-2 px-1" style="background-color: #14b8a6;">STAGE 2</th>
                        <th class="py-2 px-1" style="background-color: #14b8a6;">STAGE 3</th>
                        <th class="py-2 px-1" style="background-color: #14b8a6;">STAGE 4</th>
                        
                        <th class="py-2 px-1" style="background-color: #818cf8;">STAGE 1</th>
                        <th class="py-2 px-1" style="background-color: #818cf8;">STAGE 2</th>
                        <th class="py-2 px-1" style="background-color: #818cf8;">STAGE 3</th>
                        <th class="py-2 px-1" style="background-color: #818cf8;">STAGE 4</th>
                        <th class="py-2 px-1" style="background-color: #4f46e5;">TOTAL WQ</th>
                        
                        <th class="py-2 px-1" style="background-color: #3b82f6;">STAGE 1</th>
                        <th class="py-2 px-1" style="background-color: #3b82f6;">STAGE 2</th>
                        <th class="py-2 px-1" style="background-color: #3b82f6;">STAGE 3</th>
                        <th class="py-2 px-1" style="background-color: #3b82f6;">STAGE 4</th>
                        <th class="py-2 px-1" style="background-color: #2563eb;">TOTAL SRV</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <?php 
                        $sWait = [1=>0, 2=>0, 3=>0, 4=>0, 'total'=>0];
                        $cWait = [1=>0, 2=>0, 3=>0, 4=>0, 'total'=>0];
                        $sSrv = [1=>0, 2=>0, 3=>0, 4=>0, 'total'=>0];
                        $cSrv = [1=>0, 2=>0, 3=>0, 4=>0, 'total'=>0];
                        $sInter = [1=>0, 2=>0, 3=>0, 4=>0];
                        $cInter = [1=>0, 2=>0, 3=>0, 4=>0];
                        $sTot = 0; $cTot = 0;
                    ?>
                    <?php if (empty($data)): ?>
                        <tr><td colspan="19" class="text-muted py-5 fst-italic">Belum ada rekaman eksekusi simulasi.</td></tr>
                    <?php else: ?>
                        <?php foreach($data as $index => $user): ?>
                            <tr>
                                <td class="fw-bold text-primary px-3 align-middle">
                                    PA-<?php echo sprintf('%03d', $user['id']); ?>
                                </td>
                                
                                <td class="align-middle text-secondary">
                                    <?php echo formatTimestamp(getArrivalTime($user)); ?>
                                </td>

                                <td class="align-middle fw-bold text-dark">
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
                                    <td class="align-middle text-secondary">
                                        <?php 
                                        $val = calculateStageSpecificInterarrival($data, $index, $i);
                                        if ($val >= 0) {
                                            $sInter[$i] += $val;
                                            $cInter[$i]++;
                                        }
                                        echo formatDuration($val);
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
                                    <td class="align-middle <?php echo ($wait > 4000) ? 'text-warning-red fw-bold' : 'text-secondary'; ?>">
                                        <?php echo formatDuration($wait); ?>
                                    </td>
                                <?php endfor; ?>
                                
                                <?php if ($totalWait > 0) { $sWait['total'] += $totalWait; $cWait['total']++; } ?>
                                <td class="align-middle fw-bold text-purple" style="background-color: #faf5ff;">
                                    <?php echo formatDuration($totalWait); ?>
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
                                    <td class="align-middle text-secondary">
                                        <?php echo formatDuration($service); ?>
                                    </td>
                                <?php endfor; ?>

                                <?php if ($totalService > 0) { $sSrv['total'] += $totalService; $cSrv['total']++; } ?>
                                <td class="align-middle fw-bold text-primary" style="background-color: #eff6ff;">
                                    <?php echo formatDuration($totalService); ?>
                                </td>
                                
                                <td class="align-middle fw-bold text-dark">
                                    <?php 
                                        $tSys = getTotalTime($user);
                                        if ($tSys > 0) { $sTot += $tSys; $cTot++; }
                                        echo formatDuration($tSys); 
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- RATA - RATA -->
                        <tr style="background-color: #f1f5f9; border-top: 2px solid #e2e8f0;">
                            <td colspan="3" class="fw-bold text-end pe-4 align-middle text-dark">Average Values:</td>
                            
                            <!-- Inter Times -->
                            <?php for($i = 1; $i <= 4; $i++): ?>
                                <td class="align-middle fw-bold text-dark">
                                    <?php echo $cInter[$i] > 0 ? formatDuration($sInter[$i] / $cInter[$i]) : '-'; ?>
                                </td>
                            <?php endfor; ?>

                            <!-- Wait Times -->
                            <?php for($i = 1; $i <= 4; $i++): ?>
                                <td class="align-middle fw-bold text-dark">
                                    <?php echo $cWait[$i] > 0 ? formatDuration($sWait[$i] / $cWait[$i]) : '-'; ?>
                                </td>
                            <?php endfor; ?>
                            <td class="align-middle fw-bold text-purple" style="background-color: #faf5ff;">
                                <?php echo $cWait['total'] > 0 ? formatDuration($sWait['total'] / $cWait['total']) : '-'; ?>
                            </td>
                            
                            <!-- Service Times -->
                            <?php for($i = 1; $i <= 4; $i++): ?>
                                <td class="align-middle fw-bold text-dark">
                                    <?php echo $cSrv[$i] > 0 ? formatDuration($sSrv[$i] / $cSrv[$i]) : '-'; ?>
                                </td>
                            <?php endfor; ?>
                            <td class="align-middle fw-bold text-primary" style="background-color: #eff6ff;">
                                <?php echo $cSrv['total'] > 0 ? formatDuration($sSrv['total'] / $cSrv['total']) : '-'; ?>
                            </td>
                            
                            <!-- Total System Time -->
                            <td class="align-middle fw-bold text-dark">
                                <?php echo $cTot > 0 ? formatDuration($sTot / $cTot) : '-'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Desktop DES Section -->
    <div class="mt-5 border-top border-light pt-5 pb-5">
        
        <!-- DES Header -->
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h2 class="fw-bold mb-3 d-flex align-items-center flex-wrap gap-3" style="font-size: 2rem; letter-spacing: -0.5px; color: #0f172a;">
                    Discrete Event Simulation (DES)
                    <span class="badge" style="background-color: #e0e7ff; color: #3730a3; font-size: 0.75rem; letter-spacing: 1px; padding: 0.6rem 1rem; border-radius: 6px; border: 1px solid #c7d2fe;">PREDICTIVE ANALYSIS</span>
                </h2>
                <p class="text-secondary mb-0" style="max-width: 650px; font-size: 0.95rem; line-height: 1.6;">
                    High-fidelity computational modeling for complex queue systems. Adjust parameters below to calculate architectural system efficiency.
                </p>
            </div>
            <div class="d-flex gap-3 mt-3 mt-md-0">
                <button id="btnResetDes" class="btn text-danger fw-bold shadow-sm" style="background: white; border: 1px solid #fca5a5; border-radius: 6px; padding: 0.6rem 1.5rem; font-size: 0.85rem; letter-spacing: 1px;">RESET</button>
                <button id="btnStartDes" class="btn btn-primary fw-bold text-white shadow-sm d-flex align-items-center gap-2" style="border-radius: 6px; padding: 0.6rem 1.5rem; font-size: 0.85rem; letter-spacing: 1px;">
                    <i class="bi bi-star-fill"></i> START SIMULATION
                </button>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Left Column: SYSTEM INPUTS -->
            <div class="col-lg-4">
                <div class="report-card p-4 h-100" style="background-color: #f8fafc; border: 1px solid #f1f5f9;">
                    <h6 class="fw-bold text-dark mb-4 d-flex align-items-center gap-2" style="font-size: 0.85rem; letter-spacing: 1px;">
                        <i class="bi bi-sliders text-primary"></i> SYSTEM INPUTS
                    </h6>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="form-label text-secondary fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">REPS (REPLIKASI)</label>
                            <input type="number" id="desReps" class="form-control border-0 shadow-sm" value="200" style="padding: 0.75rem;">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-secondary fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">WARMUP (ABAIKAN)</label>
                            <input type="number" id="desWarmup" class="form-control border-0 shadow-sm" value="10" style="padding: 0.75rem;">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">OBS (DIOBSERVASI)</label>
                        <input type="number" id="desObs" class="form-control border-0 shadow-sm" value="30" style="padding: 0.75rem;">
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">BATAS KAPASITAS PER JAM</label>
                        <input type="number" id="desQuota" class="form-control border-0 shadow-sm" value="0" style="padding: 0.75rem;" placeholder="0 = Unlimited">
                    </div>

                    <div class="mb-2">
                        <label class="form-label text-secondary fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">SERVER ALLOCATION PER STAGE</label>
                        <div class="d-flex gap-2">
                            <div class="text-center w-100">
                                <div class="text-secondary fw-bold" style="font-size: 0.55rem; letter-spacing: 1px; margin-bottom: 4px;">ST-1</div>
                                <input type="number" id="srv1" class="form-control border-0 shadow-sm text-center px-1" value="1" min="1">
                            </div>
                            <div class="text-center w-100">
                                <div class="text-secondary fw-bold" style="font-size: 0.55rem; letter-spacing: 1px; margin-bottom: 4px;">ST-2</div>
                                <input type="number" id="srv2" class="form-control border-0 shadow-sm text-center px-1" value="1" min="1">
                            </div>
                            <div class="text-center w-100">
                                <div class="text-secondary fw-bold" style="font-size: 0.55rem; letter-spacing: 1px; margin-bottom: 4px;">ST-3</div>
                                <input type="number" id="srv3" class="form-control border-0 shadow-sm text-center px-1" value="1" min="1">
                            </div>
                            <div class="text-center w-100">
                                <div class="text-secondary fw-bold" style="font-size: 0.55rem; letter-spacing: 1px; margin-bottom: 4px;">ST-4</div>
                                <input type="number" id="srv4" class="form-control border-0 shadow-sm text-center px-1" value="1" min="1">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Results Container -->
            <div class="col-lg-8 position-relative">
                <div id="desResultsContainer" class="d-none animate-fade-in w-100 h-100">
                    
                    <!-- Table 3 -->
                    <div class="report-card p-4 mb-4" style="border: 1px solid #f1f5f9;">
                        <h6 class="text-secondary fw-bold text-uppercase mb-4" style="font-size: 0.7rem; letter-spacing: 1.5px;">TABLE 3: OVERALL SYSTEM METRICS</h6>
                        <div class="row align-items-center">
                            <div class="col-6 col-md-3 mb-3 mb-md-0">
                                <div class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 0.6rem; letter-spacing: 0.5px;">WQ (QUEUE TIME)</div>
                                <div class="fw-bold" style="font-size: 2rem; color: #2563eb; line-height: 1;">
                                    <span id="sysWq">0</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3 mb-md-0">
                                <div class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 0.6rem; letter-spacing: 0.5px;">W (SYSTEM TIME)</div>
                                <div class="fw-bold text-dark" style="font-size: 2rem; line-height: 1;">
                                    <span id="sysW">0</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 0.6rem; letter-spacing: 0.5px;">LQ (AVG QUEUE)</div>
                                <div class="fw-bold" style="font-size: 2rem; color: #3b82f6; line-height: 1;">
                                    <span id="sysLq">0</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 0.6rem; letter-spacing: 0.5px;">L (AVG SYSTEM)</div>
                                <div class="fw-bold text-dark" style="font-size: 2rem; line-height: 1;">
                                    <span id="sysL">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table 1 -->
                    <div class="report-card p-4" style="border: 1px solid #f1f5f9; min-height: 250px;">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="text-secondary fw-bold text-uppercase mb-0" style="font-size: 0.7rem; letter-spacing: 1.5px;">TABLE 1: PER STAGE PERFORMANCE</h6>
                            <i class="bi bi-layout-text-window-reverse text-secondary" style="font-size: 1.2rem;"></i>
                        </div>
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle">
                                <thead>
                                    <tr class="text-secondary fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px; border-bottom: 2px solid #f1f5f9;">
                                        <th class="ps-0 border-0 pb-3" style="font-weight: 700 !important; color: #64748b;">STAGE ID</th>
                                        <th class="border-0 pb-3" style="font-weight: 700 !important; color: #64748b;">AVG WAITING TIME<br>(MIN)</th>
                                        <th class="border-0 pb-3" style="font-weight: 700 !important; color: #64748b;">AVG SERVICE TIME<br>(MIN)</th>
                                    </tr>
                                </thead>
                                <tbody id="t1Body">
                                    <!-- Populated automatically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Initial State Placeholder -->
                <div id="desPlaceholder" class="report-card d-flex flex-column justify-content-center align-items-center text-secondary w-100 mx-auto" style="min-height: 400px; border: 2px dashed #e2e8f0; background-color: #f8fafc;">
                    <i class="bi bi-box-seam" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                    <p class="mt-3 text-muted fw-semibold" style="letter-spacing: 0.5px;">Ready to run simulation.</p>
                </div>
            </div>
        </div>

        <!-- Full Width Tables & Alerts -->
        <div id="desBottomContainer" class="d-none animate-fade-in">
            <!-- Table 2: Queue Parameters -->
            <div class="report-card p-4 p-md-5 mb-4" style="border: 1px solid #f1f5f9;">
                <h6 class="text-dark fw-bold text-uppercase mb-4 d-flex align-items-center gap-2" style="font-size: 0.85rem; letter-spacing: 1.5px;">
                    <i class="bi bi-table text-primary p-1 bg-primary bg-opacity-10 rounded"></i> TABLE 2: QUEUE PARAMETERS PER STAGE
                </h6>
                <div class="table-responsive">
                    <table class="table text-center align-middle mb-0" style="font-size: 0.8rem;">
                        <thead>
                            <tr class="text-secondary fw-bold text-uppercase" style="font-size: 0.65rem; border-bottom: 2px solid #e2e8f0 !important; color: #475569 !important;">
                                <th class="border-0 pb-3 ps-0 text-start">STAGE</th>
                                <th class="border-0 pb-3">λI<br><small class="text-muted fw-normal text-capitalize">(Mean Arrival)</small></th>
                                <th class="border-0 pb-3">σAI<br><small class="text-muted fw-normal text-capitalize">(Std. Dev)</small></th>
                                <th class="border-0 pb-3">CAI<br><small class="text-muted fw-normal text-capitalize">(CoV)</small></th>
                                <th class="border-0 pb-3">SI<br><small class="text-muted fw-normal text-capitalize">(Mean Service)</small></th>
                                <th class="border-0 pb-3">σSI<br><small class="text-muted fw-normal text-capitalize">(Std. Dev)</small></th>
                                <th class="border-0 pb-3">CSI<br><small class="text-muted fw-normal text-capitalize">(CoV)</small></th>
                                <th class="border-0 pb-3">ΛI<br><small class="text-muted fw-normal text-capitalize">(Arrival Rate)</small></th>
                                <th class="border-0 pb-3">PI<br><small class="text-muted fw-normal text-capitalize">(Utilization)</small></th>
                                <th class="border-0 pb-3 pe-0 text-end">MI<br><small class="text-muted fw-normal text-capitalize">(Service Rate)</small></th>
                            </tr>
                        </thead>
                        <tbody id="t2Body" class="border-top-0">
                            <!-- Populated automatically -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Insight Alert -->
            <div class="p-4 rounded-4 shadow-sm mb-4" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div style="background-color: #feefc3; border-radius: 50%; padding: 6px; display: inline-flex;">
                        <i class="bi bi-lightbulb-fill text-warning fs-5"></i>
                    </div>
                    <h6 class="fw-bold mb-0 text-primary" style="font-size: 1rem;">Insight Teori Antrean</h6>
                </div>
                
                <div class="mb-3">
                    <h6 class="fw-bold text-dark d-block mb-1" style="font-size: 0.85rem;">Tingkat Keacakan Waktu (CA / CS)</h6>
                    <p class="text-secondary mb-2" style="font-size: 0.85rem; line-height: 1.6;">Parameter ini mengukur seberapa besar fluktuasi (ketidakpastian) waktu antrean terbentuk maupun waktu layanan diselesaikan.</p>
                    <ul class="text-secondary" style="font-size: 0.85rem; padding-left: 1.2rem; line-height: 1.6;">
                        <li><strong>Bernilai &gt; 1</strong>: Durasi layanan sangat bervariasi. Hal ini secara matematis akan memicu penumpukan antrean yang sulit diprediksi.</li>
                        <li><strong>Bernilai &lt; 1</strong>: Laju kedatangan tipe pasien dan waktu penanganan tergolong cukup konsisten dan stabil.</li>
                    </ul>
                </div>
                
                <div>
                    <h6 class="fw-bold text-dark d-block mb-1" style="font-size: 0.85rem;">Beban Kapasitas Layanan (ρ / Utilisasi)</h6>
                    <p class="text-secondary mb-2" style="font-size: 0.85rem; line-height: 1.6;">Parameter ini menunjukkan utilisasi (proporsi) kesibukan staf terhadap jumlah pasien yang harus ditanggung pada tahap tertentu.</p>
                    <ul class="text-secondary mb-0" style="font-size: 0.85rem; padding-left: 1.2rem; line-height: 1.6;">
                        <li><strong>Di bawah 1</strong>: Kapasitas tenaga kerja masih sangat memadai untuk menangani kedatangan pasien.</li>
                        <li><strong>Mendekati 1</strong>: Beban kerja staf mencapai batas maksimal tanpa jeda istirahat; antrean rentan terbentuk.</li>
                        <li><strong>Melebihi 1</strong>: Kapasitas berlebih (<em>Overload / Bottleneck</em>). Volume pasien jauh melampaui kemampuan maksimal staf, dipastikan menyebabkan kemacetan sistemik.</li>
                    </ul>
                </div>
            </div>

            <!-- Kingman Warning Alert -->
            <div id="kingmanWarning" class="p-4 rounded-4 shadow-sm d-none" style="background-color: #fef2f2; border: 1px solid #fecaca;">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                    <h6 class="fw-bold mb-0 text-danger" style="font-size: 1rem;">Peringatan Kestabilan Sistem & Analitik G/G/1</h6>
                </div>
                <p id="kingmanWarningText" class="text-danger mb-0" style="font-size: 0.85rem; line-height: 1.6;"></p>
            </div>
        </div>
        
    </div>
</div>

<style>
.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
/* Basic row styling for T1 and T2 */
#t1Body tr { border-bottom: 1px solid #f1f5f9; }
#t1Body tr:last-child { border-bottom: none; }
#t2Body tr { border-bottom: 1px solid #f1f5f9; }
#t2Body tr:last-child { border-bottom: none; }
</style>

<script>
    const targetSessionId = <?php echo isset($targetSession) ? $targetSession : 1; ?>;
    
    // UI Enhancements over DES.js
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('btnStartDes').addEventListener('click', function() {
            // Fix: remove d-flex before adding d-none to hide it completely
            let pl = document.getElementById('desPlaceholder');
            pl.classList.remove('d-flex');
            pl.classList.add('d-none');
            
            document.getElementById('desResultsContainer').classList.remove('d-none');
            document.getElementById('desBottomContainer').classList.remove('d-none');
        });
        
        document.getElementById('btnResetDes').addEventListener('click', function() {
            let pl = document.getElementById('desPlaceholder');
            pl.classList.remove('d-none');
            pl.classList.add('d-flex');
            
            document.getElementById('desResultsContainer').classList.add('d-none');
            document.getElementById('desBottomContainer').classList.add('d-none');
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="des.js"></script>

</body>
</html>
