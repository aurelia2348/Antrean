<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QueueFlow Pro - Simulation Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="style.css" rel="stylesheet">
    <style>
        /* Horizontal Stage Cards */
        .stage-card-horizontal {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem !important;
            border-radius: 20px !important;
            box-shadow: var(--card-shadow) !important;
            border: 1px solid rgba(226, 232, 240, 0.5) !important;
            position: relative;
            padding-left: 1.75rem !important;
            min-height: 0;
            transition: var(--transition-smooth);
        }

        .stage-card-horizontal::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 6px;
            background: var(--primary-gradient);
            border-radius: 20px 0 0 20px;
        }

        .stage-card-horizontal:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow-hover) !important;
        }

        /* Metric Box Row (Horizontal styling) */
        .metric-box-horizontal {
            background-color: #f8fafc;
            border-radius: 14px;
            padding: 0.75rem 1.25rem;
            display: flex;
            flex-direction: row;
            align-items: center;
            border: 1px solid rgba(226, 232, 240, 0.6);
            transition: var(--transition-smooth);
            height: 100%;
            min-width: 0;
        }

        .metric-box-horizontal .metric-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 0;
            margin-right: 1.25rem;
            width: 60px;
            flex-shrink: 0;
        }

        .metric-box-horizontal .metric-value-queue {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.25rem;
            height: 100%;
            overflow-y: auto;
            width: 100%;
        }

        .metric-box-horizontal .metric-value-stage {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
        }
    </style>
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
            <div class="status-icon bg-white text-primary rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px; border: 1px solid rgba(79,70,229,0.1);">
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
            <a href="#" class="nav-link active rounded-1 d-flex align-items-center gap-3 py-3 px-3">
                <i class="bi bi-grid-1x2-fill"></i> DASHBOARD
            </a>
            <a href="history.php" class="nav-link text-secondary rounded-1 d-flex align-items-center gap-3 py-3 px-3">
                <i class="bi bi-clock-history"></i> HISTORY
            </a>
            <a href="#" class="nav-link text-secondary rounded-1 d-flex align-items-center gap-3 py-3 px-3">
                <i class="bi bi-bar-chart-line-fill"></i> ANALYSIS
            </a>
        </nav>

        <!-- Spacer -->
        <div class="mt-auto border-top mx-3 mb-3 pt-3">
            <a href="#" class="nav-link text-secondary rounded-1 d-flex align-items-center gap-3 px-3 py-2 fw-semibold">
                <i class="bi bi-gear-fill"></i> SETTINGS
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="d-flex flex-column flex-grow-1 overflow-hidden">
        
        <!-- Header -->
        <header class="top-header border-bottom bg-white d-flex align-items-center justify-content-between px-4" style="height: 70px;">
            <div class="text-secondary fw-medium" style="font-size: 0.95rem;">
                Simulation Dashboard - Web-based Multi-stage Queue System
            </div>
        </header>

        <!-- Content Area -->
        <div class="d-flex flex-grow-1 p-4 gap-4 overflow-hidden align-items-stretch" style="min-height: 0;">
            
            <!-- Left Column: Simulation and Buttons -->
            <div class="d-flex flex-column flex-grow-1 h-100 overflow-hidden" style="min-height: 0;">
                <!-- Stages List (Vertical Flow) -->
                <div class="d-flex flex-column gap-2 flex-grow-1 overflow-hidden justify-content-between mb-3" id="stages-container" style="min-height: 0;">
                    <!-- Stages will be generated by JS -->
                </div>

                <!-- Action Buttons Bottom -->
                <div class="d-flex justify-content-between align-items-center pt-3 border-top border-light flex-shrink-0">
                    <div>
                        <a href="history.php" class="btn btn-light text-secondary fw-semibold border-0 d-flex align-items-center gap-2 px-3 py-2 shadow-sm rounded-2 icon-link-hover text-nowrap">
                            <i class="bi bi-clock-history"></i> Lihat Riwayat
                        </a>
                    </div>
                    <div class="d-flex gap-3">
                        <button id="btnEndSim" class="btn btn-danger fw-semibold d-flex align-items-center gap-2 px-4 py-2 shadow-sm border-0 rounded-2 text-nowrap">
                            <i class="bi bi-stop-circle-fill"></i> Akhiri Simulasi
                        </button>
                        <button id="btnAddUser" class="btn btn-primary fw-semibold d-flex align-items-center gap-2 px-4 py-2 shadow-sm border-0 rounded-2 text-nowrap">
                            <i class="bi bi-plus-circle-fill"></i> Tambah Antrian
                        </button>
                    </div>
                </div>
            </div>

            <!-- Activity Log (Widened) -->
            <div class="log-column flex-shrink-0 h-100 position-relative" style="width: 440px;">
                <div class="card bg-dark-panel text-white h-100 border-0 shadow-lg rounded-4 overflow-hidden">
                    
                    <!-- Mac Window Controls -->
                    <div class="window-controls d-flex align-items-center px-4 py-3 gap-2 border-bottom border-secondary border-opacity-25">
                        <div class="mac-dot bg-danger"></div>
                        <div class="mac-dot bg-warning"></div>
                        <div class="mac-dot bg-success"></div>
                        <span class="ms-3 text-secondary text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 2px;">Activity Log</span>
                    </div>

                    <!-- Log Output -->
                    <div class="card-body log-panel custom-scrollbar p-4" id="logPanel">
                        <!-- Logs will appear here -->
                        <div class="log-entry text-secondary p-0 border-0 mb-2">
                            <span class="fst-italic">_ Listening for system events...</span>
                        </div>
                    </div>
                </div>

                <!-- Floating Action Button -->
                <button id="fabAddUser" class="btn btn-primary fab-btn position-absolute shadow-lg d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; border-radius: 16px; bottom: 20px; right: 20px; z-index: 10;">
                    <i class="bi bi-plus-lg fs-4"></i>
                </button>
            </div>
            
        </div>
    </main>
</div>

<!-- Scripts -->
<script src="script.js?v=<?php echo time(); ?>"></script>

</body>
</html>
