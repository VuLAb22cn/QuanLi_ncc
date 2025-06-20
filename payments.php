<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

checkLogin();

$page_title = 'Lịch sử tài chính'; // Default title
$db = getDB();

$items = []; // Change variable name to be more general (payments and debts)
$supplier_filter_name = '';

// Check for supplier_id in GET parameters
$supplier_id_filter = filter_var($_GET['supplier_id'] ?? null, FILTER_VALIDATE_INT);

// Xử lý xóa thanh toán (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $item_id_to_delete = $_GET['id'] ?? null;
    $item_type_to_delete = $_GET['type'] ?? null;

    if ($item_id_to_delete && ($item_type_to_delete === 'payment' || $item_type_to_delete === 'debt')) {
        try {
            $db->beginTransaction();
            
            if ($item_type_to_delete === 'payment') {
                // Check if payment exists before deleting (optional but good practice)
                $stmt_check = $db->prepare("SELECT id FROM payments WHERE id = ?");
                $stmt_check->execute([$item_id_to_delete]);
                if ($stmt_check->rowCount() === 0) {
                     setFlashMessage('error', 'Không tìm thấy thanh toán để xóa.');
                     $db->rollBack(); // Rollback the transaction
                } else {
                    $stmt_delete = $db->prepare("DELETE FROM payments WHERE id = ?");
                    $stmt_delete->execute([$item_id_to_delete]);
                    // Optional: Log the deletion or perform related updates
                    $db->commit();
                    setFlashMessage('success', 'Xóa thanh toán thành công!');
                }

            } elseif ($item_type_to_delete === 'debt') {
                // Check if debt exists before deleting (optional but good practice)
                $stmt_check = $db->prepare("SELECT id FROM debts WHERE id = ?");
                $stmt_check->execute([$item_id_to_delete]);
                if ($stmt_check->rowCount() === 0) {
                     setFlashMessage('error', 'Không tìm thấy công nợ để xóa.');
                     $db->rollBack(); // Rollback the transaction
                } else {
                    // IMPORTANT: Deleting a debt might require updating related invoices/bills
                    // Add logic here if needed, e.g., setting invoice status back to pending
                    $stmt_delete = $db->prepare("DELETE FROM debts WHERE id = ?");
                    $stmt_delete->execute([$item_id_to_delete]);
                    $db->commit();
                    setFlashMessage('success', 'Xóa công nợ thành công!');
                }
            }

        } catch (PDOException $e) {
            $db->rollBack();
            setFlashMessage('error', 'Lỗi khi xóa mục tài chính: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', 'Không có thông tin mục tài chính hợp lệ được cung cấp để xóa.');
    }

    // Determine the redirect URL based on whether supplier_id was present
    $redirect_url = 'payments.php';
    if ($supplier_id_filter) {
        $redirect_url .= '?supplier_id=' . $supplier_id_filter;
    }

    header('Location: ' . $redirect_url);
    exit(); // Stop script execution after redirect
}

// Build the SQL query to combine payments and debts
$sql = "
    SELECT
        p.id AS id,
        p.payment_code AS main_code,
        p.amount AS amount,
        p.payment_date AS date,
        s.name AS supplier_name,
        'payment' AS type,
        p.status AS status,
        p.description AS description,
        p.reference_code AS ref_or_debt_code,
        p.payment_method AS method
    FROM payments p
    JOIN supplier s ON p.supplier_id = s.id
";

$sql_debt = "
    SELECT
        d.id AS id,
        d.debt_code AS main_code,
        d.remaining_amount AS amount,
        d.created_at AS date, -- Or d.due_date if preferred for sorting
        s.name AS supplier_name,
        'debt' AS type,
        d.status AS status,
        d.description AS description,
        d.debt_code AS ref_or_debt_code,
        NULL AS method -- Debts don't have a payment method
    FROM debts d
    JOIN supplier s ON d.supplier_id = s.id
";

$params = [];

$where_clauses = [];
$debt_where_clauses = [];

// Filter by supplier_id
if ($supplier_id_filter) {
    $where_clauses[] = "p.supplier_id = ?";
    $debt_where_clauses[] = "d.supplier_id = ?";
    $params[] = $supplier_id_filter;
    $params[] = $supplier_id_filter; // Add parameter for both parts of the UNION

    // Get supplier name for page title/breadcrumb if filtering
    try {
        $stmt_supplier = $db->prepare("SELECT name FROM supplier WHERE id = ?");
        $stmt_supplier->execute([$supplier_id_filter]);
        $supplier_info = $stmt_supplier->fetch(PDO::FETCH_ASSOC);
        if ($supplier_info) {
            $supplier_filter_name = htmlspecialchars($supplier_info['name']);
            $page_title = 'Lịch sử tài chính ' . $supplier_filter_name; // More specific title
        }
    } catch (PDOException $e) {
        // Log error but continue to display available data
        error_log('Error fetching supplier name: ' . $e->getMessage());
    }
}

// Add status filter for 'Đã thanh toán' (assuming status 'completed')
$where_clauses[] = "p.status = 'completed'";
$debt_where_clauses[] = "d.status = 'completed'";

// Combine WHERE clauses
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
if (!empty($debt_where_clauses)) {
    $sql_debt .= " WHERE " . implode(" AND ", $debt_where_clauses);
}

