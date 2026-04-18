// Expected global variable: targetSessionId

let interarrivalSamples = [];
let serviceTimes = { 1: [], 2: [], 3: [], 4: [] };
let probNext = { 2: 1, 3: 1, 4: 1 };

// Helper to convert to Minutes for metric consistency
const toMin = ms => ms / 60000;

async function loadDESData() {
    try {
        const res = await fetch('results.json');
        const allData = await res.json();

        let sessionData = allData.filter(d => d.session_id == targetSessionId);

        let stage1Data = sessionData.filter(d => d.history.find(h => h.stage === 1));
        stage1Data.sort((a, b) => {
            let tA = a.history.find(h => h.stage === 1).masuk_queue;
            let tB = b.history.find(h => h.stage === 1).masuk_queue;
            return tA - tB;
        });

        interarrivalSamples = [];
        for (let i = 1; i < stage1Data.length; i++) {
            let t0 = stage1Data[i - 1].history.find(h => h.stage === 1).masuk_queue;
            let t1 = stage1Data[i].history.find(h => h.stage === 1).masuk_queue;
            if (t1 >= t0) {
                interarrivalSamples.push(toMin(t1 - t0));
            }
        }

        if (interarrivalSamples.length === 0) interarrivalSamples = [1];

        serviceTimes = { 1: [], 2: [], 3: [], 4: [] };
        let cCounts = { 1: 0, 2: 0, 3: 0, 4: 0 };

        sessionData.forEach(d => {
            if (d.history.find(h => h.stage === 1)) cCounts[1]++;
            if (d.history.find(h => h.stage === 2)) cCounts[2]++;
            if (d.history.find(h => h.stage === 3)) cCounts[3]++;
            if (d.history.find(h => h.stage === 4)) cCounts[4]++;

            d.history.forEach(h => {
                let st = toMin(h.keluar_stage - h.masuk_stage);
                // Fix 5: Validate Service Time Data (normalize/filter out extreme outliers > 60 mins)
                if (!isNaN(st) && st >= 0 && st <= 60) {
                    serviceTimes[h.stage].push(st);
                }
            });
        });

        // Calculate the empirical probabilities of continuing to the next stage
        probNext[2] = cCounts[1] > 0 ? (cCounts[2] / cCounts[1]) : 1;
        probNext[3] = cCounts[2] > 0 ? (cCounts[3] / cCounts[2]) : 1;
        probNext[4] = cCounts[3] > 0 ? (cCounts[4] / cCounts[3]) : 1;

        for (let i = 1; i <= 4; i++) {
            if (serviceTimes[i].length === 0) serviceTimes[i] = [1];
        }

        console.log("Max Stage 3:", Math.max(...serviceTimes[3]));

    } catch (err) {
        console.error("Loading DES data failed", err);
    }
}

