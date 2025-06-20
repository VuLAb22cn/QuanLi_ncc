<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

checkLogin();

$page_title = 'Quản lý sản phẩm';
$db = getDB();

// Xử lý các action
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') { #kiểm tra xem form có được nộp bằng phương thức submit không
    try {
        if (isset($_POST['add_product'])) { #kiểm tra xem có phải hành động thêm sản phẩm không
            // Thêm sản phẩm mới
            $name = sanitize($_POST['name']);
            $type = sanitize($_POST['type']);
            $description = sanitize($_POST['description']);
            $supplier_id = (int)$_POST['supplier_id'];
            $status = sanitize($_POST['status']);
            $price = (float)str_replace([',', '.'], '', $_POST['price']);
            
            // Validate
            if (empty($name)) {
                throw new Exception('Tên sản phẩm không được để trống');
            }
            
            if (empty($supplier_id)) {
                throw new Exception('Vui lòng chọn nhà cung cấp');
            }
            
            
            #thêm sản phẩm vào database
            $stmt = $db->prepare("INSERT INTO product (name, type, description, supplier_id, status, image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $type, $description, $supplier_id, $status, $image_path]);
            
            $product_id = $db->lastInsertId();#id của dòng vừa chèn
            
            // Thêm giá sản phẩm vào bảng product_supplier
            if ($price > 0) {
                $stmt = $db->prepare("INSERT INTO product_supplier (supplier_id, product_id, price) VALUES (?, ?, ?)");
                $stmt->execute([$supplier_id, $product_id, $price]);
            }
            
            setFlashMessage('success', 'Thêm sản phẩm thành công!');
            header('Location: products.php');
            exit;
            
        } elseif (isset($_POST['update_product'])) {
            // Cập nhật sản phẩm
            $name = sanitize($_POST['name']);
            $type = sanitize($_POST['type']);
            $description = sanitize($_POST['description']);
            $supplier_id = (int)$_POST['supplier_id'];
            $status = sanitize($_POST['status']);
            $price = (float)str_replace([',', '.'], '', $_POST['price']);
            
            // Validate
            if (empty($name)) {
                throw new Exception('Tên sản phẩm không được để trống');
            }
            
            if (empty($supplier_id)) {
                throw new Exception('Vui lòng chọn nhà cung cấp');
            }
            
    
            
            $stmt = $db->prepare("UPDATE product SET name = ?, type = ?, description = ?, supplier_id = ?, status = ?  WHERE id = ?");
            $stmt->execute([$name, $type, $description, $supplier_id, $status, $id]);
            
            // Cập nhật giá sản phẩm
            if ($price > 0) {
                $stmt = $db->prepare("
                    INSERT INTO product_supplier (supplier_id, product_id, price) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE price = VALUES(price)
                ");
                $stmt->execute([$supplier_id, $id, $price]);
            }
            
            setFlashMessage('success', 'Cập nhật sản phẩm thành công!');
            header('Location: products.php');
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
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_product WHERE product_id = ?");
        $stmt->execute([$id]);
        $bill_count = $stmt->fetch()['count'];
        
        if ($bill_count > 0) {
            setFlashMessage('error', 'Không thể xóa sản phẩm này vì có ' . $bill_count . ' hóa đơn liên quan!');
        } else {
            $stmt = $db->prepare("DELETE FROM product WHERE id = ?");
            $stmt->execute([$id]);
            setFlashMessage('success', 'Xóa sản phẩm thành công!');
        }
        
        header('Location: products.php');
        exit;
    } catch (PDOException $e) {
        setFlashMessage('error', 'Không thể xóa sản phẩm này!');
        header('Location: products.php');
        exit;
    }
}

// Lấy dữ liệu cho form edit
$product = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("
        SELECT p.*, ps.price 
        FROM product p 
        LEFT JOIN product_supplier ps ON p.id = ps.product_id AND p.supplier_id = ps.supplier_id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        setFlashMessage('error', 'Không tìm thấy sản phẩm!');
        header('Location: products.php');
        exit;
    }
}

// Lấy danh sách nhà cung cấp cho dropdown
$suppliers_stmt = $db->query("SELECT id, name FROM supplier WHERE status = 'active' ORDER BY name");
$suppliers = $suppliers_stmt->fetchAll();

// Lấy danh sách sản phẩm với phân trang và tìm kiếm
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $type_filter = $_GET['type'] ?? '';
    $supplier_filter = $_GET['supplier_id'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $records_per_page = 10;
    
    // Xây dựng query
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR s.name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($type_filter)) {
        $where_conditions[] = "p.type = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($supplier_filter)) {
        $where_conditions[] = "p.supplier_id = ?";
        $params[] = $supplier_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Đếm tổng số bản ghi
    $count_stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM product p 
        JOIN supplier s ON p.supplier_id = s.id 
        $where_clause
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Tính phân trang
    $pagination = paginate($total_records, $records_per_page, $page);
    
    // Lấy dữ liệu sản phẩm
    $stmt = $db->prepare("
        SELECT p.*, s.name as supplier_name, ps.price
        FROM product p 
        JOIN supplier s ON p.supplier_id = s.id 
        LEFT JOIN product_supplier ps ON p.id = ps.product_id AND p.supplier_id = ps.supplier_id
        $where_clause 
        ORDER BY p.created_at DESC 
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Lấy danh sách loại sản phẩm để filter
    $types_stmt = $db->query("SELECT DISTINCT type FROM product WHERE type IS NOT NULL AND type != '' ORDER BY type");
    $product_types = $types_stmt->fetchAll();
}

// Lấy dữ liệu cho action view
$product = null;
if ($action === 'view' && $id) {
    $stmt = $db->prepare("
        SELECT p.*, s.name as supplier_name, s.quality_stars, ps.price
        FROM product p 
        JOIN supplier s ON p.supplier_id = s.id 
        LEFT JOIN product_supplier ps ON p.id = ps.product_id AND p.supplier_id = ps.supplier_id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        setFlashMessage('error', 'Không tìm thấy sản phẩm!');
        header('Location: products.php');
        exit;
    }
}

include 'includes/header.php';
?>


<div class="row">
    <div class="col-12">
        <?php echo createBreadcrumb([
            ['title' => 'Dashboard', 'url' => 'dashboard.php'],
            ['title' => 'Sản phẩm']
        ]); ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- Danh sách sản phẩm -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-box"></i> Danh sách sản phẩm
                                <span class="badge badge-info ml-2"><?php echo $total_records ?? 0; ?></span>
                            </h6>
                        </div>
                        <div class="col-auto">
                            <a href="products.php?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Thêm sản phẩm
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Tìm kiếm và lọc -->
                    <form method="GET" class="mb-4">
                        <input type="hidden" name="action" value="list">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Tìm theo tên, mô tả, nhà cung cấp..." 
                                           value="<?php echo htmlspecialchars($search ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <select name="supplier_id" class="form-control">
                                        <option value="">Tất cả nhà cung cấp</option>
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
                                    <select name="type" class="form-control">
                                        <option value="">Tất cả loại</option>
                                        <?php foreach ($product_types as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type['type']); ?>" 
                                                    <?php echo ($type_filter === $type['type']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['type']); ?>
                                            </option>
                                        <?php endforeach; ?>
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
                                    <th>Tên sản phẩm</th>
                                    <th>Loại</th>
                                    <th>Nhà cung cấp</th>
                                    <th>Giá</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            Không có dữ liệu
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                <?php if (!empty($product['description'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($product['type'])): ?>
                                                    <a href="products.php?action=list&type=<?php echo urlencode($product['type']); ?>" class="badge badge-secondary" title="Xem tất cả sản phẩm loại <?php echo htmlspecialchars($product['type']); ?>">
                                                        <?php echo htmlspecialchars($product['type']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa phân loại</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['supplier_name']); ?></td>
                                            <td>
                                                <?php if ($product['price']): ?>
                                                    <strong class="text-success"><?php echo formatCurrency($product['price']); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa có giá</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($product['status'] === 'active'): ?>
                                                    <span class="badge badge-success">Hoạt động</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Không hoạt động</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    
                                                    <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-warning" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="products.php?action=delete&id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-danger" title="Xóa"
                                                       onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')">
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
                                        <a class="page-link" href="?action=list&page=<?php echo $pagination['current_page'] - 1; ?>&search=<?php echo urlencode($search ?? ''); ?>&type=<?php echo urlencode($type_filter ?? ''); ?>&supplier_id=<?php echo urlencode($supplier_filter ?? ''); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                    <li class="page-item <?php echo ($i === $pagination['current_page']) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $i; ?>&search=<?php echo urlencode($search ?? ''); ?>&type=<?php echo urlencode($type_filter ?? ''); ?>&supplier_id=<?php echo urlencode($supplier_filter ?? ''); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?action=list&page=<?php echo $pagination['current_page'] + 1; ?>&search=<?php echo urlencode($search ?? ''); ?>&type=<?php echo urlencode($type_filter ?? ''); ?>&supplier_id=<?php echo urlencode($supplier_filter ?? ''); ?>">
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
    <!-- Form thêm/sửa sản phẩm -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i>
                        <?php echo $action === 'add' ? 'Thêm sản phẩm mới' : 'Sửa thông tin sản phẩm'; ?>
                    </h6>
                </div>
                
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Tên sản phẩm <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">
                                        Vui lòng nhập tên sản phẩm
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="type">Loại sản phẩm</label>
                                    <input type="text" class="form-control" id="type" name="type" 
                                           value="<?php echo htmlspecialchars($product['type'] ?? ''); ?>"
                                           placeholder="VD: Electronics, Furniture, Stationery...">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="supplier_id">Nhà cung cấp <span class="text-danger">*</span></label>
                                    <select class="form-control" id="supplier_id" name="supplier_id" required>
                                        <option value="">Chọn nhà cung cấp</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier['id']; ?>" 
                                                    <?php echo ($product['supplier_id'] ?? '') == $supplier['id'] ? 'selected' : ''; ?>>
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
                                    <label for="price">Giá sản phẩm</label>
                                    <input type="text" class="form-control currency-input" id="price" name="price" 
                                           value="<?php echo $product ? number_format($product['price'], 0, ',', '.') : ''; ?>"
                                           placeholder="Nhập giá sản phẩm">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Trạng thái <span class="text-danger">*</span></label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="active" <?php echo ($product['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                        <option value="inactive" <?php echo ($product['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="image">Hình ảnh sản phẩm</label>
                                    <input type="file" class="form-control-file" id="image" name="image" 
                                           accept="image/*">
                                    <small class="form-text text-muted">Chấp nhận: JPG, PNG, GIF. Tối đa 5MB.</small>
                                    <?php if ($action === 'edit' && !empty($product['image']) && file_exists($product['image'])): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                                 alt="Current image" class="img-thumbnail" style="max-width: 100px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Mô tả sản phẩm</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" name="<?php echo $action === 'add' ? 'add_product' : 'update_product'; ?>" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Thêm sản phẩm' : 'Cập nhật'; ?>
                            </button>
                            <a href="products.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Hủy
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
<?php elseif ($action === 'view' && $product): ?>
    <!-- Chi tiết sản phẩm -->
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Chi tiết sản phẩm: <?php echo htmlspecialchars($product['name']); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-group text-center">
                        <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" class="img-fluid rounded" style="max-height: 200px;" alt="Product Image">
                        <?php else: ?>
                            <i class="fas fa-box-open fa-5x text-muted"></i>
                            <p class="text-muted mt-2">Không có hình ảnh</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Mã sản phẩm:</label>
                        <p class="form-control-static"><?php echo htmlspecialchars($product['id']); ?></p>
                    </div>
                     <div class="form-group">
                        <label>Tên sản phẩm:</label>
                        <p class="form-control-static"><?php echo htmlspecialchars($product['name']); ?></p>
                    </div>
                     <div class="form-group">
                        <label>Loại:</label>
                        <p class="form-control-static"><?php echo htmlspecialchars($product['type']); ?></p>
                    </div>
                     <div class="form-group">
                        <label>Mô tả:</label>
                        <p class="form-control-static"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                     <div class="form-group">
                        <label>Nhà cung cấp:</label>
                        <p class="form-control-static">
                            <?php echo htmlspecialchars($product['supplier_name']); ?>
                            <?php if (isset($product['quality_stars'])): ?>
                                <?php for ($i = 0; $i < $product['quality_stars']; $i++): ?>
                                    <i class="fas fa-star text-warning"></i>
                                <?php endfor; ?>
                                <?php for ($i = $product['quality_stars']; $i < 5; $i++): ?>
                                    <i class="far fa-star text-warning"></i>
                                <?php endfor; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                     <div class="form-group">
                        <label>Giá:</label>
                        <p class="form-control-static"><?php echo formatCurrency($product['price'] ?? 0); ?></p>
                    </div>
                     <div class="form-group">
                        <label>Trạng thái:</label>
                        <?php 
                            $status_class = '';
                            $status_text = '';
                            switch ($product['status']) {
                                case 'active': 
                                    $status_class = 'badge-success';
                                    $status_text = 'Hoạt động';
                                    break;
                                case 'inactive': 
                                    $status_class = 'badge-danger';
                                    $status_text = 'Không hoạt động';
                                    break;
                                case 'draft': 
                                    $status_class = 'badge-secondary';
                                    $status_text = 'Bản nháp';
                                    break;
                            }
                        ?>
                        <p class="form-control-static"><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_text); ?></span></p>
                    </div>

                     <div class="form-group">
                        <label>Ngày tạo:</label>
                        <p class="form-control-static"><?php echo formatDateTime($product['created_at']); ?></p>
                    </div>
                     <div class="form-group">
                        <label>Cập nhật lần cuối:</label>
                        <p class="form-control-static"><?php echo formatDateTime($product['updated_at']); ?></p>
                    </div>

                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại danh sách
                    </a>
                     <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Chỉnh sửa
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