// Combine using UNION ALL and order by date
$final_sql = "(" . $sql . ") UNION ALL (" . $sql_debt . ") ORDER BY date DESC";

// Execute the query
try {
    $stmt = $db->prepare($final_sql);
    $stmt->execute($params); // Use the updated $params array
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('error', 'Lỗi truy vấn dữ liệu tài chính: ' . $e->getMessage());
}

// Xử lý gửi form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_payment'])) {
            // Xác thực và làm sạch cơ bản
            $supplier_id = filter_var($_POST['supplier_id'], FILTER_VALIDATE_INT);
            $amount = filter_var(str_replace(['.', ','], '', $_POST['amount']), FILTER_VALIDATE_FLOAT);
            $payment_method = $_POST['payment_method'];
            $payment_date = $_POST['payment_date'];
            $reference_code = htmlspecialchars($_POST['reference_code'] ?? '');
            $description = htmlspecialchars($_POST['description'] ?? '');
            $related_invoice_id = filter_var($_POST['invoice_id'] ?? null, FILTER_VALIDATE_INT);
            $related_debt_id = filter_var($_POST['debt_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$supplier_id || $amount <= 0 || empty($payment_method) || empty($payment_date)) {
                $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc (Nhà cung cấp, Số tiền, Phương thức, Ngày thanh toán).';
                setFlashMessage('error', $error);
            } else {
                try {
                    $db->beginTransaction();

                    // Tạo mã thanh toán duy nhất (ví dụ cơ bản, có thể cần logic mạnh mẽ hơn)
                    $payment_code = 'PAY' . strtoupper(uniqid());

                    $stmt = $db->prepare("INSERT INTO payments (payment_code, supplier_id, debt_id, amount, payment_method, payment_date, reference_code, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
                    // Use $related_debt_info['id'] if available, otherwise use null
                    $insert_debt_id = $related_debt_info['id'] ?? null;

                    // Log the debt_id value before executing
                    error_log("Attempting to insert payment with debt_id: " . ($insert_debt_id === null ? 'NULL' : $insert_debt_id));

                    $stmt->execute([$payment_code, $supplier_id, $insert_debt_id, $amount, $payment_method, $payment_date, $reference_code, $description]);

                    // Tùy chọn: Cập nhật trạng thái công nợ hoặc hóa đơn liên quan nếu cần
                    // Điều này sẽ yêu cầu logic bổ sung dựa trên yêu cầu của bạn

                    $db->commit();
                    setFlashMessage('success', 'Thêm thanh toán mới thành công!');


                } catch (PDOException $e) {
                    $db->rollBack();
                    $error = 'Lỗi khi thêm thanh toán: ' . $e->getMessage();
                    setFlashMessage('error', $error); // Đảm bảo flash message lỗi được set

                }
            }
             header('Location: payments.php'); // Chuyển hướng sau khi xử lý POST
             exit(); // Dừng script sau chuyển hướng
        } elseif (isset($_POST['update_item'])) {
            // Handle updating a payment or debt
            $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
            $item_type = $_POST['item_type'] ?? '';
            $supplier_id = filter_var($_POST['supplier_id'], FILTER_VALIDATE_INT);
            $description = htmlspecialchars($_POST['description'] ?? '');

            if (!$item_id || ($item_type !== 'payment' && $item_type !== 'debt')) {
                 setFlashMessage('error', 'Thông tin mục tài chính không hợp lệ.');
                 header('Location: payments.php');
                 exit();
            }

            $db->beginTransaction();

            if ($item_type === 'payment') {
                // Update payment
                $amount = filter_var(str_replace(['.', ','], '', $_POST['amount']), FILTER_VALIDATE_FLOAT);
                $payment_method = $_POST['payment_method'] ?? '';
                $payment_date = $_POST['payment_date'] ?? '';
                $reference_code = htmlspecialchars($_POST['reference_code'] ?? '');
                $status = $_POST['status'] ?? '';

                if ($amount <= 0 || empty($payment_method) || empty($payment_date) || empty($status)) {
                     $db->rollBack();
                     setFlashMessage('error', 'Vui lòng nhập đầy đủ thông tin bắt buộc cho thanh toán.');
                     header('Location: payments.php?action=edit&id=' . $item_id . '&type=payment');
                     exit();
                }

                $stmt = $db->prepare("UPDATE payments SET supplier_id = ?, amount = ?, payment_method = ?, payment_date = ?, reference_code = ?, description = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                 $stmt->execute([$supplier_id, $amount, $payment_method, $payment_date, $reference_code, $description, $status, $item_id]);

                setFlashMessage('success', 'Cập nhật thanh toán thành công!');

            } elseif ($item_type === 'debt') {
                // Update debt
                $total_amount = filter_var(str_replace(['.', ','], '', $_POST['total_amount']), FILTER_VALIDATE_FLOAT);
                $remaining_amount = filter_var(str_replace(['.', ','], '', $_POST['remaining_amount']), FILTER_VALIDATE_FLOAT);
                $due_date = $_POST['due_date'] ?? '';
                $status = $_POST['status'] ?? '';

                 if ($total_amount < 0 || $remaining_amount < 0 || empty($due_date) || empty($status) || $remaining_amount > $total_amount) {
                      $db->rollBack();
                     setFlashMessage('error', 'Thông tin công nợ không hợp lệ.');
                     header('Location: payments.php?action=edit&id=' . $item_id . '&type=debt');
                     exit();
                 }

                $stmt = $db->prepare("UPDATE debts SET supplier_id = ?, total_amount = ?, remaining_amount = ?, due_date = ?, description = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                 $stmt->execute([$supplier_id, $total_amount, $remaining_amount, $due_date, $description, $status, $item_id]);

                setFlashMessage('success', 'Cập nhật công nợ thành công!');
            }

            $db->commit();
            header('Location: payments.php?action=view&id=' . $item_id . '&type=' . $item_type);
            exit();

        }
        
    } catch (Exception $e) {
        $db->rollBack(); // Rollback in case of any exception
        setFlashMessage('error', 'Lỗi xử lý form: ' . $e->getMessage());
         // Redirect back to the form if it was an update attempt
         if (isset($item_id) && isset($item_type)) {
             header('Location: payments.php?action=edit&id=' . $item_id . '&type=' . $item_type);
             exit();
         } else {
            header('Location: payments.php');
            exit();
         }
    }
}

include 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$item_id = $_GET['id'] ?? null; // Use item_id instead of payment_id for clarity
$invoice_id = $_GET['invoice_id'] ?? null;
$debt_id_param = $_GET['debt_id'] ?? null; // Use a different variable name as $debt_id is used internally

$message = ''; // These variables are not needed anymore as flash messages are used
$error = ''; // These variables are not needed anymore as flash messages are used


// Lấy danh sách nhà cung cấp cho dropdown form
$suppliers = [];
try {
    $stmt = $db->query("SELECT id, name FROM supplier ORDER BY name");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Lỗi khi lấy danh sách nhà cung cấp: ' . $e->getMessage();
}

// Hiển thị các chế độ xem khác nhau dựa trên hành động
switch ($action) {
    case 'add':
        $page_title = 'Tạo thanh toán mới';
        // Điền trước nhà cung cấp hoặc công nợ nếu liên kết từ các trang khác
        $selected_supplier_id = null;
        $related_debt_info = null;

        if ($debt_id_param) {
            try {
                $stmt = $db->prepare("SELECT d.id, d.debt_code, d.supplier_id, s.name as supplier_name FROM debts d JOIN supplier s ON d.supplier_id = s.id WHERE d.id = ?");
                $stmt->execute([$debt_id_param]);
                $related_debt_info = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($related_debt_info) {
                    $selected_supplier_id = $related_debt_info['supplier_id'];
                    $debt_id_for_form = $related_debt_info['id']; // Set variable for form input
                } else {
                    $error = 'Không tìm thấy thông tin công nợ liên quan.';
                    $debt_id_param = null; // Xóa debt_id không hợp lệ
                }
            } catch (PDOException $e) {
                $error = 'Lỗi khi lấy thông tin công nợ: ' . $e->getMessage();
                $debt_id_param = null; // Xóa khi có lỗi
            }
        } else if ($invoice_id) {
             try {
                $stmt = $db->prepare("SELECT b.id, b.bill_code, b.supplier_id, s.name as supplier_name FROM bill b JOIN supplier s ON b.supplier_id = s.id WHERE b.id = ?");
                $stmt->execute([$invoice_id]);
                $related_invoice_info = $stmt->fetch(PDO::FETCH_ASSOC);
                 if ($related_invoice_info) {
                    $selected_supplier_id = $related_invoice_info['supplier_id'];
                     // Bạn có thể muốn tìm công nợ liên quan cho hóa đơn này nếu tồn tại
                     $stmt_debt = $db->prepare("SELECT id, debt_code FROM debts WHERE bill_id = ? LIMIT 1");
                     $stmt_debt->execute([$invoice_id]);
                     $related_debt_info = $stmt_debt->fetch(PDO::FETCH_ASSOC);
                     if($related_debt_info) {
                         $debt_id_for_form = $related_debt_info['id']; // Set variable for form input
                     }
                } else {
                    $error = 'Không tìm thấy thông tin hóa đơn liên quan.';
                    $invoice_id = null; // Xóa invoice_id không hợp lệ
                }
            } catch (PDOException $e) {
                $error = 'Lỗi khi lấy thông tin hóa đơn: ' . $e->getMessage();
                $invoice_id = null; // Xóa khi có lỗi
            }
        }


        ?>

        <div class="row">
            <div class="col-12">
                <?php echo createBreadcrumb([
                    ['title' => 'Dashboard', 'url' => 'dashboard.php'],
                    ['title' => 'Lịch sử tài chính', 'url' => 'payments.php'],
                    ['title' => 'Tạo mới']
                ]); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-plus"></i> Tạo thanh toán mới
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="payments.php">
                             <input type="hidden" name="add_payment" value="1">
                             <?php if (isset($debt_id_for_form)): ?>
                                <input type="hidden" name="debt_id" value="<?php echo $debt_id_for_form; ?>">
                                <div class="form-group">
                                    <label for="related_debt">Công nợ liên quan</label>
                                    <p class="form-control-static">#<?php echo htmlspecialchars($related_debt_info['debt_code'] ?? ''); ?> (Nhà cung cấp: <?php echo htmlspecialchars($related_debt_info['supplier_name'] ?? ''); ?>)</p>
                                </div>
                             <?php elseif ($invoice_id): ?>
                                <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                                 <?php if ($related_debt_info): // Hiển thị công nợ liên kết nếu tìm thấy ?>
                                 <input type="hidden" name="debt_id" value="<?php echo $related_debt_info['id'] ?? ''; ?>">
                                  <div class="form-group">
                                      <label for="related_debt">Công nợ liên quan từ Hóa đơn</label>
                                       <p class="form-control-static">#<?php echo htmlspecialchars($related_debt_info['debt_code'] ?? ''); ?> (Nhà cung cấp: <?php echo htmlspecialchars($related_invoice_info['supplier_name'] ?? ''); ?>)</p>
                                   </div>
                                 <?php else: // Nếu không có công nợ liên kết, chỉ hiển thị thông tin hóa đơn ?>
                                     <div class="form-group">
                                         <label for="related_invoice">Hóa đơn liên quan</label>
                                         <p class="form-control-static">#<?php echo htmlspecialchars($related_invoice_info['bill_code'] ?? ''); ?> (Nhà cung cấp: <?php echo htmlspecialchars($related_invoice_info['supplier_name'] ?? ''); ?>)</p>
                                     </div>
                                 <?php endif; ?>

                             <?php endif; ?>

                            <div class="form-group">
                                <label for="supplier_id">Nhà cung cấp <span class="text-danger">*</span></label>
                                <select class="form-control" id="supplier_id" name="supplier_id" required <?php echo (isset($debt_id_for_form) || $invoice_id) ? 'disabled' : ''; ?> >
                                    <option value="">-- Chọn nhà cung cấp --</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id']; ?>" <?php echo ($selected_supplier_id == $supplier['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($supplier['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                 <?php if (isset($debt_id_for_form) || $invoice_id): // Gửi supplier_id bị vô hiệu hóa dưới dạng trường ẩn ?>
                                     <input type="hidden" name="supplier_id" value="<?php echo $selected_supplier_id; ?>">
                                 <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="amount">Số tiền <span class="text-danger">*</span></label>
                                <input type="text" class="form-control currency-input" id="amount" name="amount" required>
                            </div>

                            <div class="form-group">
                                <label for="payment_method">Phương thức <span class="text-danger">*</span></label>
                                <select class="form-control" id="payment_method" name="payment_method" required>
                                    <option value="">-- Chọn phương thức --</option>
                                    <option value="Tiền mặt">Tiền mặt</option>
                                    <option value="Séc">Séc</option>
                                    <option value="Chuyển khoản">Chuyển khoản</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="payment_date">Ngày thanh toán <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" required>
                            </div>

                            <div class="form-group">
                                <label for="reference_code">Mã tham chiếu</label>
                                <input type="text" class="form-control" id="reference_code" name="reference_code">
                            </div>

                            <div class="form-group">
                                <label for="description">Mô tả</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Tạo thanh toán</button>
                            <a href="payments.php" class="btn btn-secondary">Hủy</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php
        break;

    case 'view':
        $page_title = 'Chi tiết mục tài chính'; // More general title
        $item = null;

        // Determine if we are viewing a payment or a debt based on URL params (need both type and id)
        $view_id = $_GET['id'] ?? null;
        $view_type = $_GET['type'] ?? null; 

        if ($view_id) { // Allow fetching if only ID is present, assuming it's a payment by default
            try {
                if ($view_type === 'payment' || $view_type === null) { // Default to payment if type is not specified
                     // Join with debts and bill to get related invoice info for payments
                     $stmt = $db->prepare("
                         SELECT 
                             p.*, 
                             s.name as supplier_name,
                             d.debt_code as related_debt_code,
                             b.bill_code as related_bill_code,
                             b.id as related_bill_id, -- Get bill ID to create link
                             p.created_at, -- Include created_at for display
                             p.updated_at -- Include updated_at for display
                         FROM payments p 
                         JOIN supplier s ON p.supplier_id = s.id
                         LEFT JOIN debts d ON p.debt_id = d.id -- Payments can be linked to debts
                         LEFT JOIN bill b ON d.bill_id = b.id -- Debts can be linked to bills
                         WHERE p.id = ?");
                    $stmt->execute([$view_id]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    $item['type'] = 'payment'; // Explicitly set type for display consistency

                } elseif ($view_type === 'debt') {
                    // Need to fetch debt details - assuming debts table has similar columns or we adapt display
                     // Join with bill to get related invoice info for debts
                     $stmt = $db->prepare("SELECT d.*, s.name as supplier_name, b.bill_code as related_bill_code, b.id as related_bill_id FROM debts d JOIN supplier s ON d.supplier_id = s.id LEFT JOIN bill b ON d.bill_id = b.id WHERE d.id = ?");
                    $stmt->execute([$view_id]);
                     $item = $stmt->fetch(PDO::FETCH_ASSOC);
                     if ($item) {
                         $item['type'] = 'debt'; // Explicitly set type
                     }
                }

                if (!$item) {
                    setFlashMessage('error', 'Không tìm thấy mục tài chính.');
                    // Redirect back to the list, preserving supplier filter if present
                     $redirect_url = 'payments.php';
                     if ($supplier_id_filter) {
                         $redirect_url .= '?supplier_id=' . $supplier_id_filter;
                     }
                     header('Location: ' . $redirect_url);
                    exit();
                }

            } catch (PDOException $e) {
                setFlashMessage('error', 'Lỗi khi lấy chi tiết mục tài chính: ' . $e->getMessage());
                 // Redirect back to the list, preserving supplier filter if present
                 $redirect_url = 'payments.php';
                 if ($supplier_id_filter) {
                     $redirect_url .= '?supplier_id=' . $supplier_id_filter;
                 }
                 header('Location: ' . $redirect_url);
                exit();
            }
        } else {
            setFlashMessage('error', 'Không có thông tin mục tài chính được cung cấp.');
             // Redirect back to the list, preserving supplier filter if present
             $redirect_url = 'payments.php';
             if ($supplier_id_filter) {
                 $redirect_url .= '?supplier_id=' . $supplier_id_filter;
             }
             header('Location: ' . $redirect_url);
            exit();
        }

        ?>

        <div class="row">
            <div class="col-12">
                <?php echo createBreadcrumb([
                    ['title' => 'Dashboard', 'url' => 'dashboard.php'],
                    ['title' => 'Lịch sử tài chính', 'url' => 'payments.php' . ($supplier_id_filter ? '?supplier_id=' . $supplier_id_filter : '')],
                    ['title' => 'Chi tiết']
                ]); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-info-circle"></i> Chi tiết: <?php echo htmlspecialchars($item['main_code'] ?? $item['debt_code'] ?? ''); ?> (<?php echo $item['type'] === 'payment' ? 'Thanh toán' : 'Công nợ'; ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                         <?php echo getFlashMessage(); // Hiển thị flash message nếu có ?>
                        <dl class="row">
                            <dt class="col-sm-3">Mã:</dt>
                            <dd class="col-sm-9"><?php echo htmlspecialchars($item['main_code'] ?? $item['debt_code'] ?? ''); ?></dd>

                            <dt class="col-sm-3">Loại:</dt>
                            <dd class="col-sm-9"><?php echo $item['type'] === 'payment' ? 'Thanh toán' : 'Công nợ'; ?></dd>

                            <dt class="col-sm-3">Nhà cung cấp:</dt>
                            <dd class="col-sm-9"><?php echo htmlspecialchars($item['supplier_name'] ?? ''); ?></dd>

                             <?php if ($item['type'] === 'payment'): ?>
                                <dt class="col-sm-3">Số tiền thanh toán:</dt>
                                <dd class="col-sm-9"><?php echo formatCurrency($item['amount'] ?? 0); ?></dd>

                                 <?php if (!empty($item['method'])): // Conditionally display method ?>
                                     <dt class="col-sm-3">Phương thức:</dt>
                                     <dd class="col-sm-9"><?php echo htmlspecialchars($item['method']); ?></dd>
                                 <?php endif; ?>

                                 <?php if (!empty($item['date'])): // Conditionally display date ?>
                                     <dt class="col-sm-3">Ngày thanh toán:</dt>
                                     <dd class="col-sm-9"><?php echo formatDate($item['date']); ?></dd>
                                 <?php endif; ?>

                                 <?php if (!empty($item['ref_or_debt_code'])): // Conditionally display reference code ?>
                                     <dt class="col-sm-3">Mã tham chiếu:</dt>
                                     <dd class="col-sm-9"><?php echo htmlspecialchars($item['ref_or_debt_code']); ?></dd>
                                 <?php endif; ?>

                                 <?php if (!empty($item['related_bill_code'])): // Display related bill info ?>
                                 <dt class="col-sm-3">Hóa đơn liên quan:</dt>
                                 <dd class="col-sm-9">
                                     <a href="invoices.php?action=view&id=<?php echo $item['related_bill_id'] ?? ''; ?>">
                                         #<?php echo htmlspecialchars($item['related_bill_code'] ?? ''); ?>
                                     </a>
                                 </dd>
                                 <?php endif; ?>

                                <dt class="col-sm-3">Trạng thái:</dt>
                                <dd class="col-sm-9">
                                    <?php 
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($item['status'] ?? '') {
                                            case 'completed': 
                                                $status_class = 'badge-success';
                                                $status_text = 'Đã thanh toán';
                                                break;
                                            case 'pending': 
                                                $status_class = 'badge-warning';
                                                $status_text = 'Chờ xử lý';
                                                break;
                                            case 'failed': 
                                                $status_class = 'badge-danger';
                                                $status_text = 'Thất bại';
                                                break;
                                            default:
                                                 $status_class = 'badge-secondary';
                                                 $status_text = htmlspecialchars($item['status'] ?? '');
                                                 break;
                                        }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_text); ?></span>
                                </dd>
                            <?php elseif ($item['type'] === 'debt'): ?>
                                <dt class="col-sm-3">Số tiền công nợ còn lại:</dt>
                                <dd class="col-sm-9"><?php echo formatCurrency($item['amount'] ?? 0); ?></dd>

                                <dt class="col-sm-3">Ngày tạo công nợ:</dt>
                                <dd class="col-sm-9"><?php echo formatDate($item['date'] ?? ''); ?></dd>

                                <dt class="col-sm-3">Mã công nợ:</dt>
                                <dd class="col-sm-9"><?php echo htmlspecialchars($item['main_code'] ?? ''); ?></dd>

                                 <?php if (!empty($item['related_bill_code'])): // Display related bill info for debt ?>
                                 <dt class="col-sm-3">Hóa đơn liên quan:</dt>
                                 <dd class="col-sm-9">
                                     <a href="invoices.php?action=view&id=<?php echo $item['related_bill_id'] ?? ''; ?>">
                                         #<?php echo htmlspecialchars($item['related_bill_code'] ?? ''); ?>
                                     </a>
                                 </dd>
                                 <?php endif; ?>

                                <dt class="col-sm-3">Trạng thái:</dt>
                                <dd class="col-sm-9">
                                     <?php
                                        $badge_class = '';
                                        $status_text = '';
                                        switch ($item['status'] ?? '') {
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
                                            default:
                                                 $badge_class = 'badge-secondary';
                                                $status_text = htmlspecialchars($item['status'] ?? ''); // Display raw status if unknown
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                </dd>
                            <?php endif; ?>

                            <?php if (!empty($item['description'])): ?>
                                <dt class="col-sm-3">Mô tả:</dt>
                                <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($item['description'] ?? '')); ?></dd>
                            <?php endif; ?>

                             <?php /* Assuming created_at and updated_at are selected in the UNION query */ ?>
                             <?php if (isset($item['created_at'])): ?>
                                 <dt class="col-sm-3">Ngày tạo:</dt>
                                 <dd class="col-sm-9"><?php echo formatDate($item['created_at'] ?? ''); ?></dd>
                             <?php endif; ?>

                              <?php if (isset($item['updated_at'])): ?>
                                 <dt class="col-sm-3">Cập nhật cuối:</dt>
                                 <dd class="col-sm-9"><?php echo formatDate($item['updated_at'] ?? ''); ?></dd>
                              <?php endif; ?>

                        </dl>

                         <?php 
                             $back_link = 'payments.php';
                             if ($supplier_id_filter) {
                                 $back_link .= '?supplier_id=' . $supplier_id_filter;
                             }
                         ?>
                        <a href="<?php echo $back_link; ?>" class="btn btn-secondary">Quay lại danh sách</a>
                         <?php /* Có thể thêm nút sửa hoặc xóa ở đây */ ?>

                    </div>
                </div>
            </div>
        </div>

        <?php
        break;

    case 'delete':
        // No code needed here anymore
        break;

    case 'edit':
        $page_title = 'Chỉnh sửa mục tài chính';
        $item_to_edit = null;
        $edit_id = $_GET['id'] ?? null;
        $edit_type = $_GET['type'] ?? null;

        if ($edit_id && ($edit_type === 'payment' || $edit_type === 'debt')) {
            try {
                if ($edit_type === 'payment') {
                    $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
                    $stmt->execute([$edit_id]);
                    $item_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
                    $item_to_edit['type'] = 'payment';
                } elseif ($edit_type === 'debt') {
                    $stmt = $db->prepare("SELECT * FROM debts WHERE id = ?");
                    $stmt->execute([$edit_id]);
                    $item_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
                    $item_to_edit['type'] = 'debt';
                }

                if (!$item_to_edit) {
                    setFlashMessage('error', 'Không tìm thấy mục tài chính để chỉnh sửa.');
                    header('Location: payments.php');
                    exit();
                }

                 // Fetch supplier name for the item being edited
                 $stmt_supplier = $db->prepare("SELECT name FROM supplier WHERE id = ?");
                 $stmt_supplier->execute([$item_to_edit['supplier_id']]);
                 $item_to_edit['supplier_name'] = $stmt_supplier->fetchColumn();

            } catch (PDOException $e) {
                setFlashMessage('error', 'Lỗi khi lấy thông tin mục tài chính: ' . $e->getMessage());
                header('Location: payments.php');
                exit();
            }
        } else {
            setFlashMessage('error', 'Không có thông tin mục tài chính được cung cấp để chỉnh sửa.');
            header('Location: payments.php');
            exit();
        }

        ?>

        <div class="row">
            <div class="col-12">
                <?php echo createBreadcrumb([
                    ['title' => 'Dashboard', 'url' => 'dashboard.php'],
                    ['title' => 'Lịch sử tài chính', 'url' => 'payments.php'],
                    ['title' => 'Chỉnh sửa']
                ]); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-edit"></i> Chỉnh sửa <?php echo $item_to_edit['type'] === 'payment' ? 'Thanh toán' : 'Công nợ'; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                         <?php echo getFlashMessage(); // Hiển thị flash message nếu có ?>

                        <form method="POST" action="payments.php">
                            <input type="hidden" name="update_item" value="1">
                            <input type="hidden" name="item_id" value="<?php echo $item_to_edit['id']; ?>">
                            <input type="hidden" name="item_type" value="<?php echo $item_to_edit['type']; ?>">

                            <div class="form-group">
                                <label>Nhà cung cấp:</label>
                                <p class="form-control-static"><?php echo htmlspecialchars($item_to_edit['supplier_name'] ?? ''); ?></p>
                                 <input type="hidden" name="supplier_id" value="<?php echo $item_to_edit['supplier_id'] ?? ''; ?>">
                            </div>

                            <?php if ($item_to_edit['type'] === 'payment'): ?>
                                <div class="form-group">
                                    <label for="amount">Số tiền <span class="text-danger">*</span></label>
                                     <input type="text" class="form-control currency-input" id="amount" name="amount" 
                                            value="<?php echo number_format($item_to_edit['amount'] ?? 0, 0, ',', '.'); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="payment_method">Phương thức <span class="text-danger">*</span></label>
                                    <select class="form-control" id="payment_method" name="payment_method" required>
                                        <option value="Tiền mặt" <?php echo ($item_to_edit['payment_method'] ?? '') === 'Tiền mặt' ? 'selected' : ''; ?>>Tiền mặt</option>
                                        <option value="Séc" <?php echo ($item_to_edit['payment_method'] ?? '') === 'Séc' ? 'selected' : ''; ?>>Séc</option>
                                        <option value="Chuyển khoản" <?php echo ($item_to_edit['payment_method'] ?? '') === 'Chuyển khoản' ? 'selected' : ''; ?>>Chuyển khoản</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="payment_date">Ngày thanh toán <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo $item_to_edit['payment_date'] ?? ''; ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="reference_code">Mã tham chiếu</label>
                                     <input type="text" class="form-control" id="reference_code" name="reference_code"
                                            value="<?php echo htmlspecialchars($item_to_edit['reference_code'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="status">Trạng thái <span class="text-danger">*</span></label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="completed" <?php echo ($item_to_edit['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Đã thanh toán</option>
                                        <option value="pending" <?php echo ($item_to_edit['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Chờ xử lý</option>
                                        <option value="failed" <?php echo ($item_to_edit['status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Thất bại</option>
                                    </select>
                                </div>

                             <?php elseif ($item_to_edit['type'] === 'debt'): ?>
                                 <div class="form-group">
                                     <label>Mã công nợ:</label>
                                      <p class="form-control-static"><?php echo htmlspecialchars($item_to_edit['debt_code'] ?? ''); ?></p>
                                 </div>
                                 <div class="form-group">
                                     <label for="total_amount">Tổng tiền công nợ <span class="text-danger">*</span></label>
                                      <input type="text" class="form-control currency-input" id="total_amount" name="total_amount"
                                             value="<?php echo number_format($item_to_edit['total_amount'] ?? 0, 0, ',', '.'); ?>" required>
                                 </div>
                                 <div class="form-group">
                                     <label for="remaining_amount">Số tiền còn lại <span class="text-danger">*</span></label>
                                      <input type="text" class="form-control currency-input" id="remaining_amount" name="remaining_amount"
                                             value="<?php echo number_format($item_to_edit['remaining_amount'] ?? 0, 0, ',', '.'); ?>" required>
                                 </div>
                                 <div class="form-group">
                                     <label for="due_date">Ngày đến hạn <span class="text-danger">*</span></label>
                                     <input type="date" class="form-control" id="due_date" name="due_date" 
                                            value="<?php echo $item_to_edit['due_date'] ?? ''; ?>" required>
                                 </div>
                                  <div class="form-group">
                                     <label for="status">Trạng thái <span class="text-danger">*</span></label>
                                      <select class="form-control" id="status" name="status" required>
                                         <option value="current" <?php echo ($item_to_edit['status'] ?? '') === 'current' ? 'selected' : ''; ?>>Chờ thanh toán</option>
                                          <option value="paid" <?php echo ($item_to_edit['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Đã thanh toán</option>
                                          <option value="overdue" <?php echo ($item_to_edit['status'] ?? '') === 'overdue' ? 'selected' : ''; ?>>Quá hạn</option>
                                      </select>
                                  </div>
                             <?php endif; ?>

                            <div class="form-group">
                                <label for="description">Mô tả</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($item_to_edit['description'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Cập nhật</button>
                             <a href="payments.php?action=view&id=<?php echo $item_to_edit['id'] ?? ''; ?>&type=<?php echo $item_to_edit['type'] ?? ''; ?>" class="btn btn-secondary">Hủy</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php
        break;

    case 'list':
    default:
        // Mã hiển thị danh sách hiện có vẫn ở đây
        ?>

<div class="row">
    <div class="col-12">
        <?php 
             $breadcrumb = [
                ['title' => 'Dashboard', 'url' => 'dashboard.php'],
                ['title' => 'Lịch sử tài chính']
            ];
            if ($supplier_filter_name) {
                 // Add supplier name to breadcrumb if filtering
                 $breadcrumb[1]['url'] = 'payments.php'; // Link back to general payments list
                 $breadcrumb[] = ['title' => 'Của nhà cung cấp ' . $supplier_filter_name];
            }
             echo createBreadcrumb($breadcrumb);
        ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-credit-card"></i> Lịch sử tài chính <?php echo $supplier_filter_name ? 'của ' . $supplier_filter_name : ''; ?>
                        </h6>
                    </div>
                    <div class="col-auto">
                         <?php if (!$supplier_id_filter): // Only show Add button on general list ?>
                            <a href="payments.php?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Tạo thanh toán
                            </a>
                         <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <?php echo getFlashMessage(); // Hiển thị flash message nếu có ?>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Mã</th>
                                <th>Nhà cung cấp</th>
                                <th>Số tiền</th>
                                <th>Ngày</th>
                                <th>Chi tiết</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        Không có dữ liệu
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['main_code'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['supplier_name'] ?? ''); ?></td>
                                        <td>
                                            <?php if ($item['type'] === 'payment'): ?>
                                                 <?php echo formatCurrency($item['amount'] ?? 0); // Số tiền thanh toán (dương) ?>
                                            <?php elseif ($item['type'] === 'debt'): ?>
                                                 <span class="text-danger"><?php echo formatCurrency($item['amount'] ?? 0); // Số tiền công nợ (có thể muốn hiển thị âm hoặc màu đỏ) ?></span>
                                            <?php else: ?>
                                                <?php echo formatCurrency($item['amount'] ?? 0); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($item['date'] ?? ''); ?></td>
                                        <td>
                                             <?php if ($item['type'] === 'payment'): ?>
                                                Phương thức: <?php echo htmlspecialchars($item['method'] ?? ''); ?><br>
                                                Mã tham chiếu: <?php echo htmlspecialchars($item['ref_or_debt_code'] ?? ''); ?>
                                             <?php elseif ($item['type'] === 'debt'): ?>
                                                Mô tả: <?php echo nl2br(htmlspecialchars($item['description'] ?? '')); ?>
                                             <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $status_class = '';
                                                $status_text = '';
                                                if ($item['type'] === 'payment') {
                                                    switch ($item['status'] ?? '') {
                                                        case 'completed': 
                                                            $status_class = 'badge-success';
                                                            $status_text = 'Đã thanh toán';
                                                            break;
                                                        case 'pending': 
                                                            $status_class = 'badge-warning';
                                                            $status_text = 'Chờ xử lý';
                                                            break;
                                                        case 'failed': 
                                                            $status_class = 'badge-danger';
                                                            $status_text = 'Thất bại';
                                                            break;
                                                        default:
                                                             $status_class = 'badge-secondary';
                                                             $status_text = htmlspecialchars($item['status'] ?? '');
                                                             break;
                                                    }
                                                } elseif ($item['type'] === 'debt') {
                                                     switch ($item['status'] ?? '') {
                                                        case 'paid':
                                                            $status_class = 'badge-success';
                                                            $status_text = 'Đã thanh toán';
                                                            break;
                                                        case 'current':
                                                            $status_class = 'badge-warning';
                                                            $status_text = 'Chờ thanh toán';
                                                            break;
                                                        case 'overdue':
                                                            $status_class = 'badge-danger';
                                                            $status_text = 'Quá hạn';
                                                            break;
                                                        default:
                                                             $badge_class = 'badge-secondary';
                                                            $status_text = htmlspecialchars($item['status'] ?? ''); // Display raw status if unknown
                                                            break;
                                                    }
                                                } else {
                                                    $status_class = 'badge-secondary';
                                                    $status_text = htmlspecialchars($item['status'] ?? '');
                                                }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                 <?php if ($item['type'] === 'payment'): ?>
                                                     <a href="payments.php?action=view&id=<?php echo $item['id'] ?? ''; ?>&type=payment" 
                                                        class="btn btn-info btn-sm" title="Xem chi tiết">
                                                        <i class="fas fa-eye"></i>
                                                     </a>
                                                     <a href="payments.php?action=edit&id=<?php echo $item['id'] ?? ''; ?>&type=payment" 
                                                        class="btn btn-warning btn-sm" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                     </a>
                                                     <a href="payments.php?action=delete&id=<?php echo $item['id'] ?? ''; ?>&type=payment" 
                                                        class="btn btn-danger btn-sm btn-delete" title="Xóa">
                                                        <i class="fas fa-trash"></i>
                                                     </a>
                                                 <?php elseif ($item['type'] === 'debt'): ?>
                                                      <a href="payments.php?action=view&id=<?php echo $item['id'] ?? ''; ?>&type=debt" 
                                                        class="btn btn-info btn-sm" title="Xem chi tiết">
                                                        <i class="fas fa-eye"></i>
                                                     </a>
                                                     <a href="payments.php?action=edit&id=<?php echo $item['id'] ?? ''; ?>&type=debt" 
                                                        class="btn btn-warning btn-sm" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                     </a>
                                                     <!-- Debt items might not have a direct 'delete' action in the same way as payments -->
                                                     <!-- You might add a 'Mark as Paid' or similar action here if appropriate -->
                                                     <a href="payments.php?action=delete&id=<?php echo $item['id'] ?? ''; ?>&type=debt" 
                                                        class="btn btn-danger btn-sm btn-delete" title="Xóa">
                                                        <i class="fas fa-trash"></i>
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

                <?php 
                     // Pagination (assuming $pagination variable is available from list query)
                     if (isset($pagination) && $pagination['total_pages'] > 1): 
                ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <li class="page-item <?php echo ($i === $pagination['current_page']) ? 'active' : ''; ?>">
                                <a class="page-link" href="?action=list&page=<?php echo $i; ?><?php echo $supplier_id_filter ? '&supplier_id=' . $supplier_id_filter : ''; ?>">
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

<?php
        break;
}
?>

<?php include 'includes/footer.php'; ?> 