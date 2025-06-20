<?php
/**
 * AJAX endpoint để lấy danh sách nhà cung cấp
 */
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, code FROM suppliers WHERE status = 'active' ORDER BY name");
    $suppliers = $stmt->fetchAll();
    
    echo json_encode($suppliers);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
