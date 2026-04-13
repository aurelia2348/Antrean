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

    initUI() {
        for (let i = 1; i <= this.totalStages; i++) {
            const card = document.createElement('div');
            card.className = 'card shadow-sm border-0 rounded-4 mb-4 stage-card';
            card.innerHTML = `
                <div class="card-header bg-white pt-3 pb-2 border-0">
                    <h5 class="fw-bold text-primary mb-0">Tahap ${i}</h5>
                </div>
                <div class="card-body bg-white border-top rounded-bottom-4 pt-4">
                    <div class="row align-items-center">
                        <!-- Queue State -->
                        <div class="col-md-5">
                            <div class="p-3 bg-light rounded-3 shadow-sm text-center min-h-100">
                                <h6 class="text-secondary fw-bold mb-3 text-uppercase" style="letter-spacing: 1px; font-size: 0.8rem;">Queue ${i}</h6>
                                <div id="queue-display-${i}" class="d-flex flex-wrap justify-content-center min-h-queue">
                                    <span class="empty-state">[]</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="col-md-2 text-center my-3 my-md-0 d-flex flex-column justify-content-center">
                            <div class="d-grid gap-2">

                                ${i < 4 ? `
                                <button id="btn-lanjut-${i}" class="btn btn-sm btn-primary fw-bold text-nowrap rounded-pill shadow-sm py-2" onclick="sim.leaveStage(${i}, true)">
                                    Lanjut ➔
                                </button>
                                ` : ''}
                                ${i > 1 ? `
                                <button id="btn-keluar-${i}" class="btn btn-sm btn-danger fw-bold text-nowrap rounded-pill shadow-sm py-2" onclick="sim.leaveStage(${i}, false)">
                                    Keluar 🛑
                                </button>
                                ` : ''}
                            </div>
                        </div>
                        
                        <!-- Stage State -->
                        <div class="col-md-5">
                            <div class="p-3 bg-light rounded-3 shadow-sm text-center min-h-100 transition-all border" id="stage-box-${i}">
                                <h6 class="text-secondary fw-bold mb-3 text-uppercase" style="letter-spacing: 1px; font-size: 0.8rem;">Stage ${i}</h6>
                                <div id="stage-display-${i}" class="d-flex flex-wrap justify-content-center min-h-queue">
                                    <span class="empty-state">[]</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            this.stagesContainer.appendChild(card);
        }
        this.render();
    }

    bindEvents() {
        document.getElementById('btnAddUser').addEventListener('click', () => this.addUser());
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

        this.addLog(`Pasien ${userId} masuk Queue 1`, 'info');

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
        this.addLog(`Pasien ${userId} masuk Stage ${stageNum}`, 'success');
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
            this.addLog(`Pasien ${userId} lanjut dari Stage ${stageNum} ke Queue ${nextStage}`, 'warning');

            // Otomatis masuk tahap selanjutnya
            this.enterStage(nextStage);
        } else {
            // Patient finished or dropped out
            this.historyData[userId].selesai = Date.now();
            let msg = (stageNum === this.totalStages) ? `keluar Stage ${this.totalStages} dan Selesai! 🎉` : `keluar di Stage ${stageNum} (Tidak Lanjut)`;
            let color = (stageNum === this.totalStages) ? 'primary' : 'danger';
            this.addLog(`Pasien ${userId} ${msg}`, color);
            this.saveUser(userId);
        }

        // Otomatis tarik antrean di stage yang barusan kosong
        this.enterStage(stageNum);

        this.render();
    }

    addLog(msg, colorType = 'light') {
        const date = new Date();
        const time = date.toLocaleTimeString('id-ID', { hour12: false });

        const div = document.createElement('div');
        div.className = `log-entry text-${colorType}`;
        div.innerHTML = `<span class="log-time">[${time}]</span> ${msg}`;

        this.logPanel.appendChild(div);
        this.logPanel.scrollTop = this.logPanel.scrollHeight;
    }

    render() {
        for (let i = 1; i <= this.totalStages; i++) {
            // Render Queue
            const qEl = document.getElementById(`queue-display-${i}`);
            if (this.queues[i].length === 0) {
                qEl.innerHTML = `<span class="empty-state">[]</span>`;
            } else {
                qEl.innerHTML = this.queues[i].map(id => `<span class="badge bg-secondary queue-badge shadow-sm">${id}</span>`).join('');
            }

            // Render Stage
            const sEl = document.getElementById(`stage-display-${i}`);
            const sBox = document.getElementById(`stage-box-${i}`);

            if (this.stages[i] === null) {
                sEl.innerHTML = `<span class="empty-state">[]</span>`;
                sBox.classList.remove('active-stage');
            } else {
                sEl.innerHTML = `<span class="badge bg-primary queue-badge px-3 py-2 border border-2 border-white shadow">${this.stages[i]}</span>`;
                sBox.classList.add('active-stage');
            }

            // Buttons state
            const btnKeluar = document.getElementById(`btn-keluar-${i}`);
            const btnLanjut = document.getElementById(`btn-lanjut-${i}`);

            // Lanjut / Keluar = Stage is not empty
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
