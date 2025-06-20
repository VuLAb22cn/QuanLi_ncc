<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

checkLogin();

$page_title = 'Hồ sơ cá nhân';
$db = getDB();

$user_id = $_SESSION['user_id'] ?? 0;
$user_info = null;
$action = $_GET['action'] ?? 'view'; // Default action is view

// Fetch user info initially (needed for both view and edit)
if ($user_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, username, name, email, tel, role, password FROM user WHERE id = ?"); // Fetch password here
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Lỗi truy vấn thông tin người dùng: ' . $e->getMessage());
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_info) {
    
    // Handle Update Profile POST
    if ($action === 'edit') {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $tel = sanitize($_POST['tel'] ?? '');
        
        $errors = [];
        if (empty($name)) {
            $errors[] = 'Họ tên không được để trống.';
        }
        if (!empty($email) && !validateEmail($email)) {
            $errors[] = 'Email không hợp lệ.';
        }
        if (!empty($tel) && !validatePhone($tel)) {
            $errors[] = 'Số điện thoại không hợp lệ.';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("UPDATE user SET name = ?, email = ?, tel = ? WHERE id = ?");
                $stmt->execute([$name, $email, $tel, $user_id]);
                
                // Update session name if name was changed
                $_SESSION['user_name'] = $name; 

                setFlashMessage('success', 'Cập nhật hồ sơ thành công!');
                header('Location: profile.php'); // Redirect to view mode after update
                exit();
            } catch (PDOException $e) {
                setFlashMessage('error', 'Lỗi cập nhật hồ sơ: ' . $e->getMessage());
                header('Location: profile.php?action=edit'); 
                exit();
            }
        } else {
            setFlashMessage('error', implode('<br>', $errors));
             header('Location: profile.php?action=edit');
            exit();
        }
    }
    
    // Handle Change Password POST
    if ($action === 'change_password' && isset($_POST['change_password_submit'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        // Verify the current password
        if (!password_verify($current_password, $user_info['password'])) {
            $errors[] = 'Mật khẩu hiện tại không đúng.';
        }
        
        // Check if new password and confirm password match
        if ($new_password !== $confirm_password) {
            $errors[] = 'Mật khẩu mới và xác nhận mật khẩu không khớp.';
        }
        
        // Optional: Add complexity checks for the new password (e.g., minimum length)
        if (strlen($new_password) < 6) { // Example: minimum 6 characters
            $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        }
        
        if (empty($errors)) {
            try {
                // Hash the new password securely
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update the password in the database
                $stmt = $db->prepare("UPDATE user SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                setFlashMessage('success', 'Đổi mật khẩu thành công! Vui lòng đăng nhập lại.');
                header('Location: logout.php'); // Logout after password change
                exit();
                
            } catch (PDOException $e) {
                setFlashMessage('error', 'Lỗi khi đổi mật khẩu: ' . $e->getMessage());
                header('Location: profile.php'); 
                exit();
            }
        } else {
            setFlashMessage('error', implode('<br>', $errors));
            header('Location: profile.php'); 
            exit();
        }
    }
}

// Set page title based on action
if ($action === 'edit') {
    $page_title = 'Chỉnh sửa hồ sơ';
}

include 'includes/header.php';

?>

<div class="row">
    <div class="col-12">
        <?php echo createBreadcrumb([
            ['title' => 'Dashboard', 'url' => 'dashboard.php'],
            ['title' => $page_title] // Use dynamic page title
        ]); ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-user"></i> <?php echo $page_title; ?>
                </h6>
            </div>
            <div class="card-body">
                <?php echo getFlashMessage(); // Hiển thị flash message nếu có ?>

                <?php if ($user_info): ?>
                    <?php if ($action === 'view'): ?>
                        <!-- Display Profile Info -->
                        <table class="table table-borderless">
                            <tr><th>Tên đăng nhập:</th><td><?php echo htmlspecialchars($user_info['username']); ?></td></tr>
                            <tr><th>Họ tên:</th><td><?php echo htmlspecialchars($user_info['name']); ?></td></tr>
                            <tr><th>Email:</th><td><?php echo htmlspecialchars($user_info['email']); ?></td></tr>
                            <tr><th>Điện thoại:</th><td><?php echo htmlspecialchars($user_info['tel']); ?></td></tr>
                            <tr><th>Vai trò:</th><td><?php echo htmlspecialchars($user_info['role']); ?></td></tr>
                        </table>
                        
                        <div class="mt-4 text-center">
                             <a href="profile.php?action=edit" class="btn btn-warning" title="Chỉnh sửa hồ sơ">
                                <i class="fas fa-edit"></i> Chỉnh sửa hồ sơ
                            </a>
                        </div>
                    <?php elseif ($action === 'edit'): ?>
                        <!-- Display Edit Form -->
                        <form method="POST" action="profile.php?action=edit">
                            <div class="form-group">
                                <label for="username">Tên đăng nhập:</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user_info['username']); ?>" disabled>
                                <small class="form-text text-muted">Tên đăng nhập không thể thay đổi.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="name">Họ tên <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $user_info['name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $user_info['email']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="tel">Điện thoại</label>
                                <input type="text" class="form-control" id="tel" name="tel" value="<?php echo htmlspecialchars($_POST['tel'] ?? $user_info['tel']); ?>">
                            </div>

                             <div class="form-group">
                                <label for="role">Vai trò:</label>
                                <input type="text" class="form-control" id="role" value="<?php echo htmlspecialchars($user_info['role']); ?>" disabled>
                                <small class="form-text text-muted">Vai trò không thể thay đổi tại đây.</small>
                            </div>
                            
                            <div class="mt-4 text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Lưu thay đổi
                                </button>
                                <a href="profile.php" class="btn btn-secondary ml-2">
                                    <i class="fas fa-times"></i> Hủy
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle"></i> Không tìm thấy thông tin người dùng.
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

   
</div>

<?php include 'includes/footer.php'; ?> 