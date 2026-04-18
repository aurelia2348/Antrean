class QueueSimulation {
    constructor() {
        this.currentId = 0;
        this.currentSessionId = 1;
        this.totalStages = 4;

        // Internal States
        this.queues = { 1: [], 2: [], 3: [], 4: [] };
        this.stages = { 1: null, 2: null, 3: null, 4: null };
        this.historyData = {};

        // UI Elements
        this.logPanel = document.getElementById('logPanel');
        this.stagesContainer = document.getElementById('stages-container');

        this.initUI();
        this.bindEvents();
        this.loadSessionId();
    }

    getStageName(i) {
        const names = ["Arrival & Screening", "Documentation", "Verification", "Approval"];
        return names[i - 1] || "Stage";
    }

    initUI() {
        this.stagesContainer.innerHTML = '';
        for (let i = 1; i <= this.totalStages; i++) {
            const card = document.createElement('div');
            card.className = 'card border-0 shadow-sm rounded-4 mb-4 stage-card position-relative overflow-hidden bg-white';
            card.innerHTML = `
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center px-4 pt-4 pb-3">
                    <h6 class="fw-bold mb-0 text-dark">Tahap ${i}: ${this.getStageName(i)}</h6>
                    <span id="badge-status-${i}" class="badge rounded-pill bg-secondary text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">IDLE</span>
                </div>

                <!-- Body -->
                <div class="row px-4 pb-3 gx-3">
                    <!-- Queue Box -->
                    <div class="col-6">
                        <div class="metric-box">
                            <div class="metric-label">QUEUE</div>
                            <div id="queue-display-${i}" class="metric-value-queue d-flex flex-wrap justify-content-center align-items-center" style="min-height: 48px;">
                                <span class="text-secondary opacity-25">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stage Box -->
                    <div class="col-6">
                        <div id="stage-box-${i}" class="metric-box transition-all">
                            <div class="metric-label">STAGE</div>
                            <div id="stage-display-${i}" class="metric-value-stage d-flex flex-wrap justify-content-center align-items-center" style="min-height: 48px;">
                                <span class="text-secondary opacity-25">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="row px-4 pb-4 gx-3">
                    ${i > 1 ? `
                    <div class="col-${i === 4 ? '12' : '6'}">
                        <button id="btn-keluar-${i}" class="btn btn-light w-100 fw-bold text-secondary text-uppercase py-2 shadow-sm" style="font-size: 0.85rem;" onclick="sim.leaveStage(${i}, false)">
                            Keluar
                        </button>
                    </div>
                    ` : ''}
                    ${i < 4 ? `
                    <div class="col-${i === 1 ? '12' : '6'}">
                        <button id="btn-lanjut-${i}" class="btn btn-primary w-100 fw-bold text-uppercase py-2 shadow-sm" style="font-size: 0.85rem;" onclick="sim.leaveStage(${i}, true)">
                            Lanjut
                        </button>
                    </div>
                    ` : ''}
                </div>
            `;
            this.stagesContainer.appendChild(card);
        }
        this.render();
    }

    bindEvents() {
        document.getElementById('btnAddUser').addEventListener('click', () => this.addUser());
        const fab = document.getElementById('fabAddUser');
        if (fab) fab.addEventListener('click', () => this.addUser());
        
        const endBtn = document.getElementById('btnEndSim');
        if (endBtn) endBtn.addEventListener('click', () => this.resetSimulation());
    }

    loadSessionId() {
        fetch('max_session.php')
            .then(res => res.text())
            .then(txt => {
                let sId = parseInt(txt);
                if (sId > 0) {
                    this.currentSessionId = sId;
                }
                this.checkLocalSession();
            })
            .catch(e => {
                this.checkLocalSession();
            });
    }

    checkLocalSession() {
        let localId = localStorage.getItem('activeVaksinasiId');
        if (localId && parseInt(localId) > this.currentSessionId) {
            this.currentSessionId = parseInt(localId);
            fetch('max_session.php?set=' + this.currentSessionId);
        } else {
            localStorage.setItem('activeVaksinasiId', this.currentSessionId);
        }
        this.addLog(`Sistem Siap: Vaksinasi #${this.currentSessionId}`, 'success');
    }

    resetSimulation() {
        if (!confirm('Akhiri simulasi saat ini? Antrean akan direset dari 1 untuk sesi berikutnya.')) return;

        this.currentId = 0;
        this.currentSessionId++;
        localStorage.setItem('activeVaksinasiId', this.currentSessionId);
        fetch('max_session.php?set=' + this.currentSessionId);

        this.queues = { 1: [], 2: [], 3: [], 4: [] };
        this.stages = { 1: null, 2: null, 3: null, 4: null };
        this.historyData = {};

        this.logPanel.innerHTML = '';
        this.addLog(`=== BUKA VAKSINASI #${this.currentSessionId} ===`, 'primary');
        this.render();
    }

    addUser() {
        this.currentId++;
        const userId = this.currentId;

        this.historyData[userId] = {
            id: userId,
            selesai: null,
            history: [] // Stage records go here
        };

        this.recordTime(userId, 1, 'masuk_queue');
        this.queues[1].push(userId);

        this.addLog(`Antrian baru ditambahkan ke Tahap 1. (Entitas #${userId})`, 'event');

        // Otomatis tarik ke stage 1 jika kosong
        this.enterStage(1);

        this.render();
    }

    recordTime(userId, stageNum, type) {
        let user = this.historyData[userId];
        if (!user) return;

        let stageHistory = user.history.find(h => h.stage === stageNum);
        if (!stageHistory) {
            stageHistory = { stage: stageNum, masuk_queue: null, masuk_stage: null, keluar_stage: null };
            user.history.push(stageHistory);
        }
        stageHistory[type] = Date.now();
    }

    enterStage(stageNum) {
        if (this.queues[stageNum].length === 0) return;
        if (this.stages[stageNum] !== null) return; // Stage is occupied

        let userId = this.queues[stageNum].shift(); // FIFO
        this.stages[stageNum] = userId;

        this.recordTime(userId, stageNum, 'masuk_stage');
        this.addLog(`Tarik antrian: Entitas #${userId} mulai diproses di Tahap ${stageNum}.`, 'info');
        this.render();
    }

    leaveStage(stageNum, isLanjut) {
        if (this.stages[stageNum] === null) return;

        let userId = this.stages[stageNum];

        this.recordTime(userId, stageNum, 'keluar_stage');
        this.stages[stageNum] = null;

        if (isLanjut && stageNum < this.totalStages) {
            let nextStage = stageNum + 1;
            this.recordTime(userId, nextStage, 'masuk_queue');
            this.queues[nextStage].push(userId);
            this.addLog(`Entitas #${userId} berpindah Tahap ${stageNum} \u2192 Tahap ${nextStage}.`, 'info');

            // Otomatis masuk tahap selanjutnya
            this.enterStage(nextStage);
        } else {
            // Patient finished or dropped out
            this.historyData[userId].selesai = Date.now();
            let msg = (stageNum === this.totalStages && isLanjut) ? `selesai seluruh tahapan dan keluar sistem.` : `keluar sistem pada Tahap ${stageNum}.`;
            let color = (stageNum === this.totalStages && isLanjut) ? 'success' : 'warn';
            this.addLog(`Sistem: Entitas #${userId} ${msg}`, color);
            this.saveUser(userId);
        }

        // Otomatis tarik antrean di stage yang barusan kosong
        this.enterStage(stageNum);

        this.render();
    }

    addLog(msg, type = 'info') {
        const date = new Date();
        const time = date.toLocaleTimeString('id-ID', { hour12: false });

        let typeLabel = 'INFO';
        let colorClass = 'log-type-info';

        if (type === 'event' || type === 'primary') { typeLabel = 'EVENT'; colorClass = 'log-type-event'; }
        else if (type === 'warn' || type === 'danger') { typeLabel = 'WARN'; colorClass = 'log-type-warn'; }
        else if (type === 'success') { typeLabel = 'INFO'; colorClass = 'log-type-info'; }

        const div = document.createElement('div');
        div.className = `log-entry d-flex gap-3`;
        div.innerHTML = `
            <div class="log-time">[${time}]</div>
            <div class="${colorClass}" style="width: 45px; flex-shrink: 0;">${typeLabel}:</div>
            <div class="text-white opacity-75 flex-grow-1">${msg}</div>
        `;

        this.logPanel.appendChild(div);
        this.logPanel.scrollTop = this.logPanel.scrollHeight;
    }

    padStr(num) {
        return num.toString().padStart(2, '0');
    }

    render() {
        for (let i = 1; i <= this.totalStages; i++) {
            const badgeStatusEl = document.getElementById(`badge-status-${i}`);
            let status = 'IDLE';
            let badgeClass = 'bg-secondary bg-opacity-10 text-secondary';

            // Render Queue
            const qEl = document.getElementById(`queue-display-${i}`);
            if (this.queues[i].length === 0) {
                qEl.innerHTML = `<span class="text-secondary opacity-25">-</span>`;
            } else {
                status = 'PENDING';
                badgeClass = 'bg-warning bg-opacity-10 text-warning';
                
                // If only 1 person in queue, show huge padded number.
                // Else, show badges.
                if (this.queues[i].length === 1) {
                    qEl.innerHTML = `<span>${this.padStr(this.queues[i][0])}</span>`;
                } else {
                    qEl.innerHTML = this.queues[i].map(id => `<span class="badge bg-primary bg-opacity-10 text-primary queue-badge">${this.padStr(id)}</span>`).join('');
                }
            }

            // Render Stage
            const sEl = document.getElementById(`stage-display-${i}`);
            const sBox = document.getElementById(`stage-box-${i}`);

            if (this.stages[i] === null) {
                sEl.innerHTML = `<span class="text-secondary opacity-25">-</span>`;
                sBox.classList.remove('active-stage');
            } else {
                status = i === 1 ? 'PROCESSING' : 'ACTIVE';
                badgeClass = i === 1 ? 'bg-primary bg-opacity-10 text-primary' : 'bg-success bg-opacity-10 text-success';
                sEl.innerHTML = `<span>${this.padStr(this.stages[i])}</span>`;
                sBox.classList.add('active-stage');
            }

            // Update badge status
            badgeStatusEl.className = `badge rounded-pill ${badgeClass}`;
            badgeStatusEl.innerText = status;

            // Buttons state
            const btnKeluar = document.getElementById(`btn-keluar-${i}`);
            const btnLanjut = document.getElementById(`btn-lanjut-${i}`);

            let stageEmpty = (this.stages[i] === null);
            if (btnKeluar) btnKeluar.disabled = stageEmpty;
            if (btnLanjut) btnLanjut.disabled = stageEmpty;
        }
    }

    saveUser(userId) {
        const data = this.historyData[userId];
        data.session_id = this.currentSessionId;
        fetch('save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                console.log('Saved data to server for User #' + userId);
            })
            .catch(err => console.error('Error saving user:', err));
    }
}

let sim;
document.addEventListener('DOMContentLoaded', () => {
    sim = new QueueSimulation();
});