function runDES() {
    let reps = parseInt(document.getElementById('desReps').value) || 200;
    let warmup = parseInt(document.getElementById('desWarmup').value) || 10;
    let obs = parseInt(document.getElementById('desObs').value) || 30;

    // G/G/c parameters
    let qps = document.getElementById('desQuota');
    let quota = qps ? parseInt(qps.value) || 0 : 0;

    let elS1 = document.getElementById('srv1'); let srv1 = elS1 ? parseInt(elS1.value) || 1 : 1;
    let elS2 = document.getElementById('srv2'); let srv2 = elS2 ? parseInt(elS2.value) || 1 : 1;
    let elS3 = document.getElementById('srv3'); let srv3 = elS3 ? parseInt(elS3.value) || 1 : 1;
    let elS4 = document.getElementById('srv4'); let srv4 = elS4 ? parseInt(elS4.value) || 1 : 1;

    let serversCount = { 1: srv1, 2: srv2, 3: srv3, 4: srv4 };

    if (isNaN(reps) || isNaN(warmup) || isNaN(obs) || reps <= 0 || obs <= 0) {
        alert("Harap masukkan nilai yang valid untuk REPS, WARMUP, dan OBS.");
        return;
    }

    const btn = document.getElementById('btnStartDes');
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Simulating...`;
    btn.disabled = true;

    setTimeout(() => {
        let totalCustomers = warmup + obs;
        let repMetrics = [];

        let config = { warmup, obs, totalCustomers, quota, serversCount };

        for (let r = 0; r < reps; r++) {
            repMetrics.push(simOneRep(config));
        }

        let finalMetrics = averageRepMetrics(repMetrics, reps);
        renderDESResults(finalMetrics);

        btn.innerHTML = `🌟 Start Simulation`;
        btn.disabled = false;
    }, 50);
}

function simOneRep(config) {
    let warmup = config.warmup;
    let obs = config.obs;
    let totalCustomers = config.totalCustomers;
    let quota = config.quota;
    let srvC = config.serversCount;

    let customers = [];
    let currentTime = 8 * 60; // Basis jam 08:00 (480 minutes)
    let currentHourLimit = 9 * 60; // Next hour block (09:00:00)
    let patientsInHourBlock = 0;

    for (let i = 0; i < totalCustomers; i++) {
        let inter = interarrivalSamples[Math.floor(Math.random() * interarrivalSamples.length)];
        if (i === 0) inter = 0;

        let rawArr = currentTime + inter;

        if (quota > 0) {
            patientsInHourBlock++;
            if (patientsInHourBlock > quota) {
                // Dorong ke awal jam berikutnya
                currentTime = currentHourLimit;
                currentHourLimit += 60;
                patientsInHourBlock = 1;
            } else {
                currentTime = rawArr;
            }
        } else {
            currentTime = rawArr;
        }

        customers.push({
            id: i,
            arrivalSystem: currentTime,
            interArrivalSystem: (i === 0) ? 0 : inter,
            stages: {}
        });
    }

    // Engine G/G/c Array - Membagi Server per Tahap 
    let server_free = {
        1: new Array(srvC[1]).fill(8 * 60),
        2: new Array(srvC[2]).fill(8 * 60),
        3: new Array(srvC[3]).fill(8 * 60),
        4: new Array(srvC[4]).fill(8 * 60)
    };

    function getEarliestFreeServer(serverArray) {
        let minTime = serverArray[0];
        let minIdx = 0;
        for (let k = 1; k < serverArray.length; k++) {
            if (serverArray[k] < minTime) { minTime = serverArray[k]; minIdx = k; }
        }
        return { time: minTime, idx: minIdx };
    }
    let last_arr = { 1: null, 2: null, 3: null, 4: null };

    for (let i = 0; i < totalCustomers; i++) {
        let c = customers[i];

        // --- STAGE 1 ---
        let arr_1 = c.arrivalSystem;
        let pSrv1 = getEarliestFreeServer(server_free[1]);
        let start_service_1 = Math.max(arr_1, pSrv1.time);
        let wait_1 = start_service_1 - arr_1;
        let sArr1 = serviceTimes[1];
        let service_1 = sArr1[Math.floor(Math.random() * sArr1.length)];
        let end_1 = start_service_1 + service_1;
        server_free[1][pSrv1.idx] = end_1; // Alokasi kasir tercepat

        c.stages[1] = {
            arrival: arr_1,
            interArrival: (last_arr[1] !== null) ? (arr_1 - last_arr[1]) : 0,
            waitTime: wait_1,
            serviceTime: service_1,
            endService: end_1
        };
        last_arr[1] = arr_1;

        // --- STAGE 2 ---
        // Empirical Routing: drop out logic based on history
        if (Math.random() <= probNext[2]) {
            // Output tahap sebelumnya => input tahap ini
            let arr_2 = end_1;
            let pSrv2 = getEarliestFreeServer(server_free[2]);
            let start_service_2 = Math.max(arr_2, pSrv2.time);
            let wait_2 = start_service_2 - arr_2;
            let sArr2 = serviceTimes[2];
            let service_2 = sArr2[Math.floor(Math.random() * sArr2.length)];
            let end_2 = start_service_2 + service_2;
            server_free[2][pSrv2.idx] = end_2;

            c.stages[2] = {
                arrival: arr_2,
                interArrival: (last_arr[2] !== null) ? (arr_2 - last_arr[2]) : 0,
                waitTime: wait_2,
                serviceTime: service_2,
                endService: end_2
            };
            last_arr[2] = arr_2;

            // --- STAGE 3 ---
            if (Math.random() <= probNext[3]) {
                let arr_3 = end_2;
                let pSrv3 = getEarliestFreeServer(server_free[3]);
                let start_service_3 = Math.max(arr_3, pSrv3.time);
                let wait_3 = start_service_3 - arr_3;
                let sArr3 = serviceTimes[3];
                let service_3 = sArr3[Math.floor(Math.random() * sArr3.length)];
                let end_3 = start_service_3 + service_3;
                server_free[3][pSrv3.idx] = end_3;

                c.stages[3] = {
                    arrival: arr_3,
                    interArrival: (last_arr[3] !== null) ? (arr_3 - last_arr[3]) : 0,
                    waitTime: wait_3,
                    serviceTime: service_3,
                    endService: end_3
                };
                last_arr[3] = arr_3;

                // --- STAGE 4 ---
                if (Math.random() <= probNext[4]) {
                    let arr_4 = end_3;
                    let pSrv4 = getEarliestFreeServer(server_free[4]);
                    let start_service_4 = Math.max(arr_4, pSrv4.time);
                    let wait_4 = start_service_4 - arr_4;
                    let sArr4 = serviceTimes[4];
                    let service_4 = sArr4[Math.floor(Math.random() * sArr4.length)];
                    let end_4 = start_service_4 + service_4;
                    server_free[4][pSrv4.idx] = end_4;

                    c.stages[4] = {
                        arrival: arr_4,
                        interArrival: (last_arr[4] !== null) ? (arr_4 - last_arr[4]) : 0,
                        waitTime: wait_4,
                        serviceTime: service_4,
                        endService: end_4
                    };
                    last_arr[4] = arr_4;
                }
            }
        }
    }

    // Fix 6: Apply WARMUP filtering correctly
    let obsCustomers = customers.slice(warmup, warmup + obs);
    let cCount = obsCustomers.length;

    let metrics = {
        stages: { 1: {}, 2: {}, 3: {}, 4: {} },
        WqSum: 0, WSum: 0,
        srvC: srvC
    };

    for (let stage = 1; stage <= 4; stage++) {
        let wSum = 0, sSum = 0;
        let interArrivals = [];
        let rServiceTimes = [];

        let validCustCount = 0;
        let waitCustCount = 0;

        for (let i = 0; i < cCount; i++) {
            if (!obsCustomers[i].stages[stage]) continue; // Skip dropped out customers

            let s = obsCustomers[i].stages[stage];
            if (s.waitTime > 0) {
                wSum += s.waitTime;
                waitCustCount++;
            }

            sSum += s.serviceTime;
            if (validCustCount > 0) interArrivals.push(s.interArrival);
            rServiceTimes.push(s.serviceTime);

            validCustCount++;
            metrics.WqSum += s.waitTime;
        }

        metrics.stages[stage].avgWait = waitCustCount > 0 ? (wSum / waitCustCount) : 0;
        metrics.stages[stage].avgService = validCustCount > 0 ? (sSum / validCustCount) : 0;

        let meanInter = calculateMean(interArrivals);
        let stdInter = calculateStdDev(interArrivals, meanInter);
        let meanService = calculateMean(rServiceTimes);
        let stdService = calculateStdDev(rServiceTimes, meanService);

        metrics.stages[stage].Ai = meanInter;
        metrics.stages[stage].SigmaAi = stdInter;
        metrics.stages[stage].Si = meanService;
        metrics.stages[stage].SigmaSi = stdService;
    }

    for (let i = 0; i < cCount; i++) {
        let finalStage = 1;
        if (obsCustomers[i].stages[4]) finalStage = 4;
        else if (obsCustomers[i].stages[3]) finalStage = 3;
        else if (obsCustomers[i].stages[2]) finalStage = 2;

        metrics.WSum += (obsCustomers[i].stages[finalStage].endService - obsCustomers[i].arrivalSystem);
    }

    metrics.W = cCount > 0 ? (metrics.WSum / cCount) : 0;
    metrics.Wq = cCount > 0 ? (metrics.WqSum / cCount) : 0;

    return metrics;
}

function calculateMean(arr) {
    if (arr.length === 0) return 0;
    let sum = arr.reduce((a, b) => a + b, 0);
    return sum / arr.length;
}

function calculateStdDev(arr, mean) {
    if (arr.length <= 1) return 0;
    let sumSq = arr.reduce((a, b) => a + Math.pow(b - mean, 2), 0);
    return Math.sqrt(sumSq / (arr.length - 1));
}

function averageRepMetrics(repMetrics, reps) {
    let final = {
        stages: { 1: {}, 2: {}, 3: {}, 4: {} },
        Wq: 0, W: 0, Lq: 0, L: 0,
        srvC: repMetrics.length > 0 ? repMetrics[0].srvC : { 1: 1, 2: 1, 3: 1, 4: 1 }
    };

    let sumWq = 0, sumW = 0;

    let stageSums = {
        1: { avgWait: 0, avgService: 0, Ai: 0, SigmaAi: 0, Si: 0, SigmaSi: 0 },
        2: { avgWait: 0, avgService: 0, Ai: 0, SigmaAi: 0, Si: 0, SigmaSi: 0 },
        3: { avgWait: 0, avgService: 0, Ai: 0, SigmaAi: 0, Si: 0, SigmaSi: 0 },
        4: { avgWait: 0, avgService: 0, Ai: 0, SigmaAi: 0, Si: 0, SigmaSi: 0 }
    };

    repMetrics.forEach(m => {
        sumWq += m.Wq;
        sumW += m.W;
        for (let stage = 1; stage <= 4; stage++) {
            stageSums[stage].avgWait += m.stages[stage].avgWait;
            stageSums[stage].avgService += m.stages[stage].avgService;
            stageSums[stage].Ai += m.stages[stage].Ai;
            stageSums[stage].SigmaAi += m.stages[stage].SigmaAi;
            stageSums[stage].Si += m.stages[stage].Si;
            stageSums[stage].SigmaSi += m.stages[stage].SigmaSi;
        }
    });

    for (let stage = 1; stage <= 4; stage++) {
        let s = final.stages[stage];
        let sums = stageSums[stage];
        s.avgWait = sums.avgWait / reps;
        s.avgService = sums.avgService / reps;
        s.Ai = sums.Ai / reps;
        s.SigmaAi = sums.SigmaAi / reps;
        s.Si = sums.Si / reps;
        s.SigmaSi = sums.SigmaSi / reps;

        s.CAi = s.Ai > 0 ? (s.SigmaAi / s.Ai) : 0;
        s.CSi = s.Si > 0 ? (s.SigmaSi / s.Si) : 0;
        s.lambda = s.Ai > 0 ? (1 / s.Ai) : 0;
        s.mu = s.Si > 0 ? (1 / s.Si) : 0;

        let c = final.srvC[stage] || 1;
        s.rho = s.mu > 0 ? (s.lambda / (c * s.mu)) : 0;
    }

    final.Wq = sumWq / reps;
    final.W = sumW / reps;

    let globalLambda = final.stages[1].lambda;
    final.Lq = globalLambda * final.Wq;
    final.L = globalLambda * final.W;

    return final;
}

function formatNum(num) {
    return Number(num).toFixed(4);
}

function formatDurationH(mins) {
    if (!isFinite(mins) || mins < 0) return '0.0000 min';
    return Number(mins).toFixed(4) + ' min';
}

function getUtilizationClass(rho) {
    if (rho > 1) return 'bg-danger text-white px-2 py-1 rounded shadow-sm fw-bold';
    if (rho >= 0.8) return 'bg-warning text-dark px-2 py-1 rounded shadow-sm fw-bold';
    return 'bg-success text-white px-2 py-1 rounded shadow-sm fw-bold';
}

function getCVClass(cv) {
    if (cv > 1) return 'text-danger fw-bold';
    return 'text-success fw-bold';
}

function renderDESResults(metrics) {
    document.getElementById('desResultsContainer').classList.remove('d-none');

    // Table 1
    let t1Body = '';
    for (let i = 1; i <= 4; i++) {
        t1Body += `<tr>
            <td class="fw-bold">Tahap ${i}</td>
            <td class="text-primary fw-semibold">${formatDurationH(metrics.stages[i].avgWait)}</td>
            <td class="text-info fw-semibold">${formatDurationH(metrics.stages[i].avgService)}</td>
        </tr>`;
    }
    document.getElementById('t1Body').innerHTML = t1Body;

    // Table 2
    let t2Body = '';
    for (let i = 1; i <= 4; i++) {
        let s = metrics.stages[i];
        t2Body += `<tr>
            <td class="fw-bold">Tahap ${i}</td>
            <td>${formatNum(s.Ai)}</td>
            <td>${formatNum(s.SigmaAi)}</td>
            <td class="${getCVClass(s.CAi)}">${formatNum(s.CAi)}</td>
            <td>${formatNum(s.Si)}</td>
            <td>${formatNum(s.SigmaSi)}</td>
            <td class="${getCVClass(s.CSi)}">${formatNum(s.CSi)}</td>
            <td>${formatNum(s.lambda)}</td>
            <td><span class="${getUtilizationClass(s.rho)}">${formatNum(s.rho)}</span></td>
            <td>${formatNum(s.mu)}</td>
        </tr>`;
    }
    document.getElementById('t2Body').innerHTML = t2Body;

    // Table 3
    document.getElementById('sysWq').innerText = formatDurationH(metrics.Wq);
    document.getElementById('sysW').innerText = formatDurationH(metrics.W);
    document.getElementById('sysLq').innerText = formatNum(metrics.Lq) + " cust";
    document.getElementById('sysL').innerText = formatNum(metrics.L) + " cust";

    // Kingman Warning Evaluator
    let cReasons = [];
    let rhoReasons = [];
    let rhoStages = [];

    for (let i = 1; i <= 4; i++) {
        let s = metrics.stages[i];
        if (s.rho > 1) {
            rhoReasons.push(`Tahap ${i} (ρ = ${formatNum(s.rho)})`);
            rhoStages.push(`Tahap ${i}`);
        }
        if (s.CAi > 1) cReasons.push(`Tahap ${i} (CA = ${formatNum(s.CAi)})`);
        if (s.CSi > 1) cReasons.push(`Tahap ${i} (CS = ${formatNum(s.CSi)})`);
    }

    let warnDiv = document.getElementById('kingmanWarning');
    let warnText = document.getElementById('kingmanWarningText');
    if (warnDiv) {
        if (cReasons.length > 0 || rhoReasons.length > 0) {
            warnDiv.classList.remove('d-none');
            let txt = 'Berdasarkan parameter dasar sistem simulasi ini:<br><ul class="mb-0 mt-2">';

            if (cReasons.length > 0) {
                txt += `<li class="mb-2"><strong>CA / CS > 1:</strong> Ditemukan pada ${cReasons.join(", ")}. Ini menandakan laju kedatangan tipe pasien dan waktu penanganan tergolong fluktuatif/bervariasi, sehingga rentan memicu ketidakpastian antrean.</li>`;
            }
            if (rhoReasons.length > 0) {
                txt += `<li><strong>ρ > 1 (Overload):</strong> Ditemukan pada ${rhoReasons.join(", ")}. Kondisi parameter ρ di atas menunjukkan bahwa sistem antrean terindikasi sangat tidak stabil. Mengacu pada aturan batasan mekanika antrean, jika model matematis G/G/1 (Pendekatan Kingman) diterapkan secara teori rumus langsung pada kondisi ini, perhitungan waktu tunggu rata-rata (Wq) secara kalkulator akan menghasilkan nilai deviasi menjadi nilai negatif, yang berarti hasilnya tidak valid secara mutlak matematis. Di DES komputer, sistem tidak dibatasi rumus Kingman, beban murni disimulasikan sebagai penumpukan membludak sehingga nilai Wq yang muncul secara logis adalah positif raksasa yang mengular.
                <div class="mt-3 p-3 bg-white border border-warning rounded shadow-sm text-dark">
                    <strong>💡 Saran:</strong> Batasi jumlah kedatangan atau tambah jumlah server pada <strong>${rhoStages.join(" dan ")}</strong>.
                </div>
                </li>`;
            }
            txt += '</ul>';
            warnText.innerHTML = txt;
        } else {
            warnDiv.classList.add('d-none');
        }
    }
}

function resetDES() {
    document.getElementById('desReps').value = 200;
    document.getElementById('desWarmup').value = 10;
    document.getElementById('desObs').value = 30;
    
    let quotaEl = document.getElementById('desQuota');
    if (quotaEl) quotaEl.value = 0;
    
    for (let i = 1; i <= 4; i++) {
        let srv = document.getElementById('srv' + i);
        if (srv) srv.value = 1;
    }

    let pl = document.getElementById('desPlaceholder');
    if (pl) {
        pl.classList.remove('d-none');
        pl.classList.add('d-flex');
    }
    
    document.getElementById('desResultsContainer').classList.add('d-none');
    let btm = document.getElementById('desBottomContainer');
    if (btm) btm.classList.add('d-none');
}

document.addEventListener('DOMContentLoaded', () => {
    loadDESData();
    document.getElementById('btnStartDes').addEventListener('click', runDES);
    document.getElementById('btnResetDes').addEventListener('click', resetDES);
});
