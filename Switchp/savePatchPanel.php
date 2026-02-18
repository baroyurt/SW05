<?php
include "db.php";

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    die(json_encode(["success" => false, "error" => "Geçersiz veri"]));
}

try {
    $rackId = intval($data['rackId']);
    $panelLetter = strtoupper(trim($data['panelLetter']));
    $totalPorts = intval($data['totalPorts']);
    $positionInRack = intval($data['positionInRack']);
    $description = isset($data['description']) ? trim($data['description']) : '';
    
    // Aynı rack'ta aynı harfte panel var mı kontrol et
    $checkStmt = $conn->prepare("
        SELECT id FROM patch_panels 
        WHERE rack_id = ? AND panel_letter = ?
    ");
    $checkStmt->bind_param("is", $rackId, $panelLetter);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        throw new Exception("Bu rack'te $panelLetter paneli zaten mevcut");
    }
    
    // Aynı pozisyonda başka bir şey var mı kontrol et
    $checkPosStmt = $conn->prepare("
        SELECT id FROM patch_panels 
        WHERE rack_id = ? AND position_in_rack = ?
        UNION
        SELECT id FROM switches 
        WHERE rack_id = ? AND position_in_rack = ?
    ");
    $checkPosStmt->bind_param("iiii", $rackId, $positionInRack, $rackId, $positionInRack);
    $checkPosStmt->execute();
    $checkPosResult = $checkPosStmt->get_result();
    
    if ($checkPosResult->num_rows > 0) {
        throw new Exception("Bu slot zaten dolu");
    }
    
    // Panel ekle
    $insertStmt = $conn->prepare("
        INSERT INTO patch_panels (rack_id, panel_letter, total_ports, description, position_in_rack)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertStmt->bind_param("isisi", $rackId, $panelLetter, $totalPorts, $description, $positionInRack);
    
    if ($insertStmt->execute()) {
        $panelId = $conn->insert_id;
        
        // Panel portlarını oluştur
        $portStmt = $conn->prepare("
            INSERT INTO patch_ports (panel_id, port_number, status)
            VALUES (?, ?, 'inactive')
        ");
        
        for ($portNo = 1; $portNo <= $totalPorts; $portNo++) {
            $portStmt->bind_param("ii", $panelId, $portNo);
            $portStmt->execute();
        }
        $portStmt->close();
        
        echo json_encode([
            "success" => true,
            "panelId" => $panelId,
            "message" => "Patch panel ve portları oluşturuldu"
        ]);
    } else {
        throw new Exception("Panel ekleme hatası: " . $insertStmt->error);
    }
    
    $insertStmt->close();
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>