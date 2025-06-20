<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

checkLogin();

$page_title = 'Hợp đồng';
$db = getDB();

// Xử lý xóa hợp đồng (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $contract_id_to_delete = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($contract_id_to_delete) {
        try {
            // Check if contract exists before deleting
            $stmt_check = $db->prepare("SELECT id FROM contracts WHERE id = ?");
            $stmt_check->execute([$contract_id_to_delete]);
            if ($stmt_check->rowCount() === 0) {
                 setFlashMessage('error', 'Không tìm thấy hợp đồng để xóa.');
            } else {
                $db->beginTransaction();
                // Optional: Check for related data before deleting if necessary
                // $stmt_related = $db->prepare("SELECT COUNT(*) FROM some_related_table WHERE contract_id = ?");
                // $stmt_related->execute([$contract_id_to_delete]);
                // if ($stmt_related->fetchColumn() > 0) {
                //     throw new Exception('Không thể xóa hợp đồng vì có dữ liệu liên quan.');
                // }

                $stmt_delete = $db->prepare("DELETE FROM contracts WHERE id = ?");
                $stmt_delete->execute([$contract_id_to_delete]);

                $db->commit();
                setFlashMessage('success', 'Xóa hợp đồng thành công!');
            }

        } catch (Exception $e) {
            $db->rollBack();
            setFlashMessage('error', 'Lỗi khi xóa hợp đồng: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', 'Không có ID hợp đồng được cung cấp để xóa.');
    }

    header('Location: contracts.php'); // Redirect back to list after delete attempt
    exit();
}

// Xử lý gửi form (Thêm mới và Cập nhật)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_contract'])) {
        // Lấy và làm sạch dữ liệu form
        $contract_code = sanitize($_POST['contract_code']);
        $supplier_id = filter_var($_POST['supplier_id'], FILTER_VALIDATE_INT);
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $value = filter_var(str_replace(['.', ','], '', $_POST['value']), FILTER_VALIDATE_FLOAT);
        $status = sanitize($_POST['status']);
        $file_path = sanitize($_POST['file_path']); // Giả định nhập path file, có thể nâng cấp thành upload sau

        // Xác thực cơ bản
        if (empty($contract_code) || !$supplier_id || empty($title) || empty($start_date) || empty($end_date) || $value <= 0 || empty($status)) {
            setFlashMessage('error', 'Vui lòng điền đầy đủ các thông tin bắt buộc.');
        } else {
            try {
                $db->beginTransaction();

                // Kiểm tra trùng mã hợp đồng (tùy chọn, nếu mã là UNIQUE)
                $stmt_check = $db->prepare("SELECT COUNT(*) FROM contracts WHERE contract_code = ?");
                $stmt_check->execute([$contract_code]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('Mã hợp đồng đã tồn tại.');
                }

                $stmt = $db->prepare("INSERT INTO contracts (contract_code, supplier_id, title, description, start_date, end_date, value, status, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$contract_code, $supplier_id, $title, $description, $start_date, $end_date, $value, $status, $file_path]);

                $db->commit();
                setFlashMessage('success', 'Thêm hợp đồng mới thành công!');

            } catch (Exception $e) {
                $db->rollBack();
                setFlashMessage('error', 'Lỗi khi thêm hợp đồng: ' . $e->getMessage());
            }
        }
        // Chuyển hướng về trang danh sách sau khi xử lý POST
        header('Location: contracts.php');
        exit();
    }

    if (isset($_POST['update_contract'])) {
        // Lấy và làm sạch dữ liệu form cập nhật
        $id_to_update = filter_var($_POST['contract_id'], FILTER_VALIDATE_INT);
        $contract_code = sanitize($_POST['contract_code']);
        $supplier_id = filter_var($_POST['supplier_id'], FILTER_VALIDATE_INT);
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $value = filter_var(str_replace(['.', ','], '', $_POST['value']), FILTER_VALIDATE_FLOAT);
        $status = sanitize($_POST['status']);
        $file_path = sanitize($_POST['file_path']);

        if (!$id_to_update || empty($contract_code) || !$supplier_id || empty($title) || empty($start_date) || empty($end_date) || $value <= 0 || empty($status)) {
            setFlashMessage('error', 'Vui lòng điền đầy đủ các thông tin bắt buộc cho hợp đồng cần cập nhật.');
        } else {
            try {
                $db->beginTransaction();

                 // Kiểm tra trùng mã hợp đồng (trừ hợp đồng hiện tại)
                $stmt_check = $db->prepare("SELECT COUNT(*) FROM contracts WHERE contract_code = ? AND id != ?");
                $stmt_check->execute([$contract_code, $id_to_update]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('Mã hợp đồng đã tồn tại cho hợp đồng khác.');
                }

                $stmt = $db->prepare("UPDATE contracts SET contract_code = ?, supplier_id = ?, title = ?, description = ?, start_date = ?, end_date = ?, value = ?, status = ?, file_path = ? WHERE id = ?");
                $stmt->execute([$contract_code, $supplier_id, $title, $description, $start_date, $end_date, $value, $status, $file_path, $id_to_update]);

                $db->commit();
                setFlashMessage('success', 'Cập nhật hợp đồng thành công!');

            } catch (Exception $e) {
                $db->rollBack();
                setFlashMessage('error', 'Lỗi khi cập nhật hợp đồng: ' . $e->getMessage());
            }
        }
        // Chuyển hướng về trang danh sách sau khi xử lý POST
        header('Location: contracts.php');
        exit();
    }
}

// Lấy danh sách hợp đồng, join với bảng supplier để lấy tên nhà cung cấp
// Biến $contracts chỉ cần thiết cho action 'list'
$contracts = [];
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    try {
        $stmt = $db->query("
            SELECT c.*, s.name as supplier_name 
            FROM contracts c 
            JOIN supplier s ON c.supplier_id = s.id
            ORDER BY c.end_date ASC, c.created_at DESC
        ");
        $contracts = $stmt->fetchAll();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Lỗi truy vấn dữ liệu hợp đồng: ' . $e->getMessage());
    }
}

// Lấy danh sách nhà cung cấp cho dropdown form (Thêm mới và Cập nhật)
$suppliers = [];
if ($action === 'add' || $action === 'edit') {
    try {
        $stmt = $db->query("SELECT id, name FROM supplier ORDER BY name");
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        setFlashMessage('error', 'Lỗi khi lấy danh sách nhà cung cấp: ' . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <?php echo createBreadcrumb([
            ['title' => 'Dashboard', 'url' => 'dashboard.php'],
            ['title' => 'Hợp đồng']
        ]); ?>
    </div>
</div>

<?php echo getFlashMessage(); // Hiển thị flash message nếu có ?>

<?php
// Hiển thị các chế độ xem khác nhau dựa trên hành động
switch ($action) {
    case 'add':
        $page_title = 'Thêm hợp đồng mới';
        ?>

        <div class="row">
            <div class="col-12">
                <?php echo createBreadcrumb([
                    ['title' => 'Dashboard', 'url' => 'dashboard.php'],
                    ['title' => 'Hợp đồng', 'url' => 'contracts.php'],
                    ['title' => 'Thêm mới']
                ]); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-plus"></i> Thêm hợp đồng mới
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="contracts.php">
                            <input type="hidden" name="add_contract" value="1">

                            <div class="form-group">
                                <label for="contract_code">Mã HĐ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="contract_code" name="contract_code" required>
                            </div>

                            <div class="form-group">
                                <label for="supplier_id">Nhà cung cấp <span class="text-danger">*</span></label>
                                <select class="form-control" id="supplier_id" name="supplier_id" required>
                                    <option value="">-- Chọn nhà cung cấp --</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="title">Tiêu đề <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>

                            <div class="form-group">
                                <label for="description">Mô tả</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="start_date">Ngày bắt đầu <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>

                            <div class="form-group">
                                <label for="end_date">Ngày kết thúc <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>

                            <div class="form-group">
                                <label for="value">Giá trị <span class="text-danger">*</span></label>
                                <input type="text" class="form-control currency-input" id="value" name="value" required>
                            </div>

                            <div class="form-group">
                                <label for="status">Trạng thái <span class="text-danger">*</span></label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="draft">Bản nháp</option>
                                    <option value="active">Hoạt động</option>
                                    <option value="expired">Hết hạn</option>
                                    <option value="terminated">Đã chấm dứt</option>
                                </select>
                            </div>

                             <div class="form-group">
                                <label for="file_path">Đường dẫn File (Tùy chọn)</label>
                                <input type="text" class="form-control" id="file_path" name="file_path">
                            </div>

                            <button type="submit" class="btn btn-primary">Tạo hợp đồng</button>
                            <a href="contracts.php" class="btn btn-secondary">Hủy</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php
        break;

    case 'edit':
        $page_title = 'Chỉnh sửa hợp đồng';
        $contract_to_edit = null;
        $contract_id_to_edit = $_GET['id'] ?? null;

        if ($contract_id_to_edit) {
            try {
                $stmt = $db->prepare("SELECT * FROM contracts WHERE id = ?");
                $stmt->execute([$contract_id_to_edit]);
                $contract_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$contract_to_edit) {
                    setFlashMessage('error', 'Không tìm thấy hợp đồng để chỉnh sửa.');
                    header('Location: contracts.php');
                    exit();
                }
            } catch (PDOException $e) {
                setFlashMessage('error', 'Lỗi khi lấy thông tin hợp đồng: ' . $e->getMessage());
                header('Location: contracts.php');
                exit();
            }
        } else {
            setFlashMessage('error', 'Không có ID hợp đồng được cung cấp để chỉnh sửa.');
            header('Location: contracts.php');
            exit();
        }

        ?>

        <div class="row">
            <div class="col-12">
                <?php echo createBreadcrumb([
                    ['title' => 'Dashboard', 'url' => 'dashboard.php'],
                    ['title' => 'Hợp đồng', 'url' => 'contracts.php'],
                    ['title' => 'Chỉnh sửa']
                ]); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-edit"></i> Chỉnh sửa hợp đồng: <?php echo htmlspecialchars($contract_to_edit['contract_code']); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="contracts.php">
                            <input type="hidden" name="update_contract" value="1">
                            <input type="hidden" name="contract_id" value="<?php echo $contract_to_edit['id']; ?>">

                            <div class="form-group">
                                <label for="contract_code">Mã HĐ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="contract_code" name="contract_code" value="<?php echo htmlspecialchars($contract_to_edit['contract_code']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="supplier_id">Nhà cung cấp <span class="text-danger">*</span></label>
                                <select class="form-control" id="supplier_id" name="supplier_id" required>
                                    <option value="">-- Chọn nhà cung cấp --</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id']; ?>" <?php echo ($contract_to_edit['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($supplier['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="title">Tiêu đề <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($contract_to_edit['title']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="description">Mô tả</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($contract_to_edit['description']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="start_date">Ngày bắt đầu <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($contract_to_edit['start_date']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="end_date">Ngày kết thúc <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($contract_to_edit['end_date']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="value">Giá trị <span class="text-danger">*</span></label>
                                <input type="text" class="form-control currency-input" id="value" name="value" value="<?php echo htmlspecialchars($contract_to_edit['value']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="status">Trạng thái <span class="text-danger">*</span></label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="draft" <?php echo ($contract_to_edit['status'] === 'draft') ? 'selected' : ''; ?>>Bản nháp</option>
                                    <option value="active" <?php echo ($contract_to_edit['status'] === 'active') ? 'selected' : ''; ?>>Hoạt động</option>
                                    <option value="expired" <?php echo ($contract_to_edit['status'] === 'expired') ? 'selected' : ''; ?>>Hết hạn</option>
                                    <option value="terminated" <?php echo ($contract_to_edit['status'] === 'terminated') ? 'selected' : ''; ?>>Đã chấm dứt</option>
                                </select>
                            </div>

                             <div class="form-group">
                                <label for="file_path">Đường dẫn File (Tùy chọn)</label>
                                <input type="text" class="form-control" id="file_path" name="file_path" value="<?php echo htmlspecialchars($contract_to_edit['file_path']); ?>">
                            </div>

                            <button type="submit" class="btn btn-primary">Cập nhật hợp đồng</button>
                            <a href="contracts.php" class="btn btn-secondary">Hủy</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php
        break;

    case 'delete':
        // No code needed here anymore
        break;

    case 'list':
    default:
        // Mã hiển thị danh sách hiện có
        ?>

        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-file-contract"></i> Danh sách hợp đồng
                                </h6>
                            </div>
                            <div class="col-auto">
                                <a href="contracts.php?action=add" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Thêm hợp đồng
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Mã HĐ</th>
                                        <th>Nhà cung cấp</th>
                                        <th>Tiêu đề</th>
                                        <th>Ngày bắt đầu</th>
                                        <th>Ngày kết thúc</th>
                                        <th>Giá trị</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($contracts)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                Không có dữ liệu
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($contracts as $contract): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($contract['contract_code']); ?></td>
                                                <td><?php echo htmlspecialchars($contract['supplier_name']); ?></td>
                                                <td><?php echo htmlspecialchars($contract['title']); ?></td>
                                                <td><?php echo formatDate($contract['start_date']); ?></td>
                                                <td><?php echo formatDate($contract['end_date']); ?></td>
                                                <td><?php echo formatCurrency($contract['value']); ?></td>
                                                <td>
                                                    <?php 
                                                        $status_class = '';
                                                        $status_text = '';
                                                        switch ($contract['status']) {
                                                            case 'active': 
                                                                $status_class = 'badge-success';
                                                                $status_text = 'Hoạt động';
                                                                break;
                                                            case 'draft': 
                                                                $status_class = 'badge-secondary'; // Using secondary for a neutral color like gray/draft
                                                                $status_text = 'Không hoạt động';
                                                                break;
                                                            case 'expired': 
                                                                $status_class = 'badge-warning';
                                                                $status_text = 'Hết hạn';
                                                                break;
                                                            case 'terminated': 
                                                                $status_class = 'badge-danger';
                                                                $status_text = 'Đã chấm dứt';
                                                                break;
                                                            // Thêm các case khác nếu có trạng thái khác
                                                        }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_text); ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="contracts.php?action=edit&id=<?php echo $contract['id']; ?>"
                                                           class="btn btn-warning" title="Sửa hợp đồng">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="contracts.php?action=delete&id=<?php echo $contract['id']; ?>"
                                                           class="btn btn-danger" title="Xóa hợp đồng"
                                                           onclick="return confirm('Bạn có chắc chắn muốn xóa hợp đồng này?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <?php if (!empty($contract['file_path'])): ?>
                                                            <a href="<?php echo htmlspecialchars($contract['file_path']); ?>"
                                                               class="btn btn-info" title="Tải file hợp đồng" download>
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                        <?php endif; ?>
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

        <?php
        break;
}
?>

<div style="height: 80px;"></div>

<?php include 'includes/footer.php'; ?>