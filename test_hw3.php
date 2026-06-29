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

$calc_hw = function($alpha, $beta, $gamma) use ($Y2, $L5, $T5, $S_init) {
    $L_prev = $L5;
    $T_prev = $T5;
    $S_prev = $S_init;
    $sse = 0;
    
    for ($i = 0; $i < 5; $i++) {
        $y_actual = $Y2[$i];
        $F = ($L_prev + $T_prev) * $S_prev[$i];
        $sse += pow($y_actual - $F, 2);
        
        $L_t = $alpha * ($y_actual / $S_prev[$i]) + (1 - $alpha) * ($L_prev + $T_prev);
        $T_t = $beta * ($L_t - $L_prev) + (1 - $beta) * $T_prev;
        $S_t = $gamma * ($y_actual / $L_t) + (1 - $gamma) * $S_prev[$i];
        
        $L_prev = $L_t;
        $T_prev = $T_t;
    }
    return sqrt($sse / 5);
};

$best_rmse = INF;
$best_params = [];

for ($a = 0.0; $a <= 1.0; $a += 0.05) {
    for ($b = 0.0; $b <= 1.0; $b += 0.05) {
        for ($g = 0.0; $g <= 1.0; $g += 0.05) {
            $rmse = $calc_hw($a, $b, $g);
            if ($rmse < $best_rmse) {
                $best_rmse = $rmse;
                $best_params = ['a' => $a, 'b' => $b, 'g' => $g];
            }
        }
    }
}
$a_start = max(0.0, $best_params['a'] - 0.05); $a_end = min(1.0, $best_params['a'] + 0.05);
$b_start = max(0.0, $best_params['b'] - 0.05); $b_end = min(1.0, $best_params['b'] + 0.05);
$g_start = max(0.0, $best_params['g'] - 0.05); $g_end = min(1.0, $best_params['g'] + 0.05);

for ($a = $a_start; $a <= $a_end; $a += 0.01) {
    for ($b = $b_start; $b <= $b_end; $b += 0.01) {
        for ($g = $g_start; $g <= $g_end; $g += 0.01) {
            $rmse = $calc_hw($a, $b, $g);
            if ($rmse < $best_rmse) {
                $best_rmse = $rmse;
                $best_params = ['a' => $a, 'b' => $b, 'g' => $g];
            }
        }
    }
}

file_put_contents('hw_debug.txt', "Best RMSE: $best_rmse \n Params: " . print_r($best_params, true));
