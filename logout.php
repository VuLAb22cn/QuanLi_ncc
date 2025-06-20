<?php
require_once 'includes/session.php';

// Hủy bỏ tất cả các biến session
$_SESSION = array();

// Xóa cookie session. Điều này sẽ hủy phiên làm việc
// và không yêu cầu unset $_SESSION['loggedin']
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Cuối cùng, hủy session
session_destroy();

// Chuyển hướng về trang đăng nhập
header('Location: login.php');
exit;
?> 