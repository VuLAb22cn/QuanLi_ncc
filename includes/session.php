<?php
/**
 * Quản lý session và bảo mật
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Kiểm tra đăng nhập
 */
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Kiểm tra quyền admin
 */
function checkAdmin() {
    checkLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Thiết lập thông báo flash
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Lấy và xóa thông báo flash
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Tạo CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));#tạo số ngẫu nhiên an toàn
    }
    return $_SESSION['csrf_token'];
}

/**
 * Kiểm tra CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
