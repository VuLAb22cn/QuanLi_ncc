<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

checkLogin();

$page_title = 'Công nợ';
$db = getDB();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$debt_info = null; // For edit action
$errors = []; // For form validation errors

// Handle POST requests for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'edit' && $id) {
        $total_amount = filter_var($_POST['total_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $paid_amount = filter_var($_POST['paid_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $due_date = sanitize($_POST['due_date'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if ($total_amount === false || $total_amount < 0) {
            $errors[] = 'Tổng tiền không hợp lệ.';
        }
         if ($paid_amount === false || $paid_amount < 0) {
            $errors[] = 'Số tiền đã thanh toán không hợp lệ.';
        }
        if ($paid_amount > $total_amount) {
             $errors[] = 'Số tiền đã thanh toán không thể lớn hơn tổng tiền.';
        }
        if (empty($due_date)) {
            $errors[] = 'Ngày đến hạn không được để trống.';
        }
        
        $remaining_amount = $total_amount - $paid_amount;
        
        // Get status from form
        $status = sanitize($_POST['status'] ?? '');
        
        // Logic cập nhật số tiền và trạng thái
        if ($status === 'paid') {
            // Nếu trạng thái được đặt là 'paid', đặt paid_amount = total_amount và remaining_amount = 0
            $paid_amount = $total_amount; 
            $remaining_amount = 0;
        } elseif ($status === 'current') {
            // Nếu trạng thái được đặt là 'current', đặt paid_amount = 0 và remaining_amount = total_amount
            $paid_amount = 0;
            $remaining_amount = $total_amount;
        } elseif ($status === 'overdue') {
            // Nếu trạng thái được đặt là 'overdue', tính lại remaining_amount dựa trên input
            $remaining_amount = $total_amount - $paid_amount;
            // Không cần thay đổi $status ở đây vì đã lấy từ form
        } else {
            // Trường hợp status không được cung cấp từ form (fallback)
            // Tính toán remaining_amount dựa trên input
            $remaining_amount = $total_amount - $paid_amount;
            // Tính toán trạng thái tự động
            if ($remaining_amount <= 0) {
                $status = 'paid';
            } elseif (strtotime($due_date) < time()) {
                 $status = 'overdue';
            } else {
                 $status = 'current';
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("UPDATE debts SET total_amount = ?, paid_amount = ?, remaining_amount = ?, due_date = ?, status = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$total_amount, $paid_amount, $remaining_amount, $due_date, $status, $description, $id]);

                setFlashMessage('success', 'Cập nhật công nợ thành công!');
                header('Location: debts.php');
                exit();
            } catch (PDOException $e) {
                $errors[] = 'Lỗi cập nhật công nợ: ' . $e->getMessage();
            }
        }
         // If there are errors, fetch the debt info again to display in the form
        if (!empty($errors) || !empty($_POST)) {
             try {
                $stmt = $db->prepare("SELECT d.*, s.name as supplier_name FROM debts d JOIN supplier s ON d.supplier_id = s.id WHERE d.id = ?");
                $stmt->execute([$id]);
                $debt_info = $stmt->fetch();
            } catch (PDOException $e) {
                 setFlashMessage('error', 'Lỗi truy vấn thông tin công nợ: ' . $e->getMessage());
                 header('Location: debts.php');
                 exit();
            }
        }
    }
}

// Handle GET actions
if ($action === 'delete' && $id) {
    try {
        $stmt = $db->prepare("DELETE FROM debts WHERE id = ?");
        $stmt->execute([$id]);

        setFlashMessage('success', 'Xóa công nợ thành công!');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Lỗi xóa công nợ: ' . $e->getMessage());
    }
    header('Location: debts.php');
    exit();
}

// Fetch debt info for edit action (if not already fetched due to POST errors)
if ($action === 'edit' && $id && !$debt_info) {
     try {
        $stmt = $db->prepare("SELECT d.*, s.name as supplier_name FROM debts d JOIN supplier s ON d.supplier_id = s.id WHERE d.id = ?");
        $stmt->execute([$id]);
        $debt_info = $stmt->fetch();
        if (!$debt_info) {
            setFlashMessage('error', 'Không tìm thấy công nợ.');
            header('Location: debts.php');
            exit();
        }
    } catch (PDOException $e) {
         setFlashMessage('error', 'Lỗi truy vấn thông tin công nợ: ' . $e->getMessage());
         header('Location: debts.php');
         exit();
    }
}

$debts = [];
// Fetch list of debts for 'list' action or after delete/edit POST
if ($action === 'list' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->query("
            SELECT d.*, s.name as supplier_name 
            FROM debts d 
            JOIN supplier s ON d.supplier_id = s.id
            WHERE d.status != 'paid'
            ORDER BY d.due_date ASC, d.created_at DESC
        ");
        $debts = $stmt->fetchAll();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Lỗi truy vấn dữ liệu công nợ: ' . $e->getMessage());
    }
}

include 'includes/header.php';

?>

<div class="row">
    <div class="col-12">
        <?php echo createBreadcrumb([
            ['title' => 'Dashboard', 'url' => 'dashboard.php'],
            ['title' => 'Công nợ']
        ]); ?>
    </div>
</div>

<?php echo getFlashMessage(); // Hiển thị flash message nếu có ?>

<?php if ($action === 'list'): ?>

<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-exclamation-triangle"></i> Danh sách công nợ
                        </h6>
                    </div>
                    <?php /* Có thể thêm nút thêm công nợ thủ công nếu cần */ ?>
                </div>
            </div>
            
            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Mã nợ</th>
                                <th>Nhà cung cấp</th>
                                <th>Tổng tiền</th>
                                <th>Đã thanh toán</th>
                                <th>Còn lại</th>
                                <th>Ngày đến hạn</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($debts)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        Không có dữ liệu
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($debts as $debt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($debt['debt_code']); ?></td>
                                        <td><?php echo htmlspecialchars($debt['supplier_name']); ?></td>
                                        <td><?php echo formatCurrency($debt['total_amount']); ?></td>
                                        <td><?php echo formatCurrency($debt['paid_amount']); ?></td>
                                        <td><strong class="text-danger"><?php echo formatCurrency($debt['remaining_amount']); ?></strong></td>
                                        <td><?php echo formatDate($debt['due_date']); ?></td>
                                        <td>
                                            <?php 
                                                $status_class = '';
                                                $status_text = '';
                                                switch ($debt['status']) {
                                                    case 'current': 
                                                        $status_class = 'badge-warning';
                                                        $status_text = 'Chờ thanh toán';
                                                        break;
                                                    case 'overdue': 
                                                        $status_class = 'badge-danger';
                                                        $status_text = 'Quá hạn';
                                                        break;
                                                    case 'paid': 
                                                        $status_class = 'badge-success';
                                                        $status_text = 'Đã thanh toán';
                                                        break;
                                                }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_text); ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="debts.php?action=edit&id=<?php echo $debt['id']; ?>" 
                                                   class="btn btn-warning" title="Chỉnh sửa">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="debts.php?action=delete&id=<?php echo $debt['id']; ?>" 
                                                   class="btn btn-danger" title="Xóa"
                                                   onclick="return confirm('Bạn có chắc chắn muốn xóa công nợ này?')">
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
                
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'edit' && $debt_info): ?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card shadow">
            <div class="card-header py-3">
                 <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-edit"></i> Chỉnh sửa công nợ: <?php echo htmlspecialchars($debt_info['debt_code']); ?>
                </h6>
            </div>
            <div class="card-body">
                 <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="POST" action="debts.php?action=edit&id=<?php echo $debt_info['id']; ?>">
                    <div class="form-group">
                        <label for="supplier_name">Nhà cung cấp:</label>
                        <input type="text" class="form-control" id="supplier_name" value="<?php echo htmlspecialchars($debt_info['supplier_name']); ?>" disabled>
                    </div>
                     <div class="form-group">
                        <label for="total_amount">Tổng tiền <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="total_amount" name="total_amount" value="<?php echo htmlspecialchars($_POST['total_amount'] ?? $debt_info['total_amount']); ?>" required>
                    </div>

                     <div class="form-group">
                        <label for="paid_amount">Đã thanh toán <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="paid_amount" name="paid_amount" value="<?php echo htmlspecialchars($_POST['paid_amount'] ?? $debt_info['paid_amount']); ?>" required>
                    </div>

                     <div class="form-group">
                        <label for="due_date">Ngày đến hạn <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo htmlspecialchars($_POST['due_date'] ?? $debt_info['due_date']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="status">Trạng thái <span class="text-danger">*</span></label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="current" <?php echo (($_POST['status'] ?? $debt_info['status']) === 'current') ? 'selected' : ''; ?>>Chờ thanh toán</option>
                            <option value="overdue" <?php echo (($_POST['status'] ?? $debt_info['status']) === 'overdue') ? 'selected' : ''; ?>>Quá hạn</option>
                            <option value="paid" <?php echo (($_POST['status'] ?? $debt_info['status']) === 'paid') ? 'selected' : ''; ?>>Đã thanh toán</option>
                        </select>
                    </div>

                     <div class="form-group">
                        <label for="description">Mô tả:</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars(isset($_POST['description']) ? $_POST['description'] : ($debt_info['description'] ?? '')); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu thay đổi
                    </button>
                     <a href="debts.php" class="btn btn-secondary ml-2">
                        <i class="fas fa-times"></i> Hủy
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && $debt_info): ?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card shadow">
            <div class="card-header py-3">
                 <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-info-circle"></i> Chi tiết công nợ: <?php echo htmlspecialchars($debt_info['debt_code']); ?>
                </h6>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Mã nợ:</label>
                    <p class="form-control-static"><?php echo htmlspecialchars($debt_info['debt_code']); ?></p>
                </div>
                 <div class="form-group">
                    <label>Nhà cung cấp:</label>
                    <p class="form-control-static"><?php echo htmlspecialchars($debt_info['supplier_name']); ?></p>
                </div>
                 <div class="form-group">
                    <label>Tổng tiền:</label>
                    <p class="form-control-static"><?php echo formatCurrency($debt_info['total_amount']); ?></p>
                </div>
                 <div class="form-group">
                    <label>Đã thanh toán:</label>
                    <p class="form-control-static"><?php echo formatCurrency($debt_info['paid_amount']); ?></p>
                </div>
                 <div class="form-group">
                    <label>Còn lại:</label>
                    <p class="form-control-static text-danger"><strong><?php echo formatCurrency($debt_info['remaining_amount']); ?></strong></p>
                </div>
                 <div class="form-group">
                    <label>Ngày đến hạn:</label>
                    <p class="form-control-static"><?php echo formatDate($debt_info['due_date']); ?></p>
                </div>
                 <div class="form-group">
                    <label>Trạng thái:</label>
                    <?php 
                        $status_class = '';
                        $status_text = '';
                        switch ($debt_info['status']) {
                            case 'current': 
                                $status_class = 'badge-warning';
                                $status_text = 'Chờ thanh toán';
                                break;
                            case 'overdue': 
                                $status_class = 'badge-danger';
                                $status_text = 'Quá hạn';
                                break;
                            case 'paid': 
                                $status_class = 'badge-success';
                                $status_text = 'Đã thanh toán';
                                break;
                        }
                    ?>
                    <p class="form-control-static"><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_text); ?></span></p>
                </div>

                 <div class="form-group">
                    <label>Mô tả:</label>
                    <p class="form-control-static"><?php echo nl2br(htmlspecialchars($debt_info['description'])); ?></p>
                </div>

                 <div class="form-group">
                    <label>Ngày tạo:</label>
                    <p class="form-control-static"><?php echo formatDateTime($debt_info['created_at']); ?></p>
                </div>
                 <div class="form-group">
                    <label>Cập nhật lần cuối:</label>
                    <p class="form-control-static"><?php echo formatDateTime($debt_info['updated_at']); ?></p>
                </div>

                <a href="debts.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Quay lại danh sách
                </a>
                 <a href="debts.php?action=edit&id=<?php echo $debt_info['id']; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Chỉnh sửa
                </a>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<div style="height: 80px;"></div>

<?php include 'includes/footer.php'; ?> 