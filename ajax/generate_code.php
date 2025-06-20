<?php
/**
 * AJAX endpoint để tạo mã tự động
 */
require_once '../config/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefix = $_POST['prefix'] ?? '';
    $table = $_POST['table'] ?? '';
    $column = $_POST['column'] ?? '';
    
    if (empty($prefix) || empty($table) || empty($column)) {
        echo 'Error: Missing parameters';
        exit;
    }
    
    try {
        $db = getDB();
        $code = generateCode($prefix, $table,$column,$db);
        echo $code;
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    echo 'Error: Invalid request method';
}
?>
