<?php
// Xử lý page layout
$page_layout = isset($_GET['page_layout']) ? $_GET['page_layout'] : 'trangchu';

// Danh sách các trang hợp lệ và file tương ứng
$pages = [
    'trangchu' => 'home.php',
    'quanlygatau' => 'quanlygatau.php',
    'quanlydoantau' => 'quanlydoantau.php',
    'quanlylichtrinh' => 'quanlylichtrinh.php',
    'quanlyve' => 'quanlyve.php',
    'banve' => 'banve.php',
    'quanlykhachhang' => 'quanlykhachhang.php',
    'baocaodoanhthu' => 'baocaodoanhthu.php'
];

// Kiểm tra và lấy file nội dung tương ứng
$content_file = isset($pages[$page_layout]) ? $pages[$page_layout] : 'home.php';

// Hàm để lấy tiêu đề trang
function getPageTitle($layout) {
    $titles = [
        'trangchu' => 'Trang Chủ',
        'quanlygatau' => 'Quản Lý Ga Tàu',
        'quanlydoantau' => 'Quản Lý Đoàn Tàu',
        'quanlylichtrinh' => 'Quản Lý Lịch Trình',
        'quanlyve' => 'Quản Lý Vé',
        'banve' => 'Bán Vé',
        'quanlykhachhang' => 'Quản Lý Khách Hàng',
        'baocaodoanhthu' => 'Báo Cáo Doanh Thu'
    ];
    return isset($titles[$layout]) ? $titles[$layout] : 'Trang Chủ';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ Thống Quản Lý Vé Tàu - <?php echo getPageTitle($page_layout); ?></title>
    <link rel="stylesheet" href="admin.css">
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <i class="fas fa-train logo-icon"></i>
                <span class="logo-text">Metro Nhổn - Ga HN</span>
            </div>
            <nav class="nav-menu">
                <ul>
                    <li class="nav-item <?php echo $page_layout == 'trangchu' ? 'active' : ''; ?>">
                        <a href="?page_layout=trangchu">
                            <i class="fas fa-home"></i>
                            <span>Trang Chủ</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout == 'quanlygatau' ? 'active' : ''; ?>">
                        <a href="?page_layout=quanlygatau">
                            <i class="fas fa-train"></i>
                            <span>Quản Lý Ga Tàu</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout == 'quanlydoantau' ? 'active' : ''; ?>">
                        <a href="?page_layout=quanlydoantau">
                            <i class="fas fa-subway"></i>
                            <span>Quản Lý Đoàn Tàu</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout == 'quanlylichtrinh' ? 'active' : ''; ?>">
                        <a href="?page_layout=quanlylichtrinh">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Quản Lý Lịch Trình</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout == 'quanlyve' ? 'active' : ''; ?>">
                        <a href="?page_layout=quanlyve">
                            <i class="fas fa-ticket-alt"></i>
                            <span>Quản Lý Vé</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout == 'banve' ? 'active' : ''; ?>">
                        <a href="?page_layout=banve">
                            <i class="fas fa-cash-register"></i>
                            <span>Bán Vé</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout == 'quanlykhachhang' ? 'active' : ''; ?>">
                        <a href="?page_layout=quanlykhachhang">
                            <i class="fas fa-users"></i>
                            <span>Quản Lý Khách Hàng</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout == 'baocaodoanhthu' ? 'active' : ''; ?>">
                        <a href="?page_layout=baocaodoanhthu">
                            <i class="fas fa-chart-line"></i>
                            <span>Báo Cáo Doanh Thu</span>
                        </a>
                    </li>
                    <li class="nav-item logout">
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Đăng Xuất</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title">
                    <h1><?php echo getPageTitle($page_layout); ?></h1>
                </div>
                <div class="topbar-user">
                    <i class="fas fa-user-circle"></i>
                    <span>Admin</span>
                </div>
            </header>
            <section class="content">
                <?php 
                if (file_exists($content_file)) {
                    include $content_file;
                } else {
                    echo "<div class='error-message'>Không tìm thấy trang yêu cầu: $content_file</div>";
                }
                ?>
            </section>
        </main>
    </div>

    <script>
        // Toggle sidebar on mobile
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            function toggleSidebar() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                }
            }

            // Gọi hàm khi tải trang
            toggleSidebar();

            // Theo dõi thay đổi kích thước cửa sổ
            window.addEventListener('resize', toggleSidebar);
        });
    </script>
</body>
</html>