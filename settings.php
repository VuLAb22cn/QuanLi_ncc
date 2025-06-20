<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

checkLogin();
checkAdmin(); // Giả định trang cài đặt chỉ dành cho admin

$page_title = 'Cài đặt hệ thống';
$db = getDB();

// Logic để xử lý cài đặt (ví dụ: lấy và lưu cài đặt từ database)
$settings = [
    'site_name' => 'Hệ thống quản lý nhà cung cấp',
    'records_per_page' => 10
    // Thêm các cài đặt khác tại đây
];

// Xử lý lưu cài đặt nếu có POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Đây là nơi xử lý lưu dữ liệu cài đặt vào database hoặc file cấu hình
    // Ví dụ: $site_name = sanitize($_POST['site_name']);
    // Cần thêm logic database để lưu trữ cài đặt
    setFlashMessage('success', 'Cài đặt đã được lưu (chức năng lưu chưa hoàn thiện).');
    // header('Location: settings.php');
    // exit;
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <?php echo createBreadcrumb([
            ['title' => 'Dashboard', 'url' => 'dashboard.php'],
            ['title' => 'Cài đặt hệ thống']
        ]); ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-cog"></i> Cài đặt hệ thống
                </h6>
            </div>
            <div class="card-body">
                 <?php echo getFlashMessage(); // Hiển thị flash message nếu có ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="site_name">Tên hệ thống:</label>
                        <input type="text" class="form-control" id="site_name" name="site_name" 
                               value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="records_per_page">Số bản ghi mỗi trang:</label>
                        <input type="number" class="form-control" id="records_per_page" name="records_per_page" 
                               value="<?php echo htmlspecialchars($settings['records_per_page']); ?>" min="1">
                    </div>
                    
                    <?php /* Thêm các trường cài đặt khác tại đây */ ?>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu cài đặt
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 