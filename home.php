<?php
require_once 'connect.php';

// Lấy dữ liệu thống kê từ CSDL
function getStatistics($connetor) {
    $stats = [];
    
    try {
        // 1. Tổng số ga hoạt động
        $stmt = $connetor->prepare("SELECT COUNT(*) AS total FROM ga WHERE TrangThai = ?");
        if ($stmt === false) {
            error_log("Prepare failed for total_ga: " . $connetor->error);
            throw new Exception("Prepare failed for total_ga");
        }
        $status = 'hoatdong';
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_ga'] = $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();
        
        // 2. Tổng số đoàn tàu hoạt động
        $stmt = $connetor->prepare("SELECT COUNT(*) AS total FROM tau WHERE TrangThaiHoatDong = ?");
        if ($stmt === false) {
            error_log("Prepare failed for total_tau: " . $connetor->error);
            throw new Exception("Prepare failed for total_tau");
        }
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_tau'] = $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();
        
        // 3. Tổng số vé đã bán (đã thanh toán)
        $stmt = $connetor->prepare("SELECT COUNT(*) AS total FROM ve WHERE TrangThai = ?");
        if ($stmt === false) {
            error_log("Prepare failed for total_ve: " . $connetor->error);
            throw new Exception("Prepare failed for total_ve");
        }
        $status_ve = 'hoatdong';
        $stmt->bind_param("s", $status_ve);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_ve'] = $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();
        
        // 4. Tổng số khách hàng
        $stmt = $connetor->prepare("SELECT COUNT(*) AS total FROM khachhang");
        if ($stmt === false) {
            error_log("Prepare failed for total_khachhang: " . $connetor->error);
            throw new Exception("Prepare failed for total_khachhang");
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_khachhang'] = $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();

        // 5. Doanh thu hôm nay
        $stmt = $connetor->prepare("SELECT COALESCE(SUM(GiaVe), 0) AS total FROM ve WHERE TrangThai = ? AND DATE(NgayMua) = CURDATE()");
        if ($stmt === false) {
            error_log("Prepare failed for doanh_thu_ngay: " . $connetor->error);
            throw new Exception("Prepare failed for doanh_thu_ngay");
        }
        $stmt->bind_param("s", $status_ve);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['doanh_thu_ngay'] = $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();

        // 6. Doanh thu tháng này
        $stmt = $connetor->prepare("SELECT COALESCE(SUM(GiaVe), 0) AS total FROM ve WHERE TrangThai = ? AND MONTH(Ngayවින්ටර්න້ำຍິງເວົ້າ MONTH(NgayMua) = MONTH(CURDATE()) AND YEAR(NgayMua) = YEAR(CURDATE())");
        if ($stmt === false) {
            error_log("Prepare failed for doanh_thu_thang: " . $connetor->error);
            throw new Exception("Prepare failed for doanh_thu_thang");
        }
        $stmt->bind_param("s", $status_ve);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['doanh_thu_thang'] = $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();

        // 7. Số vé đã bán hôm nay
        $stmt = $connetor->prepare("SELECT COUNT(*) AS total FROM ve WHERE TrangThai = ? AND DATE(NgayMua) = CURDATE()");
        if ($stmt === false) {
            error_log("Prepare failed for ve_ban_ngay: " . $connetor->error);
            throw new Exception("Prepare failed for ve_ban_ngay");
        }
        $stmt->bind_param("s", $status_ve);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['ve_ban_ngay'] = $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();

        // 8. Số khách hàng mới trong tháng
        // Không có cột ngày đăng ký, đếm tất cả khách hàng trong khach_hang_register
        $stmt = $connetor->prepare("SELECT COUNT(*) AS total FROM khach_hang_register");
        if ($stmt === false) {
            error_log("Prepare failed for khach_hang_moi: " . $connetor->error);
            throw new Exception("Prepare failed for khach_hang_moi");
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['khach_hang_moi'] = $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error in getStatistics: " . $e->getMessage());
        return array_fill_keys(['total_ga', 'total_tau', 'total_ve', 'total_khachhang', 'doanh_thu_ngay', 'doanh_thu_thang', 've_ban_ngay', 'khach_hang_moi'], 0);
    }
    
    return $stats;
}

$statistics = getStatistics($connetor);

// Lấy thông tin các chuyến tàu sắp khởi hành
$stmt = $connetor->prepare("
    SELECT ht.MaHanhTrinh, ht.MoTa, g1.TenGa AS GaDi, g2.TenGa AS GaDen, t.SoHieu AS TenTau, 
           qdt.gio_xuat_phat AS ThoiGianDi
    FROM hanhtrinh ht 
    JOIN ve v ON ht.MaHanhTrinh = v.MaHanhTrinh
    JOIN ga g1 ON v.GaDi = g1.MaGa 
    JOIN ga g2 ON v.GaDen = g2.MaGa 
    LEFT JOIN quanlydoantau qdt ON qdt.gio_xuat_phat >= NOW()
    LEFT JOIN tau t ON t.TrangThaiHoatDong = 'hoatdong'
    WHERE qdt.gio_xuat_phat >= NOW() 
    GROUP BY ht.MaHanhTrinh, g1.TenGa, g2.TenGa, t.SoHieu, qdt.gio_xuat_phat
    ORDER BY qdt.gio_xuat_phat ASC 
    LIMIT 5
");
$upcoming_trips = [];
if ($stmt === false) {
    error_log("Prepare failed for upcoming_trips: " . $connetor->error);
} else {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['TenTau'] = $row['TenTau'] ?? 'Chưa xác định';
            $upcoming_trips[] = $row;
        }
    }
    $stmt->close();
}

// Lấy thông tin các giao dịch gần đây
$stmt = $connetor->prepare("
    SELECT v.MaVe, v.NgayMua, v.TrangThai, v.GiaVe, 
           kh.HoTen, kh.Email, qdt.gio_xuat_phat AS ThoiGianDi,
           g1.TenGa AS GaDi, g2.TenGa AS GaDen
    FROM ve v 
    JOIN khachhang kh ON v.MaKhachHang = kh.MaKhachHang 
    JOIN hanhtrinh ht ON v.MaHanhTrinh = ht.MaHanhTrinh
    JOIN ga g1 ON v.GaDi = g1.MaGa
    JOIN ga g2 ON v.GaDen = g2.MaGa
    LEFT JOIN quanlydoantau qdt ON qdt.gio_xuat_phat >= NOW()
    WHERE v.TrangThai = ?
    ORDER BY v.NgayMua DESC 
    LIMIT 5
");
$recent_transactions = [];
if ($stmt === false) {
    error_log("Prepare failed for recent_transactions: " . $connetor->error);
} else {
    $status_ve = 'hoatdong';
    $stmt->bind_param("s", $status_ve);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recent_transactions[] = $row;
        }
    }
    $stmt->close();
}

