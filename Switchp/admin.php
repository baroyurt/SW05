<?php
// Admin Dashboard - Comprehensive Management Interface
require_once 'db.php';
require_once 'auth.php';

$auth = new Auth($conn);
$auth->requireLogin();

$currentUser = $auth->getUser();

// Check if user is admin
if ($currentUser['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Yönetim Paneli</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #0f172a;
            --dark-light: #1e293b;
            --text: #e2e8f0;
            --text-light: #94a3b8;
            --border: #334155;
        }
        
        body {
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-light) 100%);
            color: var(--text);
            min-height: 100vh;
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--dark-light);
            border-right: 1px solid var(--border);
            overflow-y: auto;
            z-index: 1000;
        }
        
        .logo-section {
            padding: 25px 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .logo-section h1 {
            color: var(--primary);
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .logo-section p {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .nav-section {
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .nav-title {
            padding: 10px 20px;
            color: var(--text-light);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 20px;
            color: var(--text);
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        
        .nav-item:hover {
            background: rgba(59, 130, 246, 0.1);
            padding-left: 25px;
        }
        
        .nav-item.active {
            background: rgba(59, 130, 246, 0.2);
            border-left: 3px solid var(--primary);
            color: var(--primary);
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 30px;
        }
        
        .top-bar {
            background: var(--dark-light);
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border);
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title h2 {
            color: var(--primary);
            font-size: 28px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .content-section {
            display: none;
        }
        
        .content-section.active {
            display: block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--dark-light);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .stat-icon {
            font-size: 32px;
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .card {
            background: var(--dark-light);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid var(--border);
            margin-bottom: 20px;
        }
        
        .card h3 {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-btn {
            padding: 20px;
            background: var(--dark);
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .action-btn:hover {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .action-btn i {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .action-btn span {
            display: block;
            color: var(--text);
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background: var(--dark);
            color: var(--primary);
            font-weight: 600;
        }
        
        tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        
        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal {
            background: var(--dark-light);
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            color: var(--primary);
            font-size: 24px;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text);
            font-size: 28px;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-weight: 600;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--dark);
            color: var(--text);
            font-size: 16px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 10000;
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }
        .toast.info { background: var(--primary); }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-section">
            <h1><i class="fas fa-cogs"></i> Admin Panel</h1>
            <p>Yönetim ve Kontrol Merkezi</p>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Ana Menü</div>
            <button class="nav-item active" data-page="dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </button>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Yönetim</div>
            <button class="nav-item" data-page="switches">
                <i class="fas fa-network-wired"></i>
                <span>Switch Yönetimi</span>
            </button>
            <button class="nav-item" data-page="racks">
                <i class="fas fa-server"></i>
                <span>Rack Yönetimi</span>
            </button>
            <button class="nav-item" data-page="panels">
                <i class="fas fa-th-large"></i>
                <span>Patch Panel Yönetimi</span>
            </button>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Veri İşlemleri</div>
            <button class="nav-item" data-page="backup">
                <i class="fas fa-database"></i>
                <span>Yedekleme</span>
            </button>
            <button class="nav-item" data-page="export">
                <i class="fas fa-file-export"></i>
                <span>Excel Export</span>
            </button>
            <button class="nav-item" data-page="history">
                <i class="fas fa-history"></i>
                <span>Geçmiş Yedekler</span>
            </button>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">SNMP</div>
            <button class="nav-item" data-page="snmp">
                <i class="fas fa-sync-alt"></i>
                <span>SNMP Senkronizasyon</span>
            </button>
            <button class="nav-item" data-page="snmp-config">
                <i class="fas fa-cog"></i>
                <span>SNMP Konfigürasyon</span>
            </button>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Kullanıcı</div>
            <button class="nav-item" onclick="window.location.href='index.php'">
                <i class="fas fa-home"></i>
                <span>Ana Sayfa</span>
            </button>
            <button class="nav-item" onclick="window.location.href='logout.php'" style="background: rgba(239, 68, 68, 0.1); border-left: 3px solid var(--danger);">
                <i class="fas fa-sign-out-alt"></i>
                <span>Çıkış Yap</span>
            </button>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h2 id="page-title-text">Dashboard</h2>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                    <div style="font-size: 12px; color: var(--text-light);">Administrator</div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Section -->
        <div class="content-section active" id="section-dashboard">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--primary);">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <div class="stat-value" id="stat-switches">0</div>
                    <div class="stat-label">Toplam Switch</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--success);">
                        <i class="fas fa-server"></i>
                    </div>
                    <div class="stat-value" id="stat-racks">0</div>
                    <div class="stat-label">Rack Kabin</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--warning);">
                        <i class="fas fa-th-large"></i>
                    </div>
                    <div class="stat-value" id="stat-panels">0</div>
                    <div class="stat-label">Patch Panel</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--danger);">
                        <i class="fas fa-plug"></i>
                    </div>
                    <div class="stat-value" id="stat-ports">0</div>
                    <div class="stat-label">Aktif Port</div>
                </div>
            </div>
            
            <div class="card">
                <h3><i class="fas fa-tasks"></i> Hızlı İşlemler</h3>
                <div class="actions-grid">
                    <div class="action-btn" onclick="switchPage('switches')">
                        <i class="fas fa-plus-circle"></i>
                        <span>Yeni Switch</span>
                    </div>
                    <div class="action-btn" onclick="switchPage('racks')">
                        <i class="fas fa-cube"></i>
                        <span>Yeni Rack</span>
                    </div>
                    <div class="action-btn" onclick="switchPage('panels')">
                        <i class="fas fa-th-large"></i>
                        <span>Yeni Patch Panel</span>
                    </div>
                    <div class="action-btn" onclick="switchPage('backup')">
                        <i class="fas fa-database"></i>
                        <span>Yedekle</span>
                    </div>
                    <div class="action-btn" onclick="switchPage('export')">
                        <i class="fas fa-file-export"></i>
                        <span>Excel Export</span>
                    </div>
                    <div class="action-btn" onclick="switchPage('snmp')">
                        <i class="fas fa-sync-alt"></i>
                        <span>SNMP Sync</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Switch Management Section -->
        <div class="content-section" id="section-switches">
            <div class="card">
                <h3><i class="fas fa-network-wired"></i> Switch Yönetimi</h3>
                <button class="btn btn-primary" onclick="window.open('index.php?action=add-switch', '_blank')">
                    <i class="fas fa-plus"></i> Yeni Switch Ekle
                </button>
                <p style="margin-top: 20px; color: var(--text-light);">
                    Switch eklemek, düzenlemek ve yönetmek için ana panel kullanılır.
                    Yeni switch eklemek için yukarıdaki butonu kullanın veya ana panelden (index.php) işlem yapabilirsiniz.
                </p>
                <div id="switches-list"></div>
            </div>
        </div>
        
        <!-- Rack Management Section -->
        <div class="content-section" id="section-racks">
            <div class="card">
                <h3><i class="fas fa-server"></i> Rack Yönetimi</h3>
                <button class="btn btn-primary" onclick="window.open('index.php?action=add-rack', '_blank')">
                    <i class="fas fa-plus"></i> Yeni Rack Ekle
                </button>
                <p style="margin-top: 20px; color: var(--text-light);">
                    Rack kabinlerini eklemek, düzenlemek ve yönetmek için ana panel kullanılır.
                </p>
                <div id="racks-list"></div>
            </div>
        </div>
        
        <!-- Panel Management Section -->
        <div class="content-section" id="section-panels">
            <div class="card">
                <h3><i class="fas fa-th-large"></i> Patch Panel Yönetimi</h3>
                <button class="btn btn-primary" onclick="window.open('index.php?action=add-panel', '_blank')">
                    <i class="fas fa-plus"></i> Yeni Patch Panel Ekle
                </button>
                <p style="margin-top: 20px; color: var(--text-light);">
                    Patch panelleri eklemek, düzenlemek ve yönetmek için ana panel kullanılır.
                </p>
                <div id="panels-list"></div>
            </div>
        </div>
        
        <!-- Backup Section -->
        <div class="content-section" id="section-backup">
            <div class="card">
                <h3><i class="fas fa-database"></i> Yedekleme</h3>
                <button class="btn btn-success" onclick="createBackup()">
                    <i class="fas fa-save"></i> Yeni Yedek Oluştur
                </button>
                <div id="backup-status" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <!-- Export Section -->
        <div class="content-section" id="section-export">
            <div class="card">
                <h3><i class="fas fa-file-export"></i> Excel Export</h3>
                <div class="actions-grid" style="margin-top: 20px;">
                    <div class="action-btn" onclick="exportData('switches')">
                        <i class="fas fa-network-wired"></i>
                        <span>Switch Verisi</span>
                    </div>
                    <div class="action-btn" onclick="exportData('racks')">
                        <i class="fas fa-server"></i>
                        <span>Rack Verisi</span>
                    </div>
                    <div class="action-btn" onclick="exportData('panels')">
                        <i class="fas fa-th-large"></i>
                        <span>Panel Verisi</span>
                    </div>
                    <div class="action-btn" onclick="exportData('all')">
                        <i class="fas fa-database"></i>
                        <span>Tüm Veri</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- History Section -->
        <div class="content-section" id="section-history">
            <div class="card">
                <h3><i class="fas fa-history"></i> Geçmiş Yedekler</h3>
                <div id="backups-list"></div>
            </div>
        </div>
        
        <!-- SNMP Section -->
        <div class="content-section" id="section-snmp">
            <div class="card">
                <h3><i class="fas fa-sync-alt"></i> SNMP Veri Senkronizasyonu</h3>
                <button class="btn btn-primary" onclick="syncSNMP()">
                    <i class="fas fa-sync"></i> SNMP Verilerini Senkronize Et
                </button>
                <div id="snmp-status" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <!-- SNMP Configuration Section -->
        <div class="content-section" id="section-snmp-config">
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px; border-bottom: 1px solid var(--border);">
                    <h3><i class="fas fa-cog"></i> SNMP Konfigürasyon</h3>
                    <p style="color: var(--text-light); margin-top: 10px; margin-bottom: 0;">
                        SNMP yapılandırma ayarlarını, switch'leri, bildirim ayarlarını ve alarm seviyelerini yönetin.
                    </p>
                </div>
                <iframe 
                    src="admin_snmp_config.php" 
                    style="width: 100%; height: calc(100vh - 200px); border: none; display: block;"
                    frameborder="0"
                    id="snmp-config-iframe">
                </iframe>
            </div>
        </div>
    </div>
    
    <script>
        // Navigation
        function switchPage(pageName) {
            // Update navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-page="${pageName}"]`)?.classList.add('active');
            
            // Update content
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(`section-${pageName}`)?.classList.add('active');
            
            // Update page title
            const titles = {
                'dashboard': 'Dashboard',
                'switches': 'Switch Yönetimi',
                'racks': 'Rack Yönetimi',
                'panels': 'Patch Panel Yönetimi',
                'backup': 'Yedekleme',
                'export': 'Excel Export',
                'history': 'Geçmiş Yedekler',
                'snmp': 'SNMP Senkronizasyon',
                'snmp-config': 'SNMP Konfigürasyon'
            };
            document.getElementById('page-title-text').textContent = titles[pageName] || 'Admin Panel';
        }
        
        // Setup navigation
        document.querySelectorAll('.nav-item[data-page]').forEach(item => {
            item.addEventListener('click', () => {
                const page = item.getAttribute('data-page');
                switchPage(page);
            });
        });
        
        // Load dashboard stats
        async function loadStats() {
            try {
                const response = await fetch('getData.php');
                const data = await response.json();
                
                if (data.switches) {
                    document.getElementById('stat-switches').textContent = data.switches.length;
                }
                if (data.racks) {
                    document.getElementById('stat-racks').textContent = data.racks.length;
                }
                if (data.patchPanels) {
                    document.getElementById('stat-panels').textContent = data.patchPanels.length;
                }
                
                // Count active ports
                let activePorts = 0;
                if (data.ports) {
                    // ports is an object with switch IDs as keys
                    Object.values(data.ports).forEach(switchPorts => {
                        if (Array.isArray(switchPorts)) {
                            activePorts += switchPorts.filter(p => p.connected_device || p.panel_port_id).length;
                        }
                    });
                }
                document.getElementById('stat-ports').textContent = activePorts;
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        
        // Backup functions
        async function createBackup() {
            const statusDiv = document.getElementById('backup-status');
            statusDiv.innerHTML = '<p style="color: var(--primary);"><i class="fas fa-spinner fa-spin"></i> Yedek oluşturuluyor...</p>';
            
            try {
                const response = await fetch('backup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=backup'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    statusDiv.innerHTML = `<p style="color: var(--success);"><i class="fas fa-check-circle"></i> ${data.message}</p>`;
                    showToast('Yedekleme başarılı!', 'success');
                } else {
                    statusDiv.innerHTML = `<p style="color: var(--danger);"><i class="fas fa-exclamation-circle"></i> ${data.error}</p>`;
                    showToast('Yedekleme başarısız!', 'error');
                }
            } catch (error) {
                statusDiv.innerHTML = `<p style="color: var(--danger);"><i class="fas fa-exclamation-circle"></i> Hata: ${error.message}</p>`;
                showToast('Bir hata oluştu!', 'error');
            }
        }
        
        // Export functions
        function exportData(type) {
            const url = `getData.php?export=${type}`;
            window.open(url, '_blank');
            showToast(`${type} verileri indiriliyor...`, 'info');
        }
        
        // SNMP sync
        async function syncSNMP() {
            const statusDiv = document.getElementById('snmp-status');
            statusDiv.innerHTML = '<p style="color: var(--primary);"><i class="fas fa-spinner fa-spin"></i> SNMP verileri senkronize ediliyor...</p>';
            
            try {
                const response = await fetch('snmp_data_api.php?action=sync_to_switches');
                const data = await response.json();
                
                if (data.success) {
                    statusDiv.innerHTML = `<p style="color: var(--success);"><i class="fas fa-check-circle"></i> ${data.message}</p>`;
                    showToast('SNMP senkronizasyonu başarılı!', 'success');
                    loadStats();
                } else {
                    statusDiv.innerHTML = `<p style="color: var(--danger);"><i class="fas fa-exclamation-circle"></i> ${data.error || 'Senkronizasyon başarısız'}</p>`;
                    showToast('SNMP senkronizasyonu başarısız!', 'error');
                }
            } catch (error) {
                statusDiv.innerHTML = `<p style="color: var(--danger);"><i class="fas fa-exclamation-circle"></i> Hata: ${error.message}</p>`;
                showToast('Bir hata oluştu!', 'error');
            }
        }
        
        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadStats();
        });
    </script>
</body>
</html>
