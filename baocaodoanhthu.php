<?php
// Include connect.php với đường dẫn đúng
include 'connect.php';

// Kiểm tra kết nối
if ($connetor->connect_error) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $connetor->connect_error);
}

// Ánh xạ phương thức thanh toán từ giá trị enum sang tên hiển thị
$phuong_thuc_mapping = [
    'tienmat' => 'Tiền mặt',
    'the' => 'Thẻ',
    'tructuyen' => 'Ví điện tử'
];

// Xử lý lưu báo cáo mới
if (isset($_POST['save_report'])) {
    $report_id = 'BC' . time();
    $report_time = date('Y-m-d H:i:s');
    $date_from = mysqli_real_escape_string($connetor, $_POST['date_from']);
    $date_to = mysqli_real_escape_string($connetor, $_POST['date_to']);
    
    // Tính tổng vé bán và doanh thu trong khoảng thời gian từ bảng `ve`
    $sql_total_for_save = "SELECT 
                            COUNT(v.MaVe) as TongVe,
                            SUM(v.GiaVe) as TongDoanhThu,
                            GROUP_CONCAT(DISTINCT v.PhuongThucThanhToan) as ChiTietKenhBan
                          FROM ve v
                          WHERE v.NgayMua BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    
    $total_result_save = mysqli_query($connetor, $sql_total_for_save);
    if ($total_result_save) {
        $total_data_save = mysqli_fetch_assoc($total_result_save);
        $total_ve = $total_data_save['TongVe'] ?? 0;
        $total_doanhthu = $total_data_save['TongDoanhThu'] ?? 0;
        $kenh_ban = $total_data_save['ChiTietKenhBan'] ?? '';
        
        // Tạo JSON chi tiết các kênh bán
        $sql_channels = "SELECT 
                          v.PhuongThucThanhToan as Kenh,
                          COUNT(v.MaVe) as SoVe,
                          SUM(v.GiaVe) as DoanhThu
                        FROM ve v
                        WHERE v.NgayMua BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'
                        GROUP BY v.PhuongThucThanhToan";
        
        $channels_result = mysqli_query($connetor, $sql_channels);
        $channels_data = array();
        
        if ($channels_result) {
            while ($channel = mysqli_fetch_assoc($channels_result)) {
                $channel['Kenh'] = $phuong_thuc_mapping[$channel['Kenh']] ?? $channel['Kenh'];
                $channels_data[] = $channel;
            }
        }
        
        $chi_tiet_json = json_encode([
            'khoang_thoi_gian' => "Từ $date_from đến $date_to",
            'kenh_ban' => $channels_data
        ], JSON_UNESCAPED_UNICODE);
        
        // Lưu báo cáo vào CSDL
        $sql_save = "INSERT INTO baocaodoanhthu (MaBaoCao, ThoiGian, TongVeBan, TongDoanhThu, ChiTietKenhBan) 
                    VALUES ('$report_id', '$report_time', '$total_ve', '$total_doanhthu', '$chi_tiet_json')";
        
        if (mysqli_query($connetor, $sql_save)) {
            echo "<script>alert('Đã lưu báo cáo thành công!');</script>";
        } else {
            echo "<script>alert('Lỗi khi lưu báo cáo: " . mysqli_error($connetor) . "');</script>";
        }
    }
}

// Xử lý xóa báo cáo
if (isset($_GET['delete_report'])) {
    $report_id = mysqli_real_escape_string($connetor, $_GET['delete_report']);
    $sql_delete = "DELETE FROM baocaodoanhthu WHERE MaBaoCao = '$report_id'";
    
    if (mysqli_query($connetor, $sql_delete)) {
        echo "<script>alert('Đã xóa báo cáo thành công!');window.location.href='admin.php?page_layout=baocaodoanhthu';</script>";
    } else {
        echo "<script>alert('Lỗi khi xóa báo cáo: " . mysqli_error($connetor) . "');</script>";
    }
}

