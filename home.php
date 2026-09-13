<?php
require_once 'connect.php';

// Lấy dữ liệu thống kê từ CSDL
function getStatistics($connetor) {
    $stats = [];
    
    // 1. Tổng số ga hoạt động
    $sql = "SELECT COUNT(*) AS total FROM Ga WHERE TrangThai = 'HoatDong'";
    $result = $connetor->query($sql);
    $stats['total_ga'] = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
    
    // 2. Tổng số đoàn tàu hoạt động
    $sql = "SELECT COUNT(*) AS total FROM Tau WHERE TrangThaiHoatDong = 'HoatDong'";
    $result = $connetor->query($sql);
    $stats['total_tau'] = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
    
    // 3. Tổng số vé đã bán (đã thanh toán)
    $sql = "SELECT COUNT(*) AS total FROM Ve WHERE TrangThai = 'DaThanhToan'";
    $result = $connetor->query($sql);
    $stats['total_ve'] = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
    
    // 4. Tổng số khách hàng
    $sql = "SELECT COUNT(*) AS total FROM KhachHang";
    $result = $connetor->query($sql);
    $stats['total_khachhang'] = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
    
    return $stats;
}

$statistics = getStatistics($connetor);
?>

<div class="dashboard-container">
    <div class="header">
        <h1>Dashboard</h1>
        <div class="user-info">
            <img src="https://via.placeholder.com/45" alt="Ảnh đại diện">
            <span>Quản Trị Viên</span>
        </div>
    </div>
    <div class="dashboard-stats">
        <div class="stat-card" data-type="total_ga">
            <h3>Tổng Số Ga</h3>
            <div class="number"><?= $statistics['total_ga'] > 0 ? $statistics['total_ga'] : 'Chưa có dữ liệu' ?></div>
            <i class="fas fa-train icon"></i>
            <div class="loading-spinner"></div>
        </div>
        <div class="stat-card" data-type="total_tau">
            <h3>Đoàn Tàu</h3>
            <div class="number"><?= $statistics['total_tau'] > 0 ? $statistics['total_tau'] : 'Chưa có dữ liệu' ?></div>
            <i class="fas fa-subway icon"></i>
            <div class="loading-spinner"></div>
        </div>
        <div class="stat-card" data-type="total_ve">
            <h3>Vé Đã Bán</h3>
            <div class="number"><?= $statistics['total_ve'] ?></div>
            <i class="fas fa-ticket-alt icon"></i>
            <div class="loading-spinner"></div>
        </div>
        <div class="stat-card" data-type="total_khachhang">
            <h3>Khách Hàng</h3>
            <div class="number"><?= $statistics['total_khachhang'] ?></div>
            <i class="fas fa-users icon"></i>
            <div class="loading-spinner"></div>
        </div>
    </div>
</div>

<style>
.dashboard-container {
    padding: 20px;
    background: transparent;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    padding: 20px 30px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    margin-bottom: 30px;
    position: relative;
}

.header h1 {
    font-size: 28px;
    font-weight: 700;
    color: #fff;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-info img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    border: 2px solid #fff;
    transition: transform 0.3s ease;
}

.user-info img:hover {
    transform: scale(1.1);
}

.user-info span {
    font-size: 16px;
    font-weight: 500;
    color: #fff;
}

.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
}

.stat-card {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    text-align: center;
    position: relative;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
}

.stat-card h3 {
    font-size: 18px;
    font-weight: 600;
    color: #555;
    margin: 0 0 15px 0;
    text-transform: uppercase;
}

.stat-card .number {
    font-size: 40px;
    font-weight: 800;
    color: #007bff;
    transition: transform 0.3s ease;
}

.stat-card .number:empty::after {
    content: "Chưa có dữ liệu";
    font-size: 20px;
    color: #888;
}

.stat-card:hover .number {
    transform: scale(1.05);
}

.stat-card .icon {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 30px;
    color: rgba(0, 123, 255, 0.1);
    transition: color 0.3s ease;
}

.stat-card:hover .icon {
    color: rgba(0, 123, 255, 0.3);
}

.loading-spinner {
    display: none;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 10px auto 0;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .dashboard-stats {
        grid-template-columns: 1fr;
    }
    .header {
        flex-direction: column;
        gap: 15px;
        padding: 15px 20px;
    }
    .header h1 {
        font-size: 24px;
    }
    .stat-card .number {
        font-size: 32px;
    }
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function updateDashboard() {
    $('.stat-card .loading-spinner').show();
    $.ajax({
        url: 'get_dashboard_stats.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            $('.stat-card[data-type="total_ga"] .number').text(data.total_ga > 0 ? data.total_ga : 'Chưa có dữ liệu');
            $('.stat-card[data-type="total_tau"] .number').text(data.total_tau > 0 ? data.total_tau : 'Chưa có dữ liệu');
            $('.stat-card[data-type="total_ve"] .number').text(data.total_ve);
            $('.stat-card[data-type="total_khachhang"] .number').text(data.total_khachhang);
            $('.stat-card .loading-spinner').hide();
        },
        error: function(xhr, status, error) {
            console.error('Lỗi khi cập nhật dashboard:', error);
            $('.stat-card .loading-spinner').hide();
        },
        complete: function() {
            setTimeout(updateDashboard, 30000); // Cập nhật mỗi 30 giây
        }
    });
}

$(document).ready(function() {
    updateDashboard(); // Bắt đầu cập nhật
});
</script>