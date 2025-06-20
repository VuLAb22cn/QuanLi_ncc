<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

checkLogin();

$page_title = 'Sổ quỹ';
$db = getDB();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$transaction = null;

// Handle POST requests for add/edit and GET request for delete BEFORE including header
switch ($action) {
    case 'add':
        $page_title = 'Thêm giao dịch sổ quỹ';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $date = $_POST['date'] ?? '';
            $type = $_POST['type'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $description = $_POST['description'] ?? '';
            $category = $_POST['category'] ?? '';
            $reference_id = $_POST['reference_id'] ?? null;
            $reference_type = $_POST['reference_type'] ?? null;
            
            // Simple validation
            if (empty($date) || empty($type) || $amount <= 0) {
                setFlashMessage('error', 'Vui lòng điền đầy đủ thông tin bắt buộc (Ngày, Loại, Số tiền).');
                header('Location: cashbook.php?action=add');
                exit();
            }
            
            try {
                // Generate unique transaction code (simple example, might need more robust logic)
                $transaction_code = 'GD' . time(); 
                
                $stmt = $db->prepare("
                    INSERT INTO cashbook (transaction_code, date, type, amount, description, category, reference_id, reference_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$transaction_code, $date, $type, $amount, $description, $category, $reference_id, $reference_type]);
                
                setFlashMessage('success', 'Thêm giao dịch sổ quỹ thành công!');
                header('Location: cashbook.php');
                exit();
                
            } catch (PDOException $e) {
                setFlashMessage('error', 'Lỗi thêm giao dịch: ' . $e->getMessage());
                header('Location: cashbook.php?action=add');
                exit();
            }
        }
        break;
        
    case 'edit':
        $page_title = 'Chỉnh sửa giao dịch sổ quỹ';
        // Fetch transaction data for displaying in the form
        if ($id) {
            try {
                $stmt = $db->prepare("SELECT * FROM cashbook WHERE id = ?");
                $stmt->execute([$id]);
                $transaction = $stmt->fetch();
                
                if (!$transaction) {
                    setFlashMessage('error', 'Không tìm thấy giao dịch.');
                    header('Location: cashbook.php');
                    exit();
                }
            } catch (PDOException $e) {
                setFlashMessage('error', 'Lỗi lấy dữ liệu giao dịch: ' . $e->getMessage());
                header('Location: cashbook.php');
                exit();
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'] ?? null;
            $date = $_POST['date'] ?? '';
            $type = $_POST['type'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $description = $_POST['description'] ?? '';
            $category = $_POST['category'] ?? '';
            $reference_id = $_POST['reference_id'] ?? null;
            $reference_type = $_POST['reference_type'] ?? null;
            
            // Simple validation
            if (!$id || empty($date) || empty($type) || $amount <= 0) {
                setFlashMessage('error', 'Thông tin chỉnh sửa không hợp lệ.');
                // Redirect back to edit page with data if possible, or just list
                header('Location: cashbook.php?action=edit&id=' . $id); 
                exit();
            }
            
            try {
                $stmt = $db->prepare("
                    UPDATE cashbook 
                    SET date = ?, type = ?, amount = ?, description = ?, category = ?, reference_id = ?, reference_type = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$date, $type, $amount, $description, $category, $reference_id, $reference_type, $id]);
                
                setFlashMessage('success', 'Cập nhật giao dịch sổ quỹ thành công!');
                header('Location: cashbook.php');
                exit();
                
            } catch (PDOException $e) {
                setFlashMessage('error', 'Lỗi cập nhật giao dịch: ' . $e->getMessage());
                header('Location: cashbook.php?action=edit&id=' . $id); // Redirect back to edit page on error
                exit();
            }
        }
        break;
        
    case 'delete':
        if ($id) {
            try {
                $stmt = $db->prepare("DELETE FROM cashbook WHERE id = ?");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount()) {
                    setFlashMessage('success', 'Xóa giao dịch sổ quỹ thành công!');
                } else {
                    setFlashMessage('error', 'Không tìm thấy giao dịch để xóa.');
                }
                
            } catch (PDOException $e) {
                setFlashMessage('error', 'Lỗi xóa giao dịch: ' . $e->getMessage());
            }
        }
        // Always redirect back to list after delete attempt
        header('Location: cashbook.php');
        exit();
        
    case 'list':
    default:
        $page_title = 'Sổ quỹ';
        $transactions = [];
        $total_income = 0;
        $total_expense = 0;
        $balance = 0;

        try {
            // Get total income
            $stmt_income = $db->query("SELECT SUM(amount) as total FROM cashbook WHERE type = 'income'");
            $total_income = $stmt_income->fetch()['total'] ?? 0;

            // Get total expense
            $stmt_expense = $db->query("SELECT SUM(amount) as total FROM cashbook WHERE type = 'expense'");
            $total_expense = $stmt_expense->fetch()['total'] ?? 0;

            // Calculate balance
            $balance = $total_income - $total_expense;

            $stmt = $db->query("SELECT * FROM cashbook ORDER BY date DESC, created_at DESC");
            $transactions = $stmt->fetchAll();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Lỗi truy vấn dữ liệu sổ quỹ: ' . $e->getMessage());
        }
        break;
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <?php echo createBreadcrumb([
            ['title' => 'Dashboard', 'url' => 'dashboard.php'],
            ['title' => $page_title]
        ]); ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Tổng thu
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_income); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-arrow-up fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Tổng chi
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_expense); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-arrow-down fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Số dư
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($balance); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-balance-scale fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Lịch sử giao dịch -->
<div class="row">
    <div class="col-12">
        <h2 class="h4 mb-3">Lịch sử giao dịch</h2>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus-circle"></i> <?php echo $page_title; ?>
                    </h6>
                </div>
                
                <div class="card-body">
                    <?php echo getFlashMessage(); // Hiển thị flash message nếu có ?>

                    <form method="POST" action="cashbook.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . htmlspecialchars($id) : ''; ?>">
                        <?php if ($action === 'edit' && $transaction): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($transaction['id']); ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="date">Ngày <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($transaction['date'] ?? date('Y-m-d')); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="type">Loại <span class="text-danger">*</span></label>
                            <select class="form-control" id="type" name="type" required>
                                <option value="income" <?php echo isset($transaction['type']) && $transaction['type'] === 'income' ? 'selected' : ''; ?>>Thu</option>
                                <option value="expense" <?php echo isset($transaction['type']) && $transaction['type'] === 'expense' ? 'selected' : ''; ?>>Chi</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="amount">Số tiền <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($transaction['amount'] ?? ''); ?>" required min="0">
                        </div>

                        <div class="form-group">
                            <label for="description">Mô tả</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($transaction['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="category">Danh mục</label>
                            <input type="text" class="form-control" id="category" name="category" value="<?php echo htmlspecialchars($transaction['category'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="reference_type">Loại tham chiếu</label>
                            <select class="form-control" id="reference_type" name="reference_type">
                                <option value="">Không</option>
                                <option value="bill" <?php echo isset($transaction['reference_type']) && $transaction['reference_type'] === 'bill' ? 'selected' : ''; ?>>Hóa đơn</option>
                                <option value="payment" <?php echo isset($transaction['reference_type']) && $transaction['reference_type'] === 'payment' ? 'selected' : ''; ?>>Thanh toán</option>
                                <option value="other" <?php echo isset($transaction['reference_type']) && $transaction['reference_type'] === 'other' ? 'selected' : ''; ?>>Khác</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reference_id">ID tham chiếu</label>
                            <input type="text" class="form-control" id="reference_id" name="reference_id" value="<?php echo htmlspecialchars($transaction['reference_id'] ?? ''); ?>">
                            <small class="form-text text-muted">Nhập ID của Hóa đơn hoặc Thanh toán nếu có.</small>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Lưu giao dịch
                        </button>
                        <a href="cashbook.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Hủy
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($action === 'list'): ?>
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-book"></i> Danh sách giao dịch sổ quỹ
                            </h6>
                        </div>
                        <div class="col-auto">
                            <a href="cashbook.php?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Thêm giao dịch
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php echo getFlashMessage(); // Hiển thị flash message nếu có ?>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>Mã GD</th>
                                    <th>Ngày</th>
                                    <th>Loại</th>
                                    <th>Số tiền</th>
                                    <th>Mô tả</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            Không có dữ liệu
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($transaction['transaction_code']); ?></td>
                                            <td><?php echo formatDate($transaction['date']); ?></td>
                                            <td>
                                                <?php if ($transaction['type'] === 'income'): ?>
                                                    <span class="badge badge-success">Thu</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Chi</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($transaction['type'] === 'income'): ?>
                                                    <strong class="text-success">+ <?php echo formatCurrency($transaction['amount']); ?></strong>
                                                <?php else: ?>
                                                    <strong class="text-danger">- <?php echo formatCurrency($transaction['amount']); ?></strong>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="cashbook.php?action=edit&id=<?php echo $transaction['id']; ?>" 
                                                       class="btn btn-warning" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="cashbook.php?action=delete&id=<?php echo $transaction['id']; ?>" 
                                                       class="btn btn-danger" title="Xóa"
                                                       onclick="return confirm('Bạn có chắc chắn muốn xóa giao dịch này?')">
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
<?php endif; ?>

<?php include 'includes/footer.php'; ?> 