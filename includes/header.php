<?php
require_once 'includes/session.php';
$flash_message = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Quản lý nhà cung cấp</title>
    
    <!-- Bootstrap 4 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-building"></i> Quản Lí NCC
            </a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-home"></i> Tổng quan
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                            <i class="fas fa-building"></i> Nhà cung cấp
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="suppliers.php">Danh sách</a>
                            <a class="dropdown-item" href="suppliers.php?action=add">Thêm mới</a>
                            <a class="dropdown-item" href="suppliers.php?action=ranking">Xếp hạng</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                            <i class="fas fa-box"></i> Sản phẩm
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="products.php">Danh sách</a>
                            <a class="dropdown-item" href="products.php?action=add">Thêm mới</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                            <i class="fas fa-file-invoice"></i> Hóa đơn
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="invoices.php">Danh sách</a>
                            <a class="dropdown-item" href="invoices.php?action=add">Tạo mới</a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cashbook.php">
                            <i class="fas fa-book"></i> Sổ quỹ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="debts.php">
                            <i class="fas fa-exclamation-triangle"></i> Công nợ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card"></i> Thanh toán
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contracts.php">
                            <i class="fas fa-file-contract"></i> Hợp đồng
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                            <i class="fas fa-user"></i> Admin
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php">Hồ sơ</a>
                            <a class="dropdown-item" href="settings.php">Cài đặt</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">Đăng xuất</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        
        <!-- Flash Messages -->
        <?php if ($flash_message): ?>
            <div class="alert alert-<?php echo $flash_message['type'] === 'success' ? 'success' : ($flash_message['type'] === 'error' ? 'danger' : $flash_message['type']); ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $flash_message['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($flash_message['message']); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
