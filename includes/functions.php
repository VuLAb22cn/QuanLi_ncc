<?php
/**
 * Các hàm tiện ích chung
 */

/**
 * Làm sạch dữ liệu đầu vào
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate số điện thoại
 */
function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^[0-9]{10,11}$/', $phone);
}

/**
 * Tạo mã tự động
 */
function generateCode($prefix, $table, $column, $db) {
    try {
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING($column, LENGTH(?) + 1) AS UNSIGNED)) as max_num FROM $table WHERE $column LIKE CONCAT(?, '%')");
        $stmt->execute([$prefix, $prefix]);
        $result = $stmt->fetch();
        
        $next_num = ($result['max_num'] ?? 0) + 1;
        return $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        return $prefix . '0001';
    }
}

/**
 * Format tiền tệ
 */
function formatCurrency($amount) {
    if ($amount === null || $amount === '') {
        return '0 VNĐ';
    }
    return number_format((float)$amount, 0, ',', '.') . ' VNĐ';
}

/**
 * Format ngày tháng
 */
function formatDate($date) {
    if (empty($date)) return '';
    return date('d/m/Y', strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime) {
    if (empty($datetime)) return '';
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * Phân trang
 */
function paginate($total_records, $records_per_page, $current_page) {
    $total_pages = ceil($total_records / $records_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => max(0, $offset),
        'limit' => $records_per_page,
        'total_records' => $total_records
    ];
}

/**
 * Tạo breadcrumb
 */
function createBreadcrumb($items) {
    return ''; // Return empty string to disable breadcrumb
}

/**
 * Upload file
 */
function uploadFile($file, $upload_dir = 'uploads/') {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid parameters.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }

    if ($file['size'] > 5000000) { // 5MB
        throw new RuntimeException('Exceeded filesize limit.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $allowed_types = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    $ext = array_search($finfo->file($file['tmp_name']), $allowed_types, true);
    if (false === $ext) {
        throw new RuntimeException('Invalid file format.');
    }

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = sprintf('%s.%s', sha1_file($file['tmp_name']), $ext);
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return $filepath;
}

/**
 * Gửi email thông báo
 */
function sendEmail($to, $subject, $message, $from = 'noreply@company.com') {
    $headers = "From: $from\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Tính số ngày quá hạn
 */
function getDaysOverdue($due_date) {
    if (empty($due_date)) return 0;
    
    $today = new DateTime();
    $due = new DateTime($due_date);
    $diff = $today->diff($due);
    
    if ($today > $due) {
        return $diff->days;
    }
    return 0;
}
?>
