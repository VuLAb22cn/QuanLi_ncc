<?php
/**
 * Trang Dashboard chính
 */
require_once 'config/database.php';
require_once 'includes/functions.php';

$page_title = 'Dashboard';
$db = getDB();

// Lấy thống kê tổng quan
try {
    // Tổng số nhà cung cấp
    $stmt = $db->query("SELECT COUNT(*) as total FROM supplier WHERE status = 'active'");
    $total_suppliers = $stmt->fetch()['total'];
    
    // Tổng hóa đơn tháng này
    $stmt = $db->query("SELECT COUNT(*) as total FROM bill WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
    $monthly_invoices = $stmt->fetch()['total'];
    
    // Tổng công nợ
    $stmt = $db->query("SELECT SUM(remaining_amount) as total FROM debts WHERE status != 'paid'");
    $total_debt = $stmt->fetch()['total'] ?? 0;
    
    // Doanh thu tháng này
    $stmt = $db->query("SELECT SUM(amount) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) AND status = 'completed'");
    $monthly_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Hóa đơn gần đây
    $stmt = $db->query("
        SELECT b.*, s.name as supplier_name 
        FROM bill b 
        JOIN supplier s ON b.supplier_id = s.id 
        ORDER BY b.created_at DESC 
        LIMIT 5
    ");
    $recent_invoices = $stmt->fetchAll();
    
    // Nhà cung cấp hàng đầu
    $stmt = $db->query("
        SELECT s.*, sr.total_orders, sr.total_amount 
        FROM supplier s 
        LEFT JOIN supplier_ratings sr ON s.id = sr.supplier_id 
        WHERE s.status = 'active' 
        ORDER BY s.rating DESC 
        LIMIT 5
    ");
    $top_suppliers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn database: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </h1>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo number_format($total_suppliers); ?></div>
                    <div class="stats-label">Nhà cung cấp hoạt động</div>
                </div>
                <div class="ml-3">
                    <i class="fas fa-building fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card info">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo number_format($monthly_invoices); ?></div>
                    <div class="stats-label">Hóa đơn tháng này</div>
                </div>
                <div class="ml-3">
                    <i class="fas fa-file-invoice fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card warning">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo formatCurrency($total_debt); ?></div>
                    <div class="stats-label">Tổng công nợ</div>
                </div>
                <div class="ml-3">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card success">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo formatCurrency($monthly_revenue); ?></div>
                    <div class="stats-label">Doanh thu tháng</div>
                </div>
                <div class="ml-3">
                    <i class="fas fa-chart-line fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Hóa đơn gần đây -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-invoice"></i> Hóa đơn gần đây
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_invoices)): ?>
                    <p class="text-muted text-center">Chưa có hóa đơn nào</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Mã HĐ</th>
                                    <th>Nhà cung cấp</th>
                                    <th>Số tiền</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_invoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($invoice['code']); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['supplier_name']); ?></td>
                                        <td><?php echo formatCurrency($invoice['amount']); ?></td>
                                        <td>
                                            <?php
                                            $badge_class = '';
                                            $status_text = '';
                                            switch ($invoice['status']) {
                                                case 'paid':
                                                    $badge_class = 'badge-success';
                                                    $status_text = 'Đã thanh toán';
                                                    break;
                                                case 'pending':
                                                    $badge_class = 'badge-warning';
                                                    $status_text = 'Chờ thanh toán';
                                                    break;
                                                case 'overdue':
                                                    $badge_class = 'badge-danger';
                                                    $status_text = 'Quá hạn';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="invoices.php" class="btn btn-outline-primary btn-sm">
                            Xem tất cả hóa đơn
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Nhà cung cấp hàng đầu -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-star"></i> Nhà cung cấp hàng đầu
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_suppliers)): ?>
                    <p class="text-muted text-center">Chưa có dữ liệu xếp hạng</p>
                <?php else: ?>
                    <?php foreach ($top_suppliers as $supplier): ?>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($supplier['name']); ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-star text-warning"></i> <?php echo $supplier['rating']; ?>/5.0
                                    <?php if ($supplier['total_orders']): ?>
                                        • <?php echo $supplier['total_orders']; ?> đơn hàng
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php if ($supplier['total_amount']): ?>
                                <div class="text-right">
                                    <strong><?php echo formatCurrency($supplier['total_amount']); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <a href="suppliers.php" class="btn btn-outline-primary btn-sm">
                            Xem tất cả nhà cung cấp
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt"></i> Thao tác nhanh
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="suppliers.php?action=add" class="btn btn-primary btn-block">
                            <i class="fas fa-plus"></i><br>
                            Thêm nhà cung cấp
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="invoices.php?action=add" class="btn btn-info btn-block">
                            <i class="fas fa-file-plus"></i><br>
                            Tạo hóa đơn
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="payments.php?action=add" class="btn btn-success btn-block">
                            <i class="fas fa-credit-card"></i><br>
                            Thanh toán
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="search.php" class="btn btn-warning btn-block">
                            <i class="fas fa-search"></i><br>
                            Tìm kiếm
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
