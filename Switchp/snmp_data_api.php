<?php
/**
 * SNMP Data API
 * Fetches data collected by the SNMP worker and provides it to the web interface
 */

require_once 'db.php';
require_once 'auth.php';

$auth = new Auth($conn);
$auth->requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_devices':
            getDevices($conn);
            break;
            
        case 'get_device_details':
            $deviceId = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;
            getDeviceDetails($conn, $deviceId);
            break;
            
        case 'get_port_status':
            $deviceId = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;
            getPortStatus($conn, $deviceId);
            break;
            
        case 'get_alarms':
            getActiveAlarms($conn);
            break;
            
        case 'sync_to_switches':
            syncToSwitches($conn, $auth);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Get all SNMP devices
 */
function getDevices($conn) {
    $sql = "SELECT 
                id, name, ip_address, vendor, model, status, enabled,
                total_ports, last_poll_time, last_successful_poll,
                created_at, updated_at
            FROM snmp_devices 
            ORDER BY name";
    
    $result = $conn->query($sql);
    $devices = [];
    
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'devices' => $devices
    ]);
}

/**
 * Get device details with latest polling data
 */
function getDeviceDetails($conn, $deviceId) {
    // Get device info
    $stmt = $conn->prepare("SELECT * FROM snmp_devices WHERE id = ?");
    $stmt->bind_param("i", $deviceId);
    $stmt->execute();
    $device = $stmt->get_result()->fetch_assoc();
    
    if (!$device) {
        throw new Exception('Device not found');
    }
    
    // Get latest polling data
    $stmt = $conn->prepare("SELECT * FROM device_polling_data 
                           WHERE device_id = ? 
                           ORDER BY poll_timestamp DESC 
                           LIMIT 10");
    $stmt->bind_param("i", $deviceId);
    $stmt->execute();
    $pollingHistory = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pollingHistory[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'device' => $device,
        'polling_history' => $pollingHistory
    ]);
}

/**
 * Get port status for a device
 */
function getPortStatus($conn, $deviceId) {
    $stmt = $conn->prepare("
        SELECT 
            port_number, port_name, port_alias, port_description,
            admin_status, oper_status, port_speed, port_mtu,
            vlan_id, vlan_name, mac_address, mac_addresses,
            last_seen, poll_timestamp
        FROM port_status_data 
        WHERE device_id = ?
        AND poll_timestamp = (
            SELECT MAX(poll_timestamp) 
            FROM port_status_data 
            WHERE device_id = ?
        )
        ORDER BY port_number
    ");
    $stmt->bind_param("ii", $deviceId, $deviceId);
    $stmt->execute();
    
    $ports = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ports[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'ports' => $ports
    ]);
}

/**
 * Get active alarms
 */
function getActiveAlarms($conn) {
    $sql = "SELECT 
                a.id, a.device_id, a.alarm_type, a.severity, a.status,
                a.port_number, a.title, a.message,
                a.occurrence_count, a.first_occurrence, a.last_occurrence,
                d.name as device_name, d.ip_address as device_ip
            FROM alarms a
            LEFT JOIN snmp_devices d ON a.device_id = d.id
            WHERE a.status = 'ACTIVE'
            ORDER BY a.severity DESC, a.last_occurrence DESC";
    
    $result = $conn->query($sql);
    $alarms = [];
    
    while ($row = $result->fetch_assoc()) {
        $alarms[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'alarms' => $alarms
    ]);
}

/**
 * Sync SNMP worker data to main switches table
 */
function syncToSwitches($conn, $auth) {
    $user = $auth->getUser();
    $synced = 0;
    $errors = [];
    
    $conn->begin_transaction();
    
    try {
        // Get all active SNMP devices
        $result = $conn->query("SELECT * FROM snmp_devices WHERE enabled = 1");
        
        while ($snmpDevice = $result->fetch_assoc()) {
            // Check if switch exists in main table
            $stmt = $conn->prepare("SELECT id FROM switches WHERE ip = ?");
            $stmt->bind_param("s", $snmpDevice['ip_address']);
            $stmt->execute();
            $existingSwitch = $stmt->get_result()->fetch_assoc();
            
            if ($existingSwitch) {
                // Update existing switch
                $stmt = $conn->prepare("UPDATE switches SET 
                    name = ?, brand = ?, model = ?, ports = ?, status = ?
                    WHERE id = ?");
                
                $status = strtoupper($snmpDevice['status']) === 'ONLINE' ? 'online' : 'offline';
                $stmt->bind_param("sssisi", 
                    $snmpDevice['name'],
                    $snmpDevice['vendor'],
                    $snmpDevice['model'],
                    $snmpDevice['total_ports'],
                    $status,
                    $existingSwitch['id']
                );
                $stmt->execute();
                $switchId = $existingSwitch['id'];
                
            } else {
                // Insert new switch
                $stmt = $conn->prepare("INSERT INTO switches 
                    (name, brand, model, ports, ip, status) 
                    VALUES (?, ?, ?, ?, ?, 'online')");
                
                $stmt->bind_param("sssis",
                    $snmpDevice['name'],
                    $snmpDevice['vendor'],
                    $snmpDevice['model'],
                    $snmpDevice['total_ports'],
                    $snmpDevice['ip_address']
                );
                $stmt->execute();
                $switchId = $conn->insert_id;
            }
            
            // Sync port data
            $portStmt = $conn->prepare("
                SELECT * FROM port_status_data 
                WHERE device_id = ?
                AND poll_timestamp = (
                    SELECT MAX(poll_timestamp) 
                    FROM port_status_data 
                    WHERE device_id = ?
                )
            ");
            $portStmt->bind_param("ii", $snmpDevice['id'], $snmpDevice['id']);
            $portStmt->execute();
            $ports = $portStmt->get_result();
            
            while ($port = $ports->fetch_assoc()) {
                // Check if port exists
                $checkStmt = $conn->prepare("SELECT id FROM ports WHERE switch_id = ? AND port_no = ?");
                $checkStmt->bind_param("ii", $switchId, $port['port_number']);
                $checkStmt->execute();
                $existingPort = $checkStmt->get_result()->fetch_assoc();
                
                $device = $port['port_alias'] ?: $port['port_name'];
                $type = ($port['oper_status'] === 'up') ? 'DEVICE' : 'EMPTY';
                
                if ($existingPort) {
                    // Update port
                    $updateStmt = $conn->prepare("UPDATE ports SET 
                        type = ?, device = ?, ip = '', mac = ?
                        WHERE id = ?");
                    $updateStmt->bind_param("sssi", 
                        $type, $device, $port['mac_address'], $existingPort['id']
                    );
                    $updateStmt->execute();
                } else {
                    // Insert port
                    $insertStmt = $conn->prepare("INSERT INTO ports 
                        (switch_id, port_no, type, device, mac) 
                        VALUES (?, ?, ?, ?, ?)");
                    $insertStmt->bind_param("iisss",
                        $switchId, $port['port_number'], $type, $device, $port['mac_address']
                    );
                    $insertStmt->execute();
                }
            }
            
            $synced++;
        }
        
        $conn->commit();
        
        // Log activity
        $auth->logActivity(
            $user['id'],
            $user['username'],
            'snmp_sync',
            "SNMP worker verilerinden $synced cihaz senkronize edildi"
        );
        
        echo json_encode([
            'success' => true,
            'message' => "$synced cihaz başarıyla senkronize edildi",
            'synced_count' => $synced
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}