// Thêm xử lý tạo báo cáo theo kỳ
if (isset($_POST['generate_report'])) {
    $report_type = mysqli_real_escape_string($connetor, $_POST['report_type']);
    $report_period = mysqli_real_escape_string($connetor, $_POST['report_period']);
    $report_year = mysqli_real_escape_string($connetor, $_POST['report_year']);
    
    $date_from = '';
    $date_to = '';
    
    switch ($report_type) {
        case 'month':
            $date_from = "$report_year-$report_period-01";
            $date_to = date('Y-m-t', strtotime($date_from));
            break;
            
        case 'quarter':
            $quarter_start_month = ($report_period - 1) * 3 + 1;
            $date_from = "$report_year-" . str_pad($quarter_start_month, 2, '0', STR_PAD_LEFT) . "-01";
            $date_to = date('Y-m-t', strtotime("$report_year-" . str_pad($quarter_start_month + 2, 2, '0', STR_PAD_LEFT) . "-01"));
            break;
            
        case 'year':
            $date_from = "$report_year-01-01";
            $date_to = "$report_year-12-31";
            break;
    }
    
    // Tạo mã báo cáo
    $report_id = 'BC' . time();
    $report_time = date('Y-m-d H:i:s');
    
    // Tính tổng vé bán và doanh thu trong khoảng thời gian từ bảng `ve`
    $sql_total_for_save = "SELECT 
                            COUNT(v.MaVe) as TongVe,
                            SUM(v.GiaVe) as TongDoanhThu,
                            GROUP_CONCAT(DISTINCT v.PhuongThucThanhToan) as ChiTietKenhBan
                          FROM ve v
                          WHERE v.NgayMua BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    
    $total_result_save = mysqli_query($connetor, $sql_total_for_save);
    if ($total_result_save) {
        $total_data_save = mysqli_fetch_assoc($total_result_save);
        $total_ve = $total_data_save['TongVe'] ?? 0;
        $total_doanhthu = $total_data_save['TongDoanhThu'] ?? 0;
        
        // Chi tiết theo kênh bán
        $sql_channels = "SELECT 
                          v.PhuongThucThanhToan as Kenh,
                          COUNT(v.MaVe) as SoVe,
                          SUM(v.GiaVe) as DoanhThu
                        FROM ve v
                        WHERE v.NgayMua BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'
                        GROUP BY v.PhuongThucThanhToan";
        
        $channels_result = mysqli_query($connetor, $sql_channels);
        $channels_data = array();
        
        if ($channels_result) {
            while ($channel = mysqli_fetch_assoc($channels_result)) {
                $channel['Kenh'] = $phuong_thuc_mapping[$channel['Kenh']] ?? $channel['Kenh'];
                $channels_data[] = $channel;
            }
        }
        
        // Tạo mô tả kỳ báo cáo
        $period_desc = '';
        switch ($report_type) {
            case 'month':
                $period_desc = "Tháng $report_period năm $report_year";
                break;
            case 'quarter':
                $period_desc = "Quý $report_period năm $report_year";
                break;
            case 'year':
                $period_desc = "Năm $report_year";
                break;
        }
        
        $chi_tiet_json = json_encode([
            'ky_bao_cao' => $period_desc,
            'khoang_thoi_gian' => "Từ " . date('d/m/Y', strtotime($date_from)) . " đến " . date('d/m/Y', strtotime($date_to)),
            'kenh_ban' => $channels_data
        ], JSON_UNESCAPED_UNICODE);
        
        // Lưu báo cáo vào CSDL
        $sql_save = "INSERT INTO baocaodoanhthu (MaBaoCao, ThoiGian, TongVeBan, TongDoanhThu, ChiTietKenhBan) 
                    VALUES ('$report_id', '$report_time', '$total_ve', '$total_doanhthu', '$chi_tiet_json')";
        
        if (mysqli_query($connetor, $sql_save)) {
            echo "<script>alert('Đã tạo báo cáo thành công!');</script>";
        } else {
            echo "<script>alert('Lỗi khi tạo báo cáo: " . mysqli_error($connetor) . "');</script>";
        }
    }
}

// Xử lý tìm kiếm và lọc
$search_query = "";
$date_from = "";
$date_to = "";
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'realtime';

if (isset($_POST['search'])) {
    $search_query = mysqli_real_escape_string($connetor, $_POST['search_query']);
    $date_from = mysqli_real_escape_string($connetor, $_POST['date_from']);
    $date_to = mysqli_real_escape_string($connetor, $_POST['date_to']);
}

