<?php
// Xử lý page layout
$page_layout = isset($_GET['page_layout']) ? $_GET['page_layout'] : 'trangchu';

// Danh sách các trang hợp lệ và file tương ứng
$pages = [
    'trangchu' => 'home.php',
    'quanlygatau' => 'quanlygatau.php',
    'quanlydoantau' => 'quanlydoantau.php',
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
        'quanlyve' => 'Quản Lý Vé',
        'banve' => 'Bán Vé',
        'quanlykhachhang' => 'Quản Lý Khách Hàng',
        'baocaodoanhthu' => 'Báo Cáo Doanh Thu'
    ];
    return isset($titles[$layout]) ? $titles[$layout] : 'Trang Chủ';
}

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ Thống Quản Lý Vé Tàu</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Định nghĩa biến CSS */
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
            --danger-color: #e74c3c;
            --border-radius: 8px;
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f4f7fa;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--primary-color);
            color: var(--light-color);
            transition: var(--transition);
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar.collapsed {
            width: 60px;
        }

        .logo {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            gap: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo i {
            font-size: 1.5rem;
        }

        .logo span {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .sidebar.collapsed .logo span {
            display: none;
        }

        .nav-menu ul {
            list-style: none;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: var(--light-color);
            text-decoration: none;
            gap: 1rem;
            transition: var(--transition);
        }

        .nav-item a:hover {
            background-color: var(--secondary-color);
        }

        .nav-item.active a {
            background-color: var(--secondary-color);
            font-weight: 600;
        }

        .nav-item a i {
            font-size: 1.2rem;
        }

        .sidebar.collapsed .nav-item a span {
            display: none;
        }

        .sidebar.collapsed .nav-item a {
            justify-content: center;
            padding: 1rem;
        }

        /* Main content */
        .main-content {
            margin-left: 250px;
            flex: 1;
            transition: var(--transition);
        }

        .main-content.expanded {
            margin-left: 60px;
        }

        /* Topbar */
        .topbar {
            display: flex;
            align-items: center;
            padding: 1rem 2rem;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: var(--light-color);
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .toggle-menu {
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: var(--border-radius);
        }

        .toggle-menu:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: rotate(90deg);
        }

        .page-title {
            flex: 1;
            margin-left: 1rem;
        }

        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .user-info {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-color);
        }

        .user-info span {
            font-weight: 500;
        }

        .user-info:hover .dropdown-menu {
            display: block;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--light-color);
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            min-width: 150px;
            z-index: 1000;
        }

        .dropdown-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: var(--dark-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .dropdown-menu a:hover {
            background-color: var(--secondary-color);
            color: var(--light-color);
        }

        /* Content */
        .content {
            padding: 2rem;
        }

        .error {
            color: var(--danger-color);
            text-align: center;
            padding: 1rem;
            background-color: #ffe6e6;
            border-radius: var(--border-radius);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 200px;
            }

            .sidebar.collapsed {
                width: 60px;
            }

            .main-content {
                margin-left: 200px;
            }

            .main-content.expanded {
                margin-left: 60px;
            }

            .page-title h1 {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }

            .sidebar.collapsed {
                width: 60px;
            }

            .main-content {
                margin-left: 60px;
            }

            .main-content.expanded {
                margin-left: 60px;
            }

            .logo span,
            .nav-item a span {
                display: none;
            }

            .nav-item a {
                justify-content: center;
                padding: 1rem;
            }

            .topbar {
                padding: 0.75rem 1rem;
            }

            .page-title h1 {
                font-size: 1.2rem;
            }

            .user-info span {
                display: none;
            }

            .user-info img {
                width: 35px;
                height: 35px;
            }

            .content {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .topbar {
                padding: 0.5rem;
            }

            .page-title h1 {
                font-size: 1rem;
            }

            .toggle-menu {
                font-size: 1.2rem;
                padding: 0.3rem;
            }

            .user-info img {
                width: 30px;
                height: 30px;
            }

            .dropdown-menu {
                min-width: 120px;
            }

            .dropdown-menu a {
                padding: 0.5rem 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <div class="logo">
                <i class="fas fa-train"></i>
                <span>Quản Lý Vé Tàu</span>
            </div>
            <nav class="nav-menu">
                <ul>
                    <li class="nav-item <?php echo $page_layout === 'trangchu' ? 'active' : ''; ?>">
                        <a href="?page_layout=trangchu">
                            <i class="fas fa-home"></i>
                            <span>Trang Chủ</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout === 'quanlygatau' ? 'active' : ''; ?>">
                        <a href="?page_layout=quanlygatau">
                            <i class="fas fa-building"></i>
                            <span>Quản Lý Ga Tàu</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout === 'quanlydoantau' ? 'active' : ''; ?>">
                        <a href="?page_layout=quanlydoantau">
                            <i class="fas fa-subway"></i>
                            <span>Quản Lý Đoàn Tàu</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout === 'quanlyve' ? 'active' : ''; ?>">
                        <a href="?page_layout=quanlyve">
                            <i class="fas fa-ticket-alt"></i>
                            <span>Quản Lý Vé</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout === 'banve' ? 'active' : ''; ?>">
                        <a href="?page_layout=banve">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Bán Vé</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout === 'quanlykhachhang' ? 'active' : ''; ?>">
                        <a href="?page_layout=quanlykhachhang">
                            <i class="fas fa-users"></i>
                            <span>Quản Lý Khách Hàng</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $page_layout === 'baocaodoanhthu' ? 'active' : ''; ?>">
                        <a href="?page_layout=baocaodoanhthu">
                            <i class="fas fa-chart-line"></i>
                            <span>Báo Cáo Doanh Thu</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Đăng Xuất</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="topbar">
                <div class="toggle-menu">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="page-title">
                    <h1><?php echo getPageTitle($page_layout); ?></h1>
                </div>
                <div class="user-info">
                    <img src="https://via.placeholder.com/40" alt="User Avatar">
                    <span><?php echo htmlspecialchars($_SESSION['user']); ?></span>
                    <div class="dropdown-menu">
                        <a href="profile.php"><i class="fas fa-user-circle"></i> Hồ Sơ</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng Xuất</a>
                    </div>
                </div>
            </div>

            <div class="content">
                <?php
                if (file_exists($content_file)) {
                    include $content_file;
                } else {
                    echo '<div class="error">Trang không tồn tại</div>';
                }
                ?>
            </div>
        </main>
    </div>

    <script>
        document.querySelector('.toggle-menu').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });

        // Xử lý dropdown menu trên mobile
        document.querySelector('.user-info').addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const dropdown = this.querySelector('.dropdown-menu');
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
                e.stopPropagation();
            }
        });

        // Ẩn dropdown khi click bên ngoài
        document.addEventListener('click', function() {
            const dropdown = document.querySelector('.dropdown-menu');
            if (dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
            }
        });
    </script>
</body>
</html>