<?php
$Y1 = [11, 6, 5, 10, 3];
$Y2 = [10, 8, 7, 10, 4];

$L5 = array_sum($Y1) / 5;
$S_init = [];
foreach ($Y1 as $y) $S_init[] = $y / $L5;

$T5 = 0;
for ($i = 0; $i < 5; $i++) {
    $T5 += (($Y2[$i] - $Y1[$i]) / 5) / 5;
}
echo "Init L5: $L5, T5: $T5\n";

$calc_hw = function($alpha, $beta, $gamma) use ($Y2, $L5, $T5, $S_init) {
    $L_prev = $L5;
    $T_prev = $T5;
    $S_prev = $S_init;
    $S_new = [];
    $sse = 0;
    
    for ($i = 0; $i < 5; $i++) {
        $y_actual = $Y2[$i];
        $F = ($L_prev + $T_prev) * $S_prev[$i];
        $sse += pow($y_actual - $F, 2);
        
        $L_t = $alpha * ($y_actual / $S_prev[$i]) + (1 - $alpha) * ($L_prev + $T_prev);
        $T_t = $beta * ($L_t - $L_prev) + (1 - $beta) * $T_prev;
        $S_t = $gamma * ($y_actual / $L_t) + (1 - $gamma) * $S_prev[$i];
        
        $S_new[] = $S_t;
        $L_prev = $L_t;
        $T_prev = $T_t;
    }
    
    return [
        'rmse' => sqrt($sse / 5),
        'L_last' => $L_prev,
        'T_last' => $T_prev,
        'S_last' => $S_new,
    ];
};

// Start with user defaults
$res = $calc_hw(1, 0.008877, 0.232778);
echo "User RMSE: " . $res['rmse'] . "\n";

$best_rmse = INF;
$best_params = [];
$best_model = null;

// Grid search
for ($a = 0.01; $a <= 1.0; $a += 0.05) {
    for ($b = 0.01; $b <= 1.0; $b += 0.05) {
        for ($g = 0.01; $g <= 1.0; $g += 0.05) {
            $r = $calc_hw($a, $b, $g);
            if ($r['rmse'] < $best_rmse) {
                $best_rmse = $r['rmse'];
                $best_params = ['alpha' => $a, 'beta' => $b, 'gamma' => $g];
                $best_model = $r;
            }
        }
    }
}
// Fine search
$a_start = max(0.01, $best_params['alpha'] - 0.05);
$a_end = min(1.0, $best_params['alpha'] + 0.05);
$b_start = max(0.01, $best_params['beta'] - 0.05);
$b_end = min(1.0, $best_params['beta'] + 0.05);
$g_start = max(0.01, $best_params['gamma'] - 0.05);
$g_end = min(1.0, $best_params['gamma'] + 0.05);

for ($a = $a_start; $a <= $a_end; $a += 0.01) {
    for ($b = $b_start; $b <= $b_end; $b += 0.01) {
        for ($g = $g_start; $g <= $g_end; $g += 0.01) {
            $r = $calc_hw($a, $b, $g);
            if ($r['rmse'] < $best_rmse) {
                $best_rmse = $r['rmse'];
                $best_params = ['alpha' => $a, 'beta' => $b, 'gamma' => $g];
                $best_model = $r;
            }
        }
    }
}

echo "Best RMSE: $best_rmse\n";
print_r($best_params);

$forecast_p3 = [];
for ($h = 1; $h <= 5; $h++) {
    $f_raw = ($best_model['L_last'] + $h * $best_model['T_last']) * $best_model['S_last'][$h - 1];
    $forecast_p3[] = ceil($f_raw);
}
print_r($forecast_p3);
