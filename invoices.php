<?php
/**
 * Quản lý hóa đơn
 */
require_once 'config/database.php';
require_once 'includes/functions.php';

$page_title = 'Quản lý hóa đơn';
$db = getDB();

// Xử lý các action
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_invoice'])) {
            // Thêm hóa đơn mới
            $code = generateCode('BILL', 'bill', 'bill_code', $db);
            $supplier_id = (int)$_POST['supplier_id'];
            $amount = (float)str_replace([',', '.'], '', $_POST['amount']);
            $description = sanitize($_POST['description']);
            $date = $_POST['invoice_date'];
            $due_date = $_POST['due_date'];
            
            // Validate
            if (empty($supplier_id)) {
                throw new Exception('Vui lòng chọn nhà cung cấp');
            }
            
            if ($amount <= 0) {
                throw new Exception('Số tiền phải lớn hơn 0');
            }
            
            if (empty($date) || empty($due_date)) {
                throw new Exception('Vui lòng nhập đầy đủ ngày tháng');
            }
            
            if (strtotime($due_date) < strtotime($date)) {
                throw new Exception('Ngày đến hạn phải sau ngày tạo hóa đơn');
            }
            
            $stmt = $db->prepare("INSERT INTO bill (bill_code, supplier_id, total_amount, description, date, due_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $supplier_id, $amount, $description, $date, $due_date]);
            
            // Tạo hoặc cập nhật công nợ
            $debt_stmt = $db->prepare("
                INSERT INTO debts (debt_code, supplier_id, total_amount, remaining_amount, due_date) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                total_amount = total_amount + VALUES(total_amount),
                remaining_amount = remaining_amount + VALUES(remaining_amount)
            ");
            $debt_code = generateCode('DEBT', 'debts', 'debt_code', $db);
            $debt_stmt->execute([$debt_code, $supplier_id, $amount, $amount, $due_date]);
            
            $message = 'Thêm hóa đơn thành công!';
            $action = 'list';
            
        } elseif (isset($_POST['update_invoice'])) {
            // Cập nhật hóa đơn
            $supplier_id = (int)$_POST['supplier_id'];
            $amount = (float)str_replace([',', '.'], '', $_POST['amount']);
            $description = sanitize($_POST['description']);
            $date = $_POST['invoice_date'];
            $due_date = $_POST['due_date'];
            $status = sanitize($_POST['status']);
            
            // Validate
            if (empty($supplier_id)) {
                throw new Exception('Vui lòng chọn nhà cung cấp');
            }
            
            if ($amount <= 0) {
                throw new Exception('Số tiền phải lớn hơn 0');
            }
            
            $stmt = $db->prepare("UPDATE bill SET supplier_id = ?, total_amount = ?, description = ?, date = ?, due_date = ?, status = ? WHERE id = ?");
            $stmt->execute([$supplier_id, $amount, $description, $date, $due_date, $status, $id]);
            
            $message = 'Cập nhật hóa đơn thành công!';
            $action = 'list';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Xử lý xóa
if ($action === 'delete' && $id) {
    try {
        $stmt = $db->prepare("DELETE FROM bill WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Xóa hóa đơn thành công!';
        $action = 'list';
    } catch (PDOException $e) {
        $error = 'Không thể xóa hóa đơn này!';
        $action = 'list';
    }
}

// Lấy dữ liệu cho form edit
$invoice = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM bill WHERE id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        $error = 'Không tìm thấy hóa đơn!';
        $action = 'list';
    }
}

// Lấy danh sách nhà cung cấp cho dropdown
$suppliers_stmt = $db->query("SELECT id, name FROM supplier WHERE status = 'active' ORDER BY name");
$suppliers = $suppliers_stmt->fetchAll();

// Lấy danh sách hóa đơn với phân trang và tìm kiếm
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $supplier_filter = $_GET['supplier_id'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $records_per_page = 10;
    
    // Xây dựng query
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(b.bill_code LIKE ? OR b.description LIKE ? OR s.name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "b.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($supplier_filter)) {
        $where_conditions[] = "b.supplier_id = ?";
        $params[] = $supplier_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Đếm tổng số bản ghi
    $count_stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM bill b 
        JOIN supplier s ON b.supplier_id = s.id 
        $where_clause
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Tính phân trang
    $pagination = paginate($total_records, $records_per_page, $page);
    
    // Lấy dữ liệu
    $stmt = $db->prepare("
        SELECT b.*, s.name as supplier_name
        FROM bill b 
        JOIN supplier s ON b.supplier_id = s.id 
        $where_clause 
        ORDER BY b.created_at DESC 
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <?php echo createBreadcrumb([
            ['title' => 'Dashboard', 'url' => 'index.php'],
            ['title' => 'Hóa đơn']
        ]); ?>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Danh sách hóa đơn -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-file-invoice"></i> Danh sách hóa đơn
                            </h5>
                        </div>
                        <div class="col-auto">
                            <a href="invoices.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Tạo hóa đơn
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Tìm kiếm và lọc -->
                    <form method="GET" class="search-box">
                        <input type="hidden" name="action" value="list">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Tìm kiếm</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Tìm theo mã, mô tả, nhà cung cấp..." 
                                           value="<?php echo htmlspecialchars($search ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Nhà cung cấp</label>
                                    <select name="supplier_id" class="form-control">
                                        <option value="">Tất cả</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier['id']; ?>" 
                                                    <?php echo ($supplier_filter == $supplier['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Trạng thái</label>
                                    <select name="status" class="form-control">
                                        <option value="">Tất cả</option>
                                        <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Chờ thanh toán</option>
                                        <option value="paid" <?php echo ($status_filter === 'paid') ? 'selected' : ''; ?>>Đã thanh toán</option>
                                        <option value="overdue" <?php echo ($status_filter === 'overdue') ? 'selected' : ''; ?>>Quá hạn</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-search"></i> Tìm
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Bảng dữ liệu -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="dataTable">
                            <thead>
                                <tr>
                                    <th>Mã HĐ</th>
                                    <th>Nhà cung cấp</th>
                                    <th>Số tiền</th>
                                    <th>Ngày tạo</th>
                                    <th>Ngày đến hạn</th>
                                    <th>Trạng thái</th>
                                    <th class="no-print">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($invoices)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Không có dữ liệu</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($invoice['bill_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($invoice['supplier_name']); ?></td>
                                            <td><strong><?php echo formatCurrency($invoice['total_amount']); ?></strong></td>
                                            <td><?php echo formatDate($invoice['date']); ?></td>
                                            <td>
                                                <?php echo formatDate($invoice['due_date']); ?>
                                                <?php 
                                                $days_overdue = getDaysOverdue($invoice['due_date']);
                                                if ($days_overdue > 0 && $invoice['status'] !== 'paid'): 
                                                ?>
                                                    <br><small class="text-danger">Quá hạn <?php echo $days_overdue; ?> ngày</small>
                                                <?php endif; ?>
                                            </td>
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
                                            <td class="no-print">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="invoices.php?action=view&id=<?php echo $invoice['id']; ?>" 
                                                       class="btn btn-info" title="Xem chi tiết">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="invoices.php?action=edit&id=<?php echo $invoice['id']; ?>" 
                                                       class="btn btn-warning" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="invoices.php?action=delete&id=<?php echo $invoice['id']; ?>" 
                                                       class="btn btn-danger btn-delete" title="Xóa">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Phân trang -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <nav>
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                    <li class="page-item <?php echo ($i === $pagination['current_page']) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $i; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>&supplier_id=<?php echo urlencode($supplier_filter ?? ''); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Form thêm/sửa hóa đơn -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i>
                        <?php echo $action === 'add' ? 'Tạo hóa đơn mới' : 'Sửa thông tin hóa đơn'; ?>
                    </h5>
                </div>
                
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="supplier_id">Nhà cung cấp <span class="text-danger">*</span></label>
                                    <select class="form-control" id="supplier_id" name="supplier_id" required>
                                        <option value="">Chọn nhà cung cấp</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier['id']; ?>" 
                                                    <?php echo ($invoice['supplier_id'] ?? '') == $supplier['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Vui lòng chọn nhà cung cấp
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="amount">Số tiền <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control currency-input" id="amount" name="amount" 
                                           value="<?php echo $invoice ? number_format($invoice['total_amount'], 0, ',', '.') : ''; ?>" required>
                                    <div class="invalid-feedback">
                                        Vui lòng nhập số tiền hợp lệ
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date">Ngày tạo hóa đơn <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date" name="invoice_date" 
                                           value="<?php echo $invoice['date'] ?? date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">
                                        Vui lòng chọn ngày tạo hóa đơn
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="due_date">Ngày đến hạn <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="due_date" name="due_date" 
                                           value="<?php echo $invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days')); ?>" required>
                                    <div class="invalid-feedback">
                                        Vui lòng chọn ngày đến hạn
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($action === 'edit'): ?>
                            <div class="form-group">
                                <label for="status">Trạng thái</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="pending" <?php echo ($invoice['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Chờ thanh toán</option>
                                    <option value="paid" <?php echo ($invoice['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Đã thanh toán</option>
                                    <option value="overdue" <?php echo ($invoice['status'] ?? '') === 'overdue' ? 'selected' : ''; ?>>Quá hạn</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="description">Mô tả</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($invoice['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" name="<?php echo $action === 'add' ? 'add_invoice' : 'update_invoice'; ?>" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Tạo hóa đơn' : 'Cập nhật'; ?>
                            </button>
                            <a href="invoices.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Hủy
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($action === 'view' && $id): ?>
    <!-- Xem chi tiết hóa đơn -->
    <?php
    $stmt = $db->prepare("
        SELECT b.*, s.name as supplier_name
        FROM bill b 
        JOIN supplier s ON b.supplier_id = s.id
        WHERE b.id = ?
    ");
    $stmt->execute([$id]);
    $invoice_detail = $stmt->fetch();
    
    if (!$invoice_detail) {
        // Invoice not found, redirect with error message
        $_SESSION['error_message'] = 'Không tìm thấy hóa đơn!';
        header('Location: invoices.php');
        exit();
    }
    ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-file-invoice"></i> Chi tiết hóa đơn: <?php echo htmlspecialchars($invoice_detail['bill_code']); ?>
                    </h6>
                </div>
                
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th>Mã HĐ:</th>
                            <td><?php echo htmlspecialchars($invoice_detail['bill_code']); ?></td>
                        </tr>
                        <tr>
                            <th>Nhà cung cấp:</th>
                            <td><?php echo htmlspecialchars($invoice_detail['supplier_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Số tiền:</th>
                            <td><?php echo formatCurrency($invoice_detail['total_amount']); ?></td>
                        </tr>
                        <tr>
                            <th>Ngày tạo:</th>
                            <td><?php echo formatDate($invoice_detail['date']); ?></td>
                        </tr>
                        <tr>
                            <th>Ngày đến hạn:</th>
                            <td>
                                <?php echo formatDate($invoice_detail['due_date']); ?>
                                <?php 
                                $days_overdue = getDaysOverdue($invoice_detail['due_date']);
                                if ($days_overdue > 0 && $invoice_detail['status'] !== 'paid'): 
                                ?>
                                    <br><small class="text-danger">Quá hạn <?php echo $days_overdue; ?> ngày</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                         <tr>
                            <th>Trạng thái:</th>
                            <td>
                                <?php
                                $badge_class = '';
                                $status_text = '';
                                switch ($invoice_detail['status']) {
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
                         <tr>
                            <th>Mô tả:</th>
                            <td><?php echo nl2br(htmlspecialchars($invoice_detail['description'])); ?></td>
                        </tr>
                    </table>

                    <div class="mt-3">
                        <a href="invoices.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại danh sách
                        </a>
                         <a href="invoices.php?action=edit&id=<?php echo $invoice_detail['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Sửa hóa đơn
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