// Phân trang
$limit = 10; // Số báo cáo mỗi trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// PHẦN 1: TRUY VẤN THỐNG KÊ THỜI GIAN THỰC
if ($view_mode == 'realtime') {
    // Truy vấn để lấy doanh thu theo ngày từ bảng `ve`
    $sql = "SELECT 
                DATE(v.NgayMua) as ThoiGian,
                COUNT(v.MaVe) as TongVeBan,
                SUM(v.GiaVe) as TongDoanhThu,
                GROUP_CONCAT(DISTINCT v.PhuongThucThanhToan) as ChiTietKenhBan
            FROM ve v
            WHERE 1=1";

    if (!empty($search_query)) {
        $sql .= " AND (v.MaVe LIKE '%$search_query%' OR v.PhuongThucThanhToan LIKE '%$search_query%')";
    }
    if (!empty($date_from) && !empty($date_to)) {
        $sql .= " AND v.NgayMua BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    }
    $sql .= " GROUP BY DATE(v.NgayMua)
            ORDER BY ThoiGian DESC
            LIMIT $start, $limit";

    $result = mysqli_query($connetor, $sql);
    if (!$result) {
        die("Lỗi truy vấn: " . mysqli_error($connetor));
    }

    // Truy vấn để lấy tổng doanh thu
    $sql_total = "SELECT 
                    SUM(v.GiaVe) as TongDoanhThu,
                    COUNT(v.MaVe) as TongVe
                FROM ve v
                WHERE 1=1";

    if (!empty($search_query)) {
        $sql_total .= " AND (v.MaVe LIKE '%$search_query%' OR v.PhuongThucThanhToan LIKE '%$search_query%')";
    }
    if (!empty($date_from) && !empty($date_to)) {
        $sql_total .= " AND v.NgayMua BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    }

    $total_result = mysqli_query($connetor, $sql_total);
    if (!$total_result) {
        die("Lỗi truy vấn tổng doanh thu: " . mysqli_error($connetor));
    }
    $total_data = mysqli_fetch_assoc($total_result);

    // Đếm tổng số báo cáo để phân trang
    $sql_count = "SELECT COUNT(DISTINCT DATE(v.NgayMua)) as total
                FROM ve v
                WHERE 1=1";

    if (!empty($search_query)) {
        $sql_count .= " AND (v.MaVe LIKE '%$search_query%' OR v.PhuongThucThanhToan LIKE '%$search_query%')";
    }
    if (!empty($date_from) && !empty($date_to)) {
        $sql_count .= " AND v.NgayMua BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    }

    $count_result = mysqli_query($connetor, $sql_count);
    if (!$count_result) {
        die("Lỗi truy vấn đếm: " . mysqli_error($connetor));
    }
    $total_rows = mysqli_fetch_assoc($count_result)['total'];
    $total_pages = ceil($total_rows / $limit);

    // Truy vấn doanh thu theo phương thức thanh toán
    $sql_by_method = "SELECT 
                        v.PhuongThucThanhToan,
                        COUNT(v.MaVe) as SoVe,
                        SUM(v.GiaVe) as DoanhThu
                    FROM ve v
                    WHERE 1=1";

    if (!empty($date_from) && !empty($date_to)) {
        $sql_by_method .= " AND v.NgayMua BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    }

    $sql_by_method .= " GROUP BY v.PhuongThucThanhToan
                        ORDER BY DoanhThu DESC";

    $method_result = mysqli_query($connetor, $sql_by_method);
    if (!$method_result) {
        die("Lỗi truy vấn phương thức thanh toán: " . mysqli_error($connetor));
    }

    // Tính tăng trưởng so với ngày trước
    $sql_growth = "SELECT 
        (SELECT SUM(v.GiaVe) 
         FROM ve v 
         WHERE DATE(v.NgayMua) = CURDATE()) as today_revenue,
        (SELECT SUM(v.GiaVe) 
         FROM ve v 
         WHERE DATE(v.NgayMua) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)) as yesterday_revenue";
    
    $growth_result = mysqli_query($connetor, $sql_growth);
    $growth_data = mysqli_fetch_assoc($growth_result);
    
    $growth_percentage = 0;
    if ($growth_data['yesterday_revenue'] > 0) {
        $growth_percentage = (($growth_data['today_revenue'] - $growth_data['yesterday_revenue']) / $growth_data['yesterday_revenue']) * 100;
    }
}
// PHẦN 2: TRUY VẤN BÁO CÁO ĐÃ LƯU
else if ($view_mode == 'saved') {
    // Truy vấn báo cáo đã lưu
    $sql = "SELECT *
            FROM baocaodoanhthu
            ORDER BY ThoiGian DESC
            LIMIT $start, $limit";
    
    $result = mysqli_query($connetor, $sql);
    if (!$result) {
        die("Lỗi truy vấn báo cáo đã lưu: " . mysqli_error($connetor));
    }
    
    // Đếm tổng số báo cáo đã lưu để phân trang
    $sql_count = "SELECT COUNT(*) as total FROM baocaodoanhthu";
    $count_result = mysqli_query($connetor, $sql_count);
    if (!$count_result) {
        die("Lỗi truy vấn đếm báo cáo đã lưu: " . mysqli_error($connetor));
    }
    $total_rows = mysqli_fetch_assoc($count_result)['total'];
    $total_pages = ceil($total_rows / $limit);
}
?>

<!-- Thêm CSS mới -->
<style>
:root {
    --primary-color: #2c3e50;
    --secondary-color: #3498db;
    --success-color: #27ae60;
    --warning-color: #f1c40f;
    --danger-color: #e74c3c;
    --light-color: #ecf0f1;
    --dark-color: #2c3e50;
    --border-radius: 8px;
    --box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    --transition: all 0.3s ease;
}

.dashboard-container {
    padding: 2rem;
    background-color: #f8f9fa;
    min-height: 100vh;
}

.dashboard-header {
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dashboard-title {
    color: var(--primary-color);
    font-size: 2rem;
    font-weight: 700;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-title {
    color: var(--dark-color);
    font-size: 1.1rem;
    font-weight: 600;
}

.stat-icon {
    width: 40px;
    height: 40px;
    background: var(--light-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
    margin: 0.5rem 0;
}

.stat-description {
    color: #666;
    font-size: 0.9rem;
}

.growth-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    font-size: 0.9rem;
}

.growth-positive {
    color: var(--success-color);
}

.growth-negative {
    color: var(--danger-color);
}

.chart-container {
    background: white;
    padding: 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.chart-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--dark-color);
}

.chart-controls {
    display: flex;
    gap: 1rem;
}

.filter-section {
    background: white;
    padding: 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-weight: 500;
    color: var(--dark-color);
}

.filter-input {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.filter-input:focus {
    border-color: var(--secondary-color);
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.table-container {
    background: white;
    padding: 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
}

.custom-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.custom-table th {
    background: var(--primary-color);
    color: white;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
}

.custom-table th:first-child {
    border-top-left-radius: var(--border-radius);
}

.custom-table th:last-child {
    border-top-right-radius: var(--border-radius);
}

.custom-table td {
    padding: 1rem;
    border-bottom: 1px solid #eee;
}

.custom-table tbody tr:hover {
    background: #f8f9fa;
}

.custom-pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.page-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: var(--light-color);
    color: var(--dark-color);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
}

.page-btn:hover {
    background: var(--secondary-color);
    color: white;
}

.page-btn.active {
    background: var(--primary-color);
    color: white;
}

.action-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-view {
    background: var(--secondary-color);
    color: white;
}

.btn-delete {
    background: var(--danger-color);
    color: white;
}

.action-btn:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .chart-header {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Báo Cáo Doanh Thu</h1>
        <div class="view-tabs">
            <a href="?page_layout=baocaodoanhthu&view=realtime" 
               class="action-btn <?php echo $view_mode == 'realtime' ? 'btn-view' : ''; ?>">
                <i class="fas fa-clock"></i> Thời gian thực
            </a>
            <a href="?page_layout=baocaodoanhthu&view=saved" 
               class="action-btn <?php echo $view_mode == 'saved' ? 'btn-view' : ''; ?>">
                <i class="fas fa-save"></i> Báo cáo đã lưu
            </a>
        </div>
    </div>

    <?php if ($view_mode == 'realtime'): ?>
        <!-- Thống kê tổng quan -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Tổng doanh thu</div>
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <div class="stat-value">
                    <?php echo number_format($total_data['TongDoanhThu'] ?? 0, 0, ',', '.'); ?> VNĐ
                </div>
                <div class="stat-description">
                    Tổng số vé: <?php echo number_format($total_data['TongVe'] ?? 0, 0, ',', '.'); ?>
                </div>
                <?php if (isset($growth_percentage)): ?>
                <div class="growth-indicator <?php echo $growth_percentage >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                    <i class="fas fa-<?php echo $growth_percentage >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo abs(round($growth_percentage, 1)); ?>% so với hôm qua
                </div>
                <?php endif; ?>
            </div>

            <?php if (isset($method_result) && mysqli_num_rows($method_result) > 0): 
                while ($method = mysqli_fetch_assoc($method_result)): 
                    $method_display = $phuong_thuc_mapping[$method['PhuongThucThanhToan']] ?? $method['PhuongThucThanhToan'];
                ?>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title"><?php echo htmlspecialchars($method_display); ?></div>
                        <div class="stat-icon">
                            <i class="fas fa-<?php 
                                echo $method_display == 'Tiền mặt' ? 'money-bill' : 
                                    ($method_display == 'Ví điện tử' ? 'credit-card' : 'university'); 
                            ?>"></i>
                        </div>
                    </div>
                    <div class="stat-value">
                        <?php echo number_format($method['DoanhThu'] ?? 0, 0, ',', '.'); ?> VNĐ
                    </div>
                    <div class="stat-description">
                        Số vé: <?php echo number_format($method['SoVe'] ?? 0, 0, ',', '.'); ?>
                    </div>
                </div>
            <?php endwhile; endif; ?>
        </div>

        <!-- Form tạo báo cáo -->
        <div class="filter-section">
            <h3><i class="fas fa-file-alt"></i> Tạo báo cáo theo kỳ</h3>
            <form method="POST" class="filter-grid">
                <div class="filter-item">
                    <label class="filter-label">Loại báo cáo</label>
                    <select name="report_type" class="filter-input" id="reportType" onchange="updatePeriodOptions()">
                        <option value="month">Theo tháng</option>
                        <option value="quarter">Theo quý</option>
                        <option value="year">Theo năm</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label class="filter-label">Kỳ báo cáo</label>
                    <select name="report_period" class="filter-input" id="reportPeriod"></select>
                </div>
                <div class="filter-item">
                    <label class="filter-label">Năm</label>
                    <select name="report_year" class="filter-input">
                        <?php
                        $current_year = date('Y');
                        for ($year = $current_year; $year >= $current_year - 5; $year--) {
                            echo "<option value='$year'>$year</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label class="filter-label">&nbsp;</label>
                    <button type="submit" name="generate_report" class="action-btn btn-view">
                        <i class="fas fa-file-export"></i> Tạo báo cáo
                    </button>
                </div>
            </form>
        </div>

        <!-- Bộ lọc tìm kiếm -->
        <div class="filter-section">
            <form method="POST" class="filter-grid">
                <div class="filter-item">
                    <label class="filter-label">Tìm kiếm</label>
                    <input type="text" name="search_query" class="filter-input" 
                           placeholder="Mã vé hoặc phương thức thanh toán" 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="filter-item">
                    <label class="filter-label">Từ ngày</label>
                    <input type="date" name="date_from" class="filter-input" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="filter-item">
                    <label class="filter-label">Đến ngày</label>
                    <input type="date" name="date_to" class="filter-input" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="filter-item">
                    <label class="filter-label">&nbsp;</label>
                    <button type="submit" name="search" class="action-btn btn-view">
                        <i class="fas fa-search"></i> Tìm kiếm
                    </button>
                </div>
            </form>
        </div>

        <!-- Bảng dữ liệu -->
        <div class="table-container">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th>Tổng vé bán</th>
                        <th>Tổng doanh thu</th>
                        <th>Phương thức thanh toán</th>
                        <?php if (!empty($date_from) && !empty($date_to)): ?>
                        <th>Thao tác</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): 
                            // Ánh xạ danh sách phương thức thanh toán
                            $kenh_ban_list = array_map(function($kenh) use ($phuong_thuc_mapping) {
                                return $phuong_thuc_mapping[$kenh] ?? $kenh;
                            }, explode(',', $row['ChiTietKenhBan'] ?? ''));
                            $kenh_ban_display = implode(', ', $kenh_ban_list);
                        ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($row['ThoiGian'])); ?></td>
                                <td><?php echo number_format($row['TongVeBan'], 0, ',', '.'); ?></td>
                                <td><?php echo number_format($row['TongDoanhThu'] ?? 0, 0, ',', '.'); ?> VNĐ</td>
                                <td><?php echo htmlspecialchars($kenh_ban_display ?: 'N/A'); ?></td>
                                <?php if (!empty($date_from) && !empty($date_to)): ?>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                                        <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                                        <button type="submit" name="save_report" class="action-btn btn-view">
                                            <i class="fas fa-save"></i> Lưu
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Không có dữ liệu</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <!-- Bảng báo cáo đã lưu -->
        <div class="table-container">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Mã báo cáo</th>
                        <th>Thời gian tạo</th>
                        <th>Tổng vé bán</th>
                        <th>Tổng doanh thu</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['MaBaoCao']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['ThoiGian'])); ?></td>
                                <td><?php echo number_format($row['TongVeBan'], 0, ',', '.'); ?></td>
                                <td><?php echo number_format($row['TongDoanhThu'] ?? 0, 0, ',', '.'); ?> VNĐ</td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="admin.php?page_layout=baocaodoanhthu&view=saved&report_detail=<?php echo $row['MaBaoCao']; ?>" 
                                           class="action-btn btn-view">
                                            <i class="fas fa-eye"></i> Xem
                                        </a>
                                        <a href="admin.php?page_layout=baocaodoanhthu&view=saved&delete_report=<?php echo $row['MaBaoCao']; ?>" 
                                           class="action-btn btn-delete"
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa báo cáo này?')">
                                            <i class="fas fa-trash"></i> Xóa
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Không có báo cáo đã lưu</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Chi tiết báo cáo -->
        <?php if (isset($_GET['report_detail'])): ?>
            <?php
            $report_id = mysqli_real_escape_string($connetor, $_GET['report_detail']);
            $sql_detail = "SELECT * FROM baocaodoanhthu WHERE MaBaoCao = '$report_id'";
            $detail_result = mysqli_query($connetor, $sql_detail);
            
            if ($detail_result && mysqli_num_rows($detail_result) > 0):
                $report_detail = mysqli_fetch_assoc($detail_result);
                $chi_tiet = json_decode($report_detail['ChiTietKenhBan'], true);
            ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Chi tiết báo cáo: <?php echo htmlspecialchars($report_id); ?></div>
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                    <div class="stat-value">
                        <?php echo number_format($report_detail['TongDoanhThu'] ?? 0, 0, ',', '.'); ?> VNĐ
                    </div>
                    <div class="stat-description">
                        Tổng số vé: <?php echo number_format($report_detail['TongVeBan'] ?? 0, 0, ',', '.'); ?>
                    </div>
                    <div class="stat-description">
                        Thời gian tạo: <?php echo date('d/m/Y H:i', strtotime($report_detail['ThoiGian'])); ?>
                    </div>
                    <?php if (isset($chi_tiet['khoang_thoi_gian'])): ?>
                    <div class="stat-description">
                        Khoảng thời gian: <?php echo htmlspecialchars($chi_tiet['khoang_thoi_gian']); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (isset($chi_tiet['kenh_ban']) && is_array($chi_tiet['kenh_ban'])): ?>
                    <?php foreach ($chi_tiet['kenh_ban'] as $kenh): ?>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title"><?php echo htmlspecialchars($kenh['Kenh'] ?? 'N/A'); ?></div>
                            <div class="stat-icon">
                                <i class="fas fa-<?php 
                                    echo $kenh['Kenh'] == 'Tiền mặt' ? 'money-bill' : 
                                        ($kenh['Kenh'] == 'Ví điện tử' ? 'credit-card' : 'university'); 
                                ?>"></i>
                            </div>
                        </div>
                        <div class="stat-value">
                            <?php echo number_format($kenh['DoanhThu'] ?? 0, 0, ',', '.'); ?> VNĐ
                        </div>
                        <div class="stat-description">
                            Số vé: <?php echo number_format($kenh['SoVe'] ?? 0, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Phân trang -->
    <?php if ($total_pages > 1): ?>
    <div class="custom-pagination">
        <?php if ($page > 1): ?>
            <a href="?page_layout=baocaodoanhthu&view=<?php echo $view_mode; ?>&page=<?php echo $page - 1; ?>" 
               class="page-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page_layout=baocaodoanhthu&view=<?php echo $view_mode; ?>&page=<?php echo $i; ?>" 
               class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page_layout=baocaodoanhthu&view=<?php echo $view_mode; ?>&page=<?php echo $page + 1; ?>" 
               class="page-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function updatePeriodOptions() {
    const reportType = document.getElementById('reportType').value;
    const periodSelect = document.getElementById('reportPeriod');
    periodSelect.innerHTML = '';
    
    switch (reportType) {
        case 'month':
            for (let i = 1; i <= 12; i++) {
                const option = document.createElement('option');
                option.value = String(i).padStart(2, '0');
                option.textContent = `Tháng ${i}`;
                periodSelect.appendChild(option);
            }
            break;
            
        case 'quarter':
            for (let i = 1; i <= 4; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = `Quý ${i}`;
                periodSelect.appendChild(option);
            }
            break;
            
        case 'year':
            const option = document.createElement('option');
            option.value = '1';
            option.textContent = 'Cả năm';
            periodSelect.appendChild(option);
            break;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updatePeriodOptions();
    
    // Thêm animation cho các card khi load trang
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php mysqli_close($connetor); ?>