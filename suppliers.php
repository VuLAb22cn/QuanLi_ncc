<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

checkLogin();

$page_title = 'Quản lý nhà cung cấp';
$db = getDB();

// Xử lý các action
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_supplier'])) {
            // Thêm nhà cung cấp mới
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            $tel = sanitize($_POST['tel']);
            $address = sanitize($_POST['address']);
            $status = sanitize($_POST['status']);
            
            // Validate
            if (empty($name)) {
                throw new Exception('Tên nhà cung cấp không được để trống');
            }
            
            if (!empty($email) && !validateEmail($email)) {
                throw new Exception('Email không hợp lệ');
            }
            
            if (!empty($tel) && !validatePhone($tel)) {
                throw new Exception('Số điện thoại không hợp lệ');
            }
            
            $stmt = $db->prepare("INSERT INTO supplier (name, email, tel, address, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $tel, $address, $status]);
            
            setFlashMessage('success', 'Thêm nhà cung cấp thành công!');
            header('Location: suppliers.php');
            exit;
            
        } elseif (isset($_POST['update_supplier'])) {
            // Cập nhật nhà cung cấp
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            $tel = sanitize($_POST['tel']);
            $address = sanitize($_POST['address']);
            $status = sanitize($_POST['status']);
            $rating = (float)$_POST['rating'];
            
            // Validate
            if (empty($name)) {
                throw new Exception('Tên nhà cung cấp không được để trống');
            }
            
            if (!empty($email) && !validateEmail($email)) {
                throw new Exception('Email không hợp lệ');
            }
            
            if (!empty($tel) && !validatePhone($tel)) {
                throw new Exception('Số điện thoại không hợp lệ');
            }
            
            $stmt = $db->prepare("UPDATE supplier SET name = ?, email = ?, tel = ?, address = ?, status = ?, rating = ? WHERE id = ?");
            $stmt->execute([$name, $email, $tel, $address, $status, $rating, $id]);
            
            setFlashMessage('success', 'Cập nhật nhà cung cấp thành công!');
            header('Location: suppliers.php');
            exit;
        }
        
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
}

// Xử lý xóa
if ($action === 'delete' && $id) {
    try {
        // Kiểm tra xem có dữ liệu liên quan không
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill WHERE supplier_id = ?");
        $stmt->execute([$id]);
        $bill_count = $stmt->fetch()['count'];
        
        if ($bill_count > 0) {
            setFlashMessage('error', 'Không thể xóa nhà cung cấp này vì có ' . $bill_count . ' hóa đơn liên quan!');
        } else {
            $stmt = $db->prepare("DELETE FROM supplier WHERE id = ?");
            $stmt->execute([$id]);
            setFlashMessage('success', 'Xóa nhà cung cấp thành công!');
        }
        
        header('Location: suppliers.php');
        exit;
    } catch (PDOException $e) {
        setFlashMessage('error', 'Không thể xóa nhà cung cấp này!');
        header('Location: suppliers.php');
        exit;
    }
}

// Lấy dữ liệu cho form edit
$supplier = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM supplier WHERE id = ?");
    $stmt->execute([$id]);
    $supplier = $stmt->fetch();
    
    if (!$supplier) {
        setFlashMessage('error', 'Không tìm thấy nhà cung cấp!');
        header('Location: suppliers.php');
        exit;
    }
}

// Lấy danh sách nhà cung cấp với phân trang và tìm kiếm
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $records_per_page = 10;
    
    // Xây dựng query
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ? OR tel LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Đếm tổng số bản ghi
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM supplier $where_clause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Tính phân trang
    $pagination = paginate($total_records, $records_per_page, $page);
    
    // Lấy dữ liệu
    $stmt = $db->prepare("
        SELECT s.*, 
               (SELECT COUNT(*) FROM bill WHERE supplier_id = s.id) as total_bills,
               (SELECT SUM(total_amount) FROM bill WHERE supplier_id = s.id AND status = 'paid') as total_paid
        FROM supplier s 
        $where_clause 
        ORDER BY s.created_at DESC 
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll();
}

// Lấy top 3 nhà cung cấp hàng đầu theo tổng doanh thu
if ($action === 'ranking') {
    $page_title = 'Xếp hạng nhà cung cấp';
    $ranked_suppliers = [];
    try {
        $stmt = $db->query("
            SELECT s.id, s.name, 
                   SUM(CASE WHEN b.status = 'paid' THEN b.total_amount ELSE 0 END) as total_paid_amount
            FROM supplier s
            LEFT JOIN bill b ON s.id = b.supplier_id
            GROUP BY s.id, s.name
            ORDER BY total_paid_amount DESC
            LIMIT 3
        ");
        $ranked_suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        setFlashMessage('error', 'Lỗi truy vấn xếp hạng nhà cung cấp: ' . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <?php echo createBreadcrumb([
            ['title' => 'Dashboard', 'url' => 'dashboard.php'],
            ['title' => 'Nhà cung cấp']
        ]); ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- Danh sách nhà cung cấp -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-building"></i> Danh sách nhà cung cấp
                                <span class="badge badge-info ml-2"><?php echo $total_records ?? 0; ?></span>
                            </h6>
                        </div>
                        <div class="col-auto">
                            <a href="suppliers.php?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Thêm nhà cung cấp
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Tìm kiếm và lọc -->
                    <form method="GET" class="mb-4">
                        <input type="hidden" name="action" value="list">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Tìm theo tên, email, SĐT..." 
                                           value="<?php echo htmlspecialchars($search ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <select name="status" class="form-control">
                                        <option value="">Tất cả trạng thái</option>
                                        <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Hoạt động</option>
                                        <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Không hoạt động</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search"></i> Tìm
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Bảng dữ liệu -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Tên nhà cung cấp</th>
                                    <th>Email</th>
                                    <th>Điện thoại</th>
                                    <th>Trạng thái</th>
                                    <th>Đánh giá</th>
                                    <th>Tổng HĐ</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($suppliers)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            Không có dữ liệu
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <tr>
                                            <td><?php echo $supplier['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($supplier['name']); ?></strong></td>
                                            <td>
                                                <?php if (!empty($supplier['email'])): ?>
                                                    <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>">
                                                        <?php echo htmlspecialchars($supplier['email']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa có</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($supplier['tel'])): ?>
                                                    <a href="tel:<?php echo htmlspecialchars($supplier['tel']); ?>">
                                                        <?php echo htmlspecialchars($supplier['tel']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa có</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($supplier['status'] === 'active'): ?>
                                                    <span class="badge badge-success">Hoạt động</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Không hoạt động</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-star text-warning"></i> <?php echo number_format($supplier['rating'], 1); ?>/5.0
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $supplier['total_bills']; ?> HĐ</span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="suppliers.php?action=view&id=<?php echo $supplier['id']; ?>" 
                                                       class="btn btn-info" title="Xem chi tiết">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="suppliers.php?action=edit&id=<?php echo $supplier['id']; ?>" 
                                                       class="btn btn-warning" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="suppliers.php?action=delete&id=<?php echo $supplier['id']; ?>" 
                                                       class="btn btn-danger" title="Xóa"
                                                       onclick="return confirm('Bạn có chắc chắn muốn xóa nhà cung cấp này?')">
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
                    <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($pagination['current_page'] > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?action=list&page=<?php echo $pagination['current_page'] - 1; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                    <li class="page-item <?php echo ($i === $pagination['current_page']) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $i; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?action=list&page=<?php echo $pagination['current_page'] + 1; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Form thêm/sửa nhà cung cấp -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i>
                        <?php echo $action === 'add' ? 'Thêm nhà cung cấp mới' : 'Sửa thông tin nhà cung cấp'; ?>
                    </h6>
                </div>
                
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Tên nhà cung cấp <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($supplier['name'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">
                                        Vui lòng nhập tên nhà cung cấp
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>">
                                    <div class="invalid-feedback">
                                        Vui lòng nhập email hợp lệ
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tel">Số điện thoại</label>
                                    <input type="text" class="form-control" id="tel" name="tel" 
                                           value="<?php echo htmlspecialchars($supplier['tel'] ?? ''); ?>"
                                           pattern="[0-9]{10,11}">
                                    <div class="invalid-feedback">
                                        Số điện thoại phải có 10-11 chữ số
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Trạng thái <span class="text-danger">*</span></label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="active" <?php echo ($supplier['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                        <option value="inactive" <?php echo ($supplier['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($action === 'edit'): ?>
                            <div class="form-group">
                                <label for="rating">Đánh giá (1-5)</label>
                                <input type="number" class="form-control" id="rating" name="rating" 
                                       min="1" max="5" step="0.1"
                                       value="<?php echo $supplier['rating'] ?? 0; ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="address">Địa chỉ</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" name="<?php echo $action === 'add' ? 'add_supplier' : 'update_supplier'; ?>" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Thêm nhà cung cấp' : 'Cập nhật'; ?>
                            </button>
                            <a href="suppliers.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Hủy
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'view' && $id): ?>
    <!-- Xem chi tiết nhà cung cấp -->
    <?php
    $stmt = $db->prepare("
        SELECT s.*, 
               (SELECT COUNT(*) FROM bill WHERE supplier_id = s.id) as total_bills,
               (SELECT SUM(total_amount) FROM bill WHERE supplier_id = s.id AND status = 'paid') as total_paid,
               (SELECT SUM(remaining_amount) FROM debts WHERE supplier_id = s.id AND status != 'paid') as total_debt
        FROM supplier s 
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $supplier_detail = $stmt->fetch();
    
    if (!$supplier_detail) {
        setFlashMessage('error', 'Không tìm thấy nhà cung cấp!');
        header('Location: suppliers.php');
        exit;
    }
    ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-building"></i> Thông tin nhà cung cấp
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">ID:</th>
                            <td><strong><?php echo $supplier_detail['id']; ?></strong></td>
                        </tr>
                        <tr>
                            <th>Tên nhà cung cấp:</th>
                            <td><?php echo htmlspecialchars($supplier_detail['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td>
                                <?php if (!empty($supplier_detail['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($supplier_detail['email']); ?>">
                                        <?php echo htmlspecialchars($supplier_detail['email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Chưa có</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Số điện thoại:</th>
                            <td>
                                <?php if (!empty($supplier_detail['tel'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($supplier_detail['tel']); ?>">
                                        <?php echo htmlspecialchars($supplier_detail['tel']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Chưa có</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Địa chỉ:</th>
                            <td>
                                <?php if (!empty($supplier_detail['address'])): ?>
                                    <?php echo nl2br(htmlspecialchars($supplier_detail['address'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">Chưa có</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Trạng thái:</th>
                            <td>
                                <?php if ($supplier_detail['status'] === 'active'): ?>
                                    <span class="badge badge-success">Hoạt động</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Không hoạt động</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Đánh giá:</th>
                            <td>
                                <i class="fas fa-star text-warning"></i> <?php echo number_format($supplier_detail['rating'], 1); ?>/5.0
                            </td>
                        </tr>
                        <tr>
                            <th>Ngày tạo:</th>
                            <td><?php echo formatDateTime($supplier_detail['created_at']); ?></td>
                        </tr>
                        <tr>
                            <th>Cập nhật lần cuối:</th>
                            <td><?php echo formatDateTime($supplier_detail['updated_at']); ?></td>
                        </tr>
                    </table>
                    
                    <div class="mt-3">
                        <a href="suppliers.php?action=edit&id=<?php echo $supplier_detail['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Sửa thông tin
                        </a>
                        <a href="suppliers.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thống kê</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-12 mb-3">
                            <h4 class="text-primary"><?php echo $supplier_detail['total_bills']; ?></h4>
                            <small class="text-muted">Tổng hóa đơn</small>
                        </div>
                        <div class="col-12 mb-3">
                            <h4 class="text-success"><?php echo formatCurrency($supplier_detail['total_paid'] ?? 0); ?></h4>
                            <small class="text-muted">Đã thanh toán</small>
                        </div>
                        <div class="col-12 mb-3">
                            <h4 class="text-danger"><?php echo formatCurrency($supplier_detail['total_debt'] ?? 0); ?></h4>
                            <small class="text-muted">Công nợ còn lại</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow mt-3">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thao tác nhanh</h6>
                </div>
                <div class="card-body">
                    <a href="invoices.php?supplier_id=<?php echo $supplier_detail['id']; ?>" class="btn btn-info btn-block btn-sm mb-2">
                        <i class="fas fa-file-invoice"></i> Xem hóa đơn
                    </a>
                    <a href="payments.php?supplier_id=<?php echo $supplier_detail['id']; ?>" class="btn btn-success btn-block btn-sm mb-2">
                        <i class="fas fa-credit-card"></i> Lịch sử thanh toán
                    </a>
                    <a href="debts.php?supplier_id=<?php echo $supplier_detail['id']; ?>" class="btn btn-warning btn-block btn-sm">
                        <i class="fas fa-exclamation-triangle"></i> Công nợ
                    </a>
                </div>
            </div>
        </div>
    </div>
    
<?php elseif ($action === 'ranking'): ?>
    <!-- Xếp hạng nhà cung cấp -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-award"></i> Xếp hạng Top 3 Nhà cung cấp hàng đầu
                    </h6>
                </div>
                
                <div class="card-body">
                    <?php echo getFlashMessage(); // Hiển thị flash message nếu có ?>

                    <?php if (empty($ranked_suppliers)): ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-info-circle"></i> Chưa có dữ liệu xếp hạng.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Tên nhà cung cấp</th>
                                        <th>Tổng doanh thu đã thanh toán</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; foreach ($ranked_suppliers as $supplier): ?>
                                        <tr>
                                            <td><?php echo $rank++; ?></td>
                                            <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                            <td><?php echo formatCurrency($supplier['total_paid_amount']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; // End list and ranking display ?>

<div style="height: 80px;"></div>

<?php include 'includes/footer.php'; ?>
