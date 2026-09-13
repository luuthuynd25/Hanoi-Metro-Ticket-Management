<?php
include "connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $full_name = $_POST['full_name'];
    $gender = $_POST['gender'];

    $sql = "INSERT INTO register (email, password_account, full_name, gender) VALUES (?, ?, ?, ?)";
    $stmt = $connetor->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ssss", $email, $password, $full_name, $gender);
        
        if ($stmt->execute()) {
            $success_message = "Đăng ký thành công!";
        } else {
            $error_message = "Lỗi: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        $error_message = "Lỗi trong quá trình chuẩn bị câu lệnh.";
    }
}
$connetor->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Tài Khoản</title>
    <link rel="stylesheet" href="register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Đăng Ký Tài Khoản</h2>
            <p>Vui lòng điền thông tin để tạo tài khoản mới</p>
        </div>
        
        <div class="form-container">
            <?php if(isset($success_message)): ?>
                <div class="message success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error_message)): ?>
                <div class="message error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Nhập địa chỉ email" required>
                    <i class="fas fa-envelope icon"></i>
                </div>
                
                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" required>
                    <i class="fas fa-lock icon"></i>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Họ và tên</label>
                    <input type="text" id="full_name" name="full_name" placeholder="Nhập họ và tên" required>
                    <i class="fas fa-user icon"></i>
                </div>
                
                <div class="form-group">
                    <label for="gender">Giới tính</label>
                    <select id="gender" name="gender" required>
                        <option value="" disabled selected>Chọn giới tính</option>
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                        <option value="Khác">Khác</option>
                    </select>
                    <i class="fas fa-venus-mars icon"></i>
                </div>
                
                <button type="submit" class="btn-register">Đăng Ký</button>
                
                <div class="form-footer">
                    <p>Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>