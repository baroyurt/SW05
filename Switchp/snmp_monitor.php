<?php
/**
 * SNMP Monitoring Dashboard
 * Real-time monitoring of SNMP Worker status and data collection
 */

session_start();
require_once 'db.php';
require_once 'auth.php';

// Initialize auth
$auth = new Auth($conn);

// Check if user is authenticated
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Cache control headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$pageTitle = "SNMP Monitoring";
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .monitoring-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .status-card h3 {
            margin-top: 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-online { background: #4CAF50; }
        .status-offline { background: #f44336; }
        .status-warning { background: #ff9800; }
        
        .metric {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .metric:last-child {
            border-bottom: none;
        }
        
        .metric-value {
            font-weight: bold;
            color: #2196F3;
        }
        
        .data-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #2196F3;
            color: white;
            padding: 15px;
            text-align: left;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .data-table tr:hover {
            background: #f5f5f5;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .log-container {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        
        .log-entry {
            padding: 5px 0;
        }
        
        .log-timestamp {
            color: #4CAF50;
        }
        
        .log-level-info {
            color: #2196F3;
        }
        
        .log-level-error {
            color: #f44336;
        }
        
        .log-level-warning {
            color: #ff9800;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="monitoring-container">
        <h1><i class="fas fa-chart-line"></i> SNMP Monitoring Kontrol Paneli</h1>
        
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="refreshData()">
                <i class="fas fa-sync"></i> Yenile
            </button>
            <button class="btn btn-success" onclick="testConnection()">
                <i class="fas fa-plug"></i> Bağlantı Test Et
            </button>
            <button class="btn btn-warning" onclick="viewLogs()">
                <i class="fas fa-file-alt"></i> Log Dosyasını Görüntüle
            </button>
        </div>
        
        <!-- Status Cards -->
        <div class="status-grid">
            <!-- SNMP Worker Status -->
            <div class="status-card">
                <h3>
                    <span class="status-indicator" id="worker-status"></span>
                    SNMP Worker Durumu
                </h3>
                <div class="metric">
                    <span>Durum:</span>
                    <span class="metric-value" id="worker-state">Kontrol ediliyor...</span>
                </div>
                <div class="metric">
                    <span>Son Çalışma:</span>
                    <span class="metric-value" id="last-run">-</span>
                </div>
                <div class="metric">
                    <span>Toplanan Veri:</span>
                    <span class="metric-value" id="data-count">-</span>
                </div>
            </div>
            
            <!-- Database Connection -->
            <div class="status-card">
                <h3>
                    <span class="status-indicator status-online"></span>
                    Veritabanı Bağlantısı
                </h3>
                <div class="metric">
                    <span>Durum:</span>
                    <span class="metric-value">Bağlı</span>
                </div>
                <div class="metric">
                    <span>Toplam Switch:</span>
                    <span class="metric-value" id="total-switches">-</span>
                </div>
                <div class="metric">
                    <span>SNMP Aktif:</span>
                    <span class="metric-value" id="snmp-enabled-switches">-</span>
                </div>
            </div>
            
            <!-- Config Status -->
            <div class="status-card">
                <h3>
                    <span class="status-indicator" id="config-status"></span>
                    Yapılandırma Durumu
                </h3>
                <div class="metric">
                    <span>Config Dosyası:</span>
                    <span class="metric-value" id="config-exists">-</span>
                </div>
                <div class="metric">
                    <span>Yapılandırılmış Switch:</span>
                    <span class="metric-value" id="config-switches">-</span>
                </div>
                <div class="metric">
                    <span>SNMP Versiyonu:</span>
                    <span class="metric-value" id="snmp-version">-</span>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="status-card">
                <h3>
                    <i class="fas fa-clock"></i>
                    Son Aktivite
                </h3>
                <div class="metric">
                    <span>Son Port Taraması:</span>
                    <span class="metric-value" id="last-scan">-</span>
                </div>
                <div class="metric">
                    <span>Başarılı:</span>
                    <span class="metric-value" id="success-count">-</span>
                </div>
                <div class="metric">
                    <span>Başarısız:</span>
                    <span class="metric-value" id="error-count">-</span>
                </div>
            </div>
        </div>
        
        <!-- Recent SNMP Data -->
        <div class="data-table">
            <h3 style="padding: 15px; margin: 0; background: #f5f5f5;">
                <i class="fas fa-database"></i> Son Toplanan SNMP Verileri
            </h3>
            <div id="snmp-data-container">
                <div class="empty-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Veriler yükleniyor...</p>
                </div>
            </div>
        </div>
        
        <!-- Logs -->
        <div class="log-container" id="log-container">
            <strong>Log Kayıtları:</strong>
            <div id="log-entries">
                <div class="log-entry">Loglar yükleniyor...</div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds
        let autoRefreshInterval;
        
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(refreshData, 30000);
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }
        
        function refreshData() {
            console.log('Refreshing monitoring data...');
            checkWorkerStatus();
            loadSwitchStats();
            loadConfigStatus();
            loadRecentData();
            loadRecentLogs();
        }
        
        function checkWorkerStatus() {
            // Check if SNMP Worker service is running (Windows)
            fetch('snmp_monitor_api.php?action=check_worker')
                .then(response => response.json())
                .then(data => {
                    const statusEl = document.getElementById('worker-status');
                    const stateEl = document.getElementById('worker-state');
                    
                    if (data.running) {
                        statusEl.className = 'status-indicator status-online';
                        stateEl.textContent = 'Çalışıyor';
                    } else {
                        statusEl.className = 'status-indicator status-offline';
                        stateEl.textContent = 'Durdurulmuş';
                    }
                    
                    document.getElementById('last-run').textContent = data.last_run || 'Bilinmiyor';
                    document.getElementById('data-count').textContent = data.data_count || '0';
                })
                .catch(err => {
                    console.error('Worker status check failed:', err);
                    document.getElementById('worker-status').className = 'status-indicator status-warning';
                    document.getElementById('worker-state').textContent = 'Kontrol edilemedi';
                });
        }
        
        function loadSwitchStats() {
            fetch('snmp_monitor_api.php?action=switch_stats')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('total-switches').textContent = data.total || '0';
                    document.getElementById('snmp-enabled-switches').textContent = data.snmp_enabled || '0';
                })
                .catch(err => console.error('Switch stats failed:', err));
        }
        
        function loadConfigStatus() {
            fetch('snmp_monitor_api.php?action=config_status')
                .then(response => response.json())
                .then(data => {
                    const statusEl = document.getElementById('config-status');
                    
                    if (data.exists) {
                        statusEl.className = 'status-indicator status-online';
                        document.getElementById('config-exists').textContent = 'Mevcut';
                        document.getElementById('config-switches').textContent = data.switches_count || '0';
                        document.getElementById('snmp-version').textContent = data.snmp_version || 'v3';
                    } else {
                        statusEl.className = 'status-indicator status-warning';
                        document.getElementById('config-exists').textContent = 'Bulunamadı';
                    }
                })
                .catch(err => console.error('Config status failed:', err));
        }
        
        function loadRecentData() {
            fetch('snmp_monitor_api.php?action=recent_data')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('snmp-data-container');
                    
                    if (data.data && data.data.length > 0) {
                        let html = '<table><thead><tr>';
                        html += '<th>Switch</th><th>Port</th><th>Durum</th><th>Hız</th><th>Son Güncelleme</th>';
                        html += '</tr></thead><tbody>';
                        
                        data.data.forEach(row => {
                            html += '<tr>';
                            html += `<td>${row.switch_name}</td>`;
                            html += `<td>${row.port_name || 'N/A'}</td>`;
                            html += `<td>${row.status || 'N/A'}</td>`;
                            html += `<td>${row.speed || 'N/A'}</td>`;
                            html += `<td>${row.last_update || 'N/A'}</td>`;
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>Henüz veri toplanmadı</p></div>';
                    }
                })
                .catch(err => {
                    console.error('Recent data failed:', err);
                    document.getElementById('snmp-data-container').innerHTML = 
                        '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Veri yüklenemedi</p></div>';
                });
        }
        
        function loadRecentLogs() {
            fetch('snmp_monitor_api.php?action=recent_logs')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('log-entries');
                    
                    if (data.logs && data.logs.length > 0) {
                        container.innerHTML = data.logs.map(log => {
                            const level = log.level || 'info';
                            return `<div class="log-entry">
                                <span class="log-timestamp">${log.timestamp}</span>
                                <span class="log-level-${level}">[${level.toUpperCase()}]</span>
                                ${log.message}
                            </div>`;
                        }).join('');
                    } else {
                        container.innerHTML = '<div class="log-entry">Henüz log kaydı yok</div>';
                    }
                })
                .catch(err => console.error('Logs failed:', err));
        }
        
        function testConnection() {
            const originalText = event.target.innerHTML;
            event.target.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test ediliyor...';
            event.target.disabled = true;
            
            fetch('snmp_monitor_api.php?action=test_connection')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Bağlantı Başarılı!\n\n' + 
                              'Config dosyası: ' + (data.config_exists ? 'Mevcut' : 'Bulunamadı') + '\n' +
                              'Veritabanı: Bağlı\n' +
                              'SNMP Yapılandırması: ' + (data.snmp_configured ? 'OK' : 'Eksik'));
                    } else {
                        alert('❌ Bağlantı Başarısız!\n\n' + (data.error || 'Bilinmeyen hata'));
                    }
                })
                .catch(err => {
                    alert('❌ Test Hatası: ' + err.message);
                })
                .finally(() => {
                    event.target.innerHTML = originalText;
                    event.target.disabled = false;
                });
        }
        
        function viewLogs() {
            window.open('view_snmp_logs.php', '_blank');
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            refreshData();
            startAutoRefresh();
        });
        
        // Stop auto-refresh when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });
    </script>
</body>
</html>