// Kiểm tra session
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = 'Admin';
}
?>
<link rel="stylesheet" href="home.css">
</style>
<!-- HTML, CSS, và JavaScript giữ nguyên như code gốc -->
<div class="dashboard-container">
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    <div class="particles-container" id="particlesContainer"></div>

    <!-- Header Section -->
    <div class="header reveal">
        <div class="header-content">
            <h1><i class="fas fa-tachometer-alt"></i> Tổng Quan Hệ Thống</h1>
            <p class="current-time"><?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
        <div class="user-info">
            <div class="user-details">
                <span class="welcome">Xin chào,</span>
                <span class="username"><?php echo htmlspecialchars($_SESSION['user']); ?></span>
            </div>
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user']); ?>&background=random" alt="Avatar">
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card primary reveal">
            <div class="stat-icon">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div class="stat-details">
                <h3>Vé Bán Hôm Nay</h3>
                <p class="number" id="veBanNgay"><?php echo number_format($statistics['ve_ban_ngay']); ?></p>
                <p class="description">Doanh thu: <span id="doanhThuNgay"><?php echo number_format($statistics['doanh_thu_ngay']); ?></span>đ</p>
            </div>
            <div class="tooltip">
                <span class="tooltip-text">Thống kê vé bán và doanh thu hôm nay</span>
            </div>
        </div>

        <div class="stat-card success reveal">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-details">
                <h3>Doanh Thu Tháng</h3>
                <p class="number" id="doanhThuThang"><?php echo number_format($statistics['doanh_thu_thang']); ?>đ</p>
                <p class="description">Tổng số vé: <?php echo number_format($statistics['total_ve']); ?></p>
            </div>
            <div class="tooltip">
                <span class="tooltip-text">Doanh thu và tổng vé bán trong tháng</span>
            </div>
        </div>

        <div class="stat-card info reveal">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <h3>Khách Hàng</h3>
                <p class="number"><?php echo number_format($statistics['total_khachhang']); ?></p>
                <p class="description">Mới tháng này: +<?php echo number_format($statistics['khach_hang_moi']); ?></p>
            </div>
            <div class="tooltip">
                <span class="tooltip-text">Tổng khách hàng và khách hàng mới</span>
            </div>
        </div>

        <div class="stat-card warning reveal">
            <div class="stat-icon">
                <i class="fas fa-train"></i>
            </div>
            <div class="stat-details">
                <h3>Đoàn Tàu & Ga</h3>
                <p class="number"><?php echo number_format($statistics['total_tau']); ?> / <?php echo number_format($statistics['total_ga']); ?></p>
                <p class="description">Đoàn tàu / Ga đang hoạt động</p>
            </div>
            <div class="tooltip">
                <span class="tooltip-text">Tổng số đoàn tàu và ga hoạt động</span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Upcoming Trips Section -->
        <div class="content-card reveal">
            <div class="card-header">
                <h2><i class="fas fa-clock"></i> Chuyến Tàu Sắp Khởi Hành</h2>
                <div class="tooltip">
                    <a href="?page_layout=quanlyhanhtrinh" class="view-all">Xem tất cả</a>
                    <span class="tooltip-text">Xem danh sách tất cả chuyến tàu</span>
                </div>
            </div>
            <div class="card-content">
                <?php if (!empty($upcoming_trips)): ?>
                    <div class="trips-list">
                        <?php foreach ($upcoming_trips as $trip): ?>
                            <div class="trip-item">
                                <div class="trip-info">
                                    <div class="route">
                                        <span class="station"><?php echo htmlspecialchars($trip['GaDi']); ?></span>
                                        <i class="fas fa-long-arrow-alt-right"></i>
                                        <span class="station"><?php echo htmlspecialchars($trip['GaDen']); ?></span>
                                    </div>
                                    <div class="time">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('H:i d/m/Y', strtotime($trip['ThoiGianDi'])); ?>
                                    </div>
                                </div>
                                <div class="train-info">
                                    <i class="fas fa-subway"></i>
                                    <?php echo htmlspecialchars($trip['TenTau']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-data">Không có chuyến tàu nào sắp khởi hành</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Transactions Section -->
        <div class="content-card reveal">
            <div class="card-header">
                <h2><i class="fas fa-receipt"></i> Giao Dịch Gần Đây</h2>
                <div class="tooltip">
                    <a href="?page_layout=quanlyve" class="view-all">Xem tất cả</a>
                    <span class="tooltip-text">Xem danh sách tất cả giao dịch</span>
                </div>
            </div>
            <div class="card-content">
                <div class="transactions-list" id="transactionsList">
                    <?php if (!empty($recent_transactions)): ?>
                        <?php foreach ($recent_transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="customer-info">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($transaction['HoTen']); ?>&size=32" alt="Avatar">
                                    <div class="details">
                                        <span class="name"><?php echo htmlspecialchars($transaction['HoTen']); ?></span>
                                        <span class="email"><?php echo htmlspecialchars($transaction['Email']); ?></span>
                                        <span class="route"><?php echo htmlspecialchars($transaction['GaDi']) . ' → ' . htmlspecialchars($transaction['GaDen']); ?></span>
                                    </div>
                                </div>
                                <div class="transaction-details">
                                    <span class="amount"><?php echo number_format($transaction['GiaVe']); ?>đ</span>
                                    <span class="time"><?php echo date('H:i d/m/Y', strtotime($transaction['NgayMua'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-data">Không có giao dịch nào gần đây</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Cập nhật thời gian hiện tại
function updateCurrentTime() {
    const now = new Date();
    const options = {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    };
    document.querySelector('.current-time').textContent = now.toLocaleString('vi-VN', options);
}

// Cập nhật thời gian mỗi giây
setInterval(updateCurrentTime, 1000);

// Hiển thị loading overlay khi tải trang
document.addEventListener('DOMContentLoaded', function() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    loadingOverlay.classList.add('active');

    // Tắt loading và hiển thị particle effect sau khi trang tải xong
    window.onload = function() {
        loadingOverlay.classList.remove('active');
        createParticles();
    };

    // Particle effect
    function createParticles() {
        const container = document.getElementById('particlesContainer');
        for (let i = 0; i < 30; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');
            const size = Math.random() * 5 + 5;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.left = Math.random() * 100 + 'vw';
            particle.style.top = Math.random() * 100 + 'vh';
            particle.style.animation = `particleRise ${Math.random() * 2 + 1}s ease-out`;
            container.appendChild(particle);
            setTimeout(() => particle.remove(), 3000);
        }
    }

    // Ripple effect cho stat-card và view-all
    const clickableElements = document.querySelectorAll('.stat-card, .view-all');
    clickableElements.forEach(element => {
        element.addEventListener('click', function(e) {
            const rect = element.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            element.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // Fade-in và scale animation cho trip-item và transaction-item
    const items = document.querySelectorAll('.trip-item, .transaction-item');
    items.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'scale(0.95)';
        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'scale(1)';
        }, index * 100);
    });

    // Scroll reveal effect
    const revealElements = document.querySelectorAll('.reveal');
    const revealOnScroll = () => {
        revealElements.forEach(element => {
            const windowHeight = window.innerHeight;
            const elementTop = element.getBoundingClientRect().top;
            if (elementTop < windowHeight - 100) {
                element.classList.add('visible');
            }
        });
    };
    window.addEventListener('scroll', revealOnScroll);
    revealOnScroll();

    // Animation cho các số liệu thống kê
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            const numberElement = this.querySelector('.number');
            numberElement.style.transform = 'scale(1.1)';
            setTimeout(() => {
                numberElement.style.transform = 'scale(1)';
            }, 200);
        });
    });

    // Cập nhật dữ liệu thời gian thực
    function updateRealTimeData() {
        fetch('get_realtime_data.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('veBanNgay').textContent = new Intl.NumberFormat('vi-VN').format(data.ve_ban_ngay);
                document.getElementById('doanhThuNgay').textContent = new Intl.NumberFormat('vi-VN').format(data.doanh_thu_ngay);
                document.getElementById('doanhThuThang').textContent = new Intl.NumberFormat('vi-VN').format(data.doanh_thu_thang) + 'đ';

                // Cập nhật giao dịch gần đây
                const transactionsList = document.getElementById('transactionsList');
                transactionsList.innerHTML = '';
                if (data.recent_transactions.length > 0) {
                    data.recent_transactions.forEach((transaction, index) => {
                        const item = document.createElement('div');
                        item.classList.add('transaction-item');
                        item.style.opacity = '0';
                        item.style.transform = 'scale(0.95)';
                        item.innerHTML = `
                            <div class="customer-info">
                                <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(transaction.HoTen)}&size=32" alt="Avatar">
                                <div class="details">
                                    <span class="name">${transaction.HoTen}</span>
                                    <span class="email">${transaction.Email}</span>
                                    <span class="route">${transaction.GaDi} → ${transaction.GaDen}</span>
                                </div>
                            </div>
                            <div class="transaction-details">
                                <span class="amount">${new Intl.NumberFormat('vi-VN').format(transaction.GiaVe)}đ</span>
                                <span class="time">${new Date(transaction.NgayMua).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' })}</span>
                            </div>
                        `;
                        transactionsList.appendChild(item);
                        setTimeout(() => {
                            item.style.transition = 'all 0.5s ease';
                            item.style.opacity = '1';
                            item.style.transform = 'scale(1)';
                        }, index * 100);
                    });
                } else {
                    transactionsList.innerHTML = '<p class="no-data">Không có giao dịch nào gần đây</p>';
                }
            })
            .catch(error => console.error('Error fetching real-time data:', error));
    }

    // Cập nhật dữ liệu mỗi 10 giây
    setInterval(updateRealTimeData, 10000);
    updateRealTimeData();

    // Add ripple effect style dynamically
    const style = document.createElement('style');
    style.textContent = `
        .ripple {
            position: absolute;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            width: 10px;
            height: 10px;
            transform: scale(0);
            animation: rippleEffect 0.6s linear;
            pointer-events: none;
        }
        @keyframes rippleEffect {
            to {
                transform: scale(20);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>