<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

checkLogin();

$page_title = 'Dashboard';
$db = getDB();

// Lấy thống kê tổng quan
try {
    // Tổng số nhà cung cấp
    $stmt = $db->query("SELECT COUNT(*) as total FROM supplier WHERE status = 'active'");
    $total_suppliers = $stmt->fetch()['total'];
    
    // Tổng sản phẩm
    $stmt = $db->query("SELECT COUNT(*) as total FROM product WHERE status = 'active'");
    $total_products = $stmt->fetch()['total'];
    
    // Tổng hóa đơn tháng này
    $stmt = $db->query("SELECT COUNT(*) as total FROM bill WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
    $monthly_bills = $stmt->fetch()['total'];
    
    // Thống kê công nợ
    $stmt = $db->query("SELECT SUM(total_amount) as total_original, SUM(remaining_amount) as total_remaining FROM debts WHERE status != 'paid'");
    $debt_stats = $stmt->fetch();
    $total_original_debt = $debt_stats['total_original'] ?? 0;
    $total_remaining_debt = $debt_stats['total_remaining'] ?? 0;
    
    // Thống kê tổng doanh thu (tổng giá trị hóa đơn đã thanh toán)
    $stmt = $db->query("SELECT SUM(total_amount) as total_revenue FROM bill WHERE status = 'paid'");
    $revenue_stats = $stmt->fetch();
    $total_revenue = $revenue_stats['total_revenue'] ?? 0;
    
    // Thống kê thanh toán và công nợ theo nhà cung cấp
    $stmt = $db->query("
        SELECT 
            s.name,
            SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_paid,
            SUM(CASE WHEN d.status != 'paid' THEN d.remaining_amount ELSE 0 END) as total_outstanding_debt
        FROM supplier s
        LEFT JOIN payments p ON s.id = p.supplier_id
        LEFT JOIN debts d ON s.id = d.supplier_id
        GROUP BY s.id, s.name
        ORDER BY s.name
    ");
    $supplier_stats_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Thống kê hóa đơn theo trạng thái
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM bill GROUP BY status");
    $bill_status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $pending_bills_count = $bill_status_counts['pending'] ?? 0;
    $paid_bills_count = $bill_status_counts['paid'] ?? 0;
    $overdue_bills_count = $bill_status_counts['overdue'] ?? 0;
    
    // Hóa đơn gần đây
    $stmt = $db->query("
        SELECT b.*, s.name as supplier_name 
        FROM bill b 
        JOIN supplier s ON b.supplier_id = s.id 
        ORDER BY b.created_at DESC 
        LIMIT 5
    ");
    $recent_bills = $stmt->fetchAll();
    
    // Nhà cung cấp hàng đầu
    $stmt = $db->query("
        SELECT s.*, 
               (SELECT COUNT(*) FROM bill WHERE supplier_id = s.id) as total_bills,
               (SELECT SUM(total_amount) FROM bill WHERE supplier_id = s.id AND status = 'paid') as total_paid
        FROM supplier s 
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

<style>
    .autocomplete-suggestions {
        border: 1px solid #ced4da;
        max-height: 150px;
        overflow-y: auto;
        position: absolute;
        z-index: 1050; /* Ensure it's above other elements */
        background-color: #fff;
        width: calc(100% - 1.5rem); /* Adjust width to match input padding */
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .autocomplete-suggestions div {
        padding: 8px 12px;
        cursor: pointer;
    }
    .autocomplete-suggestions div:hover {
        background-color: #e9ecef;
    }
    
    /* Add hover effect for supplier links */
    .supplier-link:hover > div {
        background-color: #f8f9fa; /* Light grey background on hover */
        transition: background-color 0.2s ease-in-out;
    }
</style>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-tachometer-alt"></i>
            <small class="text-muted">Tổng quan hệ thống</small>
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
        <a href="suppliers.php" style="text-decoration: none; color: inherit;">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Nhà cung cấp
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_suppliers); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="products.php" style="text-decoration: none; color: inherit;">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Sản phẩm
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_products); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="invoices.php?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" style="text-decoration: none; color: inherit;">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Hóa đơn 
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">10</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="debts.php" style="text-decoration: none; color: inherit;">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Tổng công nợ
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_remaining_debt); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row mb-4">
    <!-- Tổng nợ gốc -->
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="invoices.php" style="text-decoration: none; color: inherit;">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Tổng doanh thu
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_revenue); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-coins fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Hóa đơn Chờ thanh toán -->
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="invoices.php?status=pending" style="text-decoration: none; color: inherit;">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Hóa đơn Chờ TT
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($pending_bills_count); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Hóa đơn Đã thanh toán -->
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="invoices.php?status=paid" style="text-decoration: none; color: inherit;">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Hóa đơn Đã TT
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($paid_bills_count); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Hóa đơn Quá hạn -->
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="invoices.php?status=overdue" style="text-decoration: none; color: inherit;">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Hóa đơn Quá hạn
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($overdue_bills_count); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-times fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row">
    <!-- Hóa đơn gần đây -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-file-invoice"></i> Hóa đơn gần đây
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($recent_bills)): ?>
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
                                <?php foreach ($recent_bills as $bill): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bill['bill_code']); ?></td>
                                        <td><?php echo htmlspecialchars($bill['supplier_name']); ?></td>
                                        <td><?php echo formatCurrency($bill['total_amount']); ?></td>
                                        <td>
                                            <?php
                                            $badge_class = '';
                                            $status_text = '';
                                            switch ($bill['status']) {
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
                <?php endif; ?>
            </div>
            <div class="card-footer text-center">
                <a href="invoices.php" class="btn btn-outline-primary btn-sm btn-block">
                    Xem tất cả hóa đơn
                </a>
            </div>
        </div>
    </div>
    
    <!-- Nhà cung cấp hàng đầu -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-star"></i> Nhà cung cấp hàng đầu
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($top_suppliers)): ?>
                    <p class="text-muted text-center">Chưa có dữ liệu xếp hạng</p>
                <?php else: ?>
                    <?php foreach ($top_suppliers as $supplier): ?>
                        <a href="suppliers.php?action=view&id=<?php echo $supplier['id']; ?>" class="supplier-link" style="text-decoration: none; color: inherit;">
                            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($supplier['name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-star text-warning"></i> <?php echo number_format($supplier['rating'], 1); ?>/5.0
                                        <?php if ($supplier['total_bills'] > 0): // Display if there's at least one bill ?>
                                            • <?php echo $supplier['total_bills']; ?> HĐ
                                            <?php if ($supplier['total_paid'] > 0): // Optionally show total paid if greater than 0 ?>
                                                (<?php echo formatCurrency($supplier['total_paid']); ?>)
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center">
                <a href="suppliers.php" class="btn btn-outline-primary btn-sm btn-block">
                    Xem tất cả nhà cung cấp
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-bolt"></i> Thao tác nhanh
                </h6>
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
                        <a href="products.php?action=add" class="btn btn-success btn-block">
                            <i class="fas fa-box"></i><br>
                            Thêm sản phẩm
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="invoices.php?action=add" class="btn btn-info btn-block">
                            <i class="fas fa-file-invoice"></i><br>
                            Tạo hóa đơn
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

<!-- Biểu đồ thống kê Thanh toán và Công nợ theo Nhà cung cấp -->
<div class="row mb-4" style="margin-bottom: 80px;">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-bar"></i> Thống kê Thanh toán và Công nợ theo Nhà cung cấp
                </h6>
            </div>
            <div class="card-body">
                <canvas id="supplierStatsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('supplierStatsChart').getContext('2d');

        var supplierStatsData = <?php echo json_encode($supplier_stats_data); ?>;

        // Chuẩn bị dữ liệu cho biểu đồ
        var labels = supplierStatsData.map(function(item) {
            return item.name;
        });

        var totalPaidData = supplierStatsData.map(function(item) {
            return item.total_paid;
        });

        var totalOutstandingDebtData = supplierStatsData.map(function(item) {
            return item.total_outstanding_debt;
        });

        var supplierStatsChart = new Chart(ctx, {
            type: 'bar', // Có thể đổi sang 'horizontalBar' nếu tên nhà cung cấp dài
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Tổng thanh toán',
                        backgroundColor: '#1cc88a', // Màu xanh lá cây
                        borderColor: '#1cc88a',
                        data: totalPaidData
                    },
                    {
                        label: 'Công nợ còn lại',
                        backgroundColor: '#f6c23e', // Màu vàng
                        borderColor: '#f6c23e',
                        data: totalOutstandingDebtData
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        left: 10,
                        right: 25,
                        top: 25,
                        bottom: 0
                    }
                },
                scales: {
                    xAxes: [{
                        stacked: false, // Đổi thành true nếu muốn stacked bar chart
                        gridLines: {
                            display: true,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 10 // Giới hạn số lượng label trên trục X
                        }
                    }],
                    yAxes: [{
                        stacked: false,
                        ticks: {
                            min: 0,
                            maxTicksLimit: 5,
                            padding: 10,
                            // Thêm định dạng tiền tệ nếu cần
                            callback: function(value, index, values) {
                                return formatCurrencyJS(value);
                            }
                        },
                        gridLines: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    }],
                },
                legend: {
                    display: true
                },
                tooltips: {
                    titleMarginBottom: 10,
                    titleFontColor: '#6e707e',
                    titleFontSize: 14,
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                    callbacks: {
                        label: function(tooltipItem, chart) {
                            var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                            var value = tooltipItem.yLabel;
                            return datasetLabel + ': ' + formatCurrencyJS(value);
                        }
                    }
                },
            }
        });
         // Hàm định dạng tiền tệ cơ bản cho tooltip (cần điều chỉnh nếu formatCurrency PHP phức tạp hơn)
        function formatCurrencyJS(amount) {
            return amount.toLocaleString('vi-VN', { style: 'currency', currency: 'VND' });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
