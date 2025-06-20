<?php
// Hàm kiểm tra đăng nhập
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Hàm kiểm tra quyền admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Hàm chuyển hướng nếu chưa đăng nhập
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Hàm chuyển hướng nếu không phải admin
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

// Hàm lấy thông tin người dùng hiện tại
function getCurrentUser() {
    global $db;
    if (isLoggedIn()) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?"); #tạo câu truy vẫn an toàn
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(); 
    }
    return null;
}
?> 