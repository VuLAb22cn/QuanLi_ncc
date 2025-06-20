<?php
/**
 * Tìm kiếm toàn diện
 */
require_once 'config/database.php';
require_once 'includes/functions.php';

$page_title = 'Tìm kiếm toàn diện';
$db = getDB();

$keyword = $_GET['keyword'] ?? '';
$search_type = $_GET['type'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$results = [
    'suppliers' => [],
    'invoices' => [],
    'payments' => [],
    'debts' => []
];

try {
    // Tìm kiếm nhà cung cấp
    if ($search_type === 'all' || $search_type === 'suppliers') {
        $params = [];
        $where_conditions = [];
        
        if (!empty($keyword)) {
            $where_conditions[] = "(name LIKE ? OR email LIKE ? OR tel LIKE ?)";
            $search_param = "%$keyword%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
        }
        
        $sql = "SELECT *, 'supplier' as result_type FROM supplier";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY name";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results['suppliers'] = $stmt->fetchAll();
    }
    
    // Tìm kiếm hóa đơn
    if ($search_type === 'all' || $search_type === 'invoices') {
        $params = [];
        $where_conditions = [];
        
        if (!empty($keyword)) {
            $where_conditions[] = "(b.bill_code LIKE ? OR b.description LIKE ? OR s.name LIKE ?)";
            $search_param = "%$keyword%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
        }
        
        if (!empty($date_from) && !empty($date_to)) {
            $where_conditions[] = "b.date BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }
        
        $sql = "SELECT b.*, s.name as supplier_name, 'invoice' as result_type 
                FROM bill b 
                JOIN supplier s ON b.supplier_id = s.id";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY b.date DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results['invoices'] = $stmt->fetchAll();
    }
    
    // Tìm kiếm thanh toán
    if ($search_type === 'all' || $search_type === 'payments') {
        $params = [];
        $where_conditions = [];
        
        if (!empty($keyword)) {
            $where_conditions[] = "(p.code LIKE ? OR p.description LIKE ? OR s.name LIKE ?)";
            $search_param = "%$keyword%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
        }
        
        if (!empty($date_from) && !empty($date_to)) {
            $where_conditions[] = "p.payment_date BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }
        
        $sql = "SELECT p.id, p.payment_code AS code, p.amount, p.payment_date, p.payment_method, p.status, p.description, p.reference_code, s.name as supplier_name, 'payment' as result_type 
                FROM payments p 
                JOIN supplier s ON p.supplier_id = s.id";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY p.payment_date DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results['payments'] = $stmt->fetchAll();
    }
    
    // Tìm kiếm công nợ
    if ($search_type === 'all' || $search_type === 'debts') {
        $params = [];
        $where_conditions = [];
        
        if (!empty($keyword)) {
            $where_conditions[] = "(d.code LIKE ? OR s.name LIKE ?)";
            $search_param = "%$keyword%";
            $params = array_merge($params, [$search_param, $search_param]);
        }
        
        if (!empty($date_from) && !empty($date_to)) {
            $where_conditions[] = "d.due_date BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }
        
        $sql = "SELECT d.*, s.name as supplier_name, 'debt' as result_type 
                FROM debts d 
                JOIN supplier s ON d.supplier_id = s.id";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        } else {
            $sql .= " WHERE 1=1";
        }
        $sql .= " AND d.status != 'paid'";
        $sql .= " ORDER BY d.due_date DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results['debts'] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $error = "Lỗi tìm kiếm: " . $e->getMessage();
}

$total_results = count($results['suppliers']) + count($results['invoices']) + count($results['payments']) + count($results['debts']);

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
</style>

<div class="row">
    <div class="col-12">
        <?php echo createBreadcrumb([
            ['title' => 'Dashboard', 'url' => 'index.php'],
            ['title' => 'Tìm kiếm']
        ]); ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-search"></i> Tìm kiếm toàn diện
                </h5>
            </div>
            
            <div class="card-body">
                <!-- Form tìm kiếm -->
                <form method="GET" class="search-box">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="keyword">Từ khóa tìm kiếm (không bắt buộc)</label>
                                <input type="text" class="form-control" id="keyword" name="keyword" 
                                       placeholder="Nhập từ khóa..." 
                                       value="<?php echo htmlspecialchars($keyword); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="type">Loại tìm kiếm</label>
                                <select class="form-control" id="type" name="type">
                                    <option value="all" <?php echo $search_type === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                                    <option value="suppliers" <?php echo $search_type === 'suppliers' ? 'selected' : ''; ?>>Nhà cung cấp</option>
                                    <option value="invoices" <?php echo $search_type === 'invoices' ? 'selected' : ''; ?>>Hóa đơn</option>
                                    <option value="payments" <?php echo $search_type === 'payments' ? 'selected' : ''; ?>>Thanh toán</option>
                                    <option value="debts" <?php echo $search_type === 'debts' ? 'selected' : ''; ?>>Công nợ</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="date_from">Từ ngày</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="date_to">Đến ngày</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search"></i> Tìm kiếm
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($search_type !== 'all' || !empty($keyword) || (!empty($date_from) && !empty($date_to))): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                Tìm thấy <strong><?php echo $total_results; ?></strong> kết quả
                <?php if (!empty($keyword)): ?>
                    cho từ khóa "<strong><?php echo htmlspecialchars($keyword); ?></strong>"
                <?php endif; ?>
                <?php if (!empty($date_from) && !empty($date_to)): ?>
                    trong khoảng thời gian từ <strong><?php echo formatDate($date_from); ?></strong> đến <strong><?php echo formatDate($date_to); ?></strong>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kết quả tìm kiếm nhà cung cấp -->
    <?php if (!empty($results['suppliers'])): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-building"></i> Nhà cung cấp (<?php echo count($results['suppliers']); ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Mã</th>
                                        <th>Tên</th>
                                        <th>Email</th>
                                        <th>Điện thoại</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results['suppliers'] as $supplier): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($supplier['id']); ?></td>
                                            <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                            <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                                            <td><?php echo htmlspecialchars($supplier['tel']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $supplier['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $supplier['status'] === 'active' ? 'Hoạt động' : 'Không hoạt động'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="suppliers.php?action=view&id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Kết quả tìm kiếm hóa đơn -->
    <?php if (!empty($results['invoices'])): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-file-invoice"></i> Hóa đơn (<?php echo count($results['invoices']); ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Mã HĐ</th>
                                        <th>Nhà cung cấp</th>
                                        <th>Số tiền</th>
                                        <th>Ngày tạo</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results['invoices'] as $invoice): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($invoice['bill_code']); ?></td>
                                            <td><?php echo htmlspecialchars($invoice['supplier_name']); ?></td>
                                            <td><?php echo formatCurrency($invoice['total_amount']); ?></td>
                                            <td><?php echo formatDate($invoice['date']); ?></td>
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
                                            <td>
                                                <a href="invoices.php?action=view&id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Kết quả tìm kiếm thanh toán -->
    <?php if (!empty($results['payments'])): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-credit-card"></i> Thanh toán (<?php echo count($results['payments']); ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Mã TT</th>
                                        <th>Nhà cung cấp</th>
                                        <th>Số tiền</th>
                                        <th>Ngày thanh toán</th>
                                        <th>Phương thức</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results['payments'] as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['code']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['supplier_name']); ?></td>
                                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                                            <td><?php echo formatDate($payment['payment_date']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($payment['payment_method']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badge_class = '';
                                                $status_text = '';
                                                switch ($payment['status']) {
                                                    case 'completed':
                                                        $badge_class = 'badge-success';
                                                        $status_text = 'Hoàn thành';
                                                        break;
                                                    case 'pending':
                                                        $badge_class = 'badge-warning';
                                                        $status_text = 'Đang xử lý';
                                                        break;
                                                    case 'failed':
                                                        $badge_class = 'badge-danger';
                                                        $status_text = 'Thất bại';
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
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Kết quả tìm kiếm công nợ -->
    <?php if (!empty($results['debts'])): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-exclamation-triangle"></i> Công nợ (<?php echo count($results['debts']); ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Mã CN</th>
                                        <th>Nhà cung cấp</th>
                                        <th>Tổng tiền</th>
                                        <th>Còn lại</th>
                                        <th>Ngày đến hạn</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results['debts'] as $debt): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($debt['debt_code']); ?></td>
                                            <td><?php echo htmlspecialchars($debt['supplier_name']); ?></td>
                                            <td><?php echo formatCurrency($debt['total_amount']); ?></td>
                                            <td><strong class="text-danger"><?php echo formatCurrency($debt['remaining_amount']); ?></strong></td>
                                            <td><?php echo formatDate($debt['due_date']); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = '';
                                                $status_text = '';
                                                switch ($debt['status']) {
                                                    case 'paid':
                                                        $badge_class = 'badge-success';
                                                        $status_text = 'Đã thanh toán';
                                                        break;
                                                    case 'current':
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
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($total_results === 0): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning text-center">
                    <i class="fas fa-search"></i> Không tìm thấy kết quả nào cho từ khóa "<strong><?php echo htmlspecialchars($keyword); ?></strong>"
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> Nhập từ khóa để bắt đầu tìm kiếm
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        const keywordInput = $('#keyword');
        const searchTypeSelect = $('#type');
        let suggestionsDiv = $('<div class="autocomplete-suggestions"></div>').insertAfter(keywordInput.parent());

        keywordInput.on('input', function() {
            const keyword = $(this).val();
            const searchType = searchTypeSelect.val();

            if (searchType === 'suppliers' && keyword.length > 0) {
                $.ajax({
                    url: 'ajax/search_suppliers_ajax.php',
                    method: 'GET',
                    data: { keyword: keyword },
                    success: function(data) {
                        suggestionsDiv.empty();
                        if (data.length > 0) {
                            data.forEach(function(supplier) {
                                $('<div>').text(supplier.name).data('supplier-id', supplier.id).appendTo(suggestionsDiv);
                            });
                            suggestionsDiv.show();
                        } else {
                            suggestionsDiv.hide();
                        }
                    },
                    error: function() {
                        suggestionsDiv.empty().hide();
                    }
                });
            } else {
                suggestionsDiv.empty().hide();
            }
        });

        // Handle suggestion click
        suggestionsDiv.on('click', 'div', function() {
            keywordInput.val($(this).text());
            // Optionally, trigger search or select supplier ID
            // keywordInput.closest('form').submit(); 
            // You might want to store the selected supplier ID if needed for the form submission
            suggestionsDiv.hide();
        });

        // Hide suggestions when clicking outside
        $(document).on('click', function(event) {
            if (!$(event.target).closest('.form-group').is(keywordInput.parent())) {
                suggestionsDiv.hide();
            }
        });
         // Ensure suggestions are hidden if input is cleared
         keywordInput.on('blur', function(){
             setTimeout(function() { // Delay hiding to allow click event on suggestion
                 if (!suggestionsDiv.is(':hover')) { // Check if mouse is over suggestions
                     suggestionsDiv.hide();
                 }
             }, 100);
         });

        // Optional: Clear input if search type changes from suppliers
        searchTypeSelect.on('change', function() {
            if ($(this).val() !== 'suppliers') {
                keywordInput.val('');
                suggestionsDiv.hide();
            }
        });
    });

     // Prevent form submission when clicking on a suggestion (if needed)
     // $(document).on('mousedown', '.autocomplete-suggestions div', function(event) {
     //     event.preventDefault();
     // });

</script>
