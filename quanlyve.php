<?php
require_once 'connect.php';

// Khởi tạo biến
$thong_bao = '';
$loi = '';
$ve_list = [];
$search_term = '';
$loai_ve_filter = '';


$sql_loai_ve = "SELECT DISTINCT LoaiVe FROM ve";
$result_loai_ve = $connetor->query($sql_loai_ve);
$loai_ve_list = [];
if ($result_loai_ve && $result_loai_ve->num_rows > 0) {
    while ($row = $result_loai_ve->fetch_assoc()) {
        $loai_ve_list[] = $row['LoaiVe'];
    }
}

// Phân trang
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Thống kê số lượng vé
$sql_stats = "SELECT 
                COUNT(CASE WHEN v.TrangThai = 'hoatdong' THEN 1 END) AS da_ban,
                COUNT(CASE WHEN v.TrangThai = 'dasudung' THEN 1 END) AS da_su_dung,
                COUNT(vdh.MaHuy) AS da_huy
              FROM ve v
              LEFT JOIN vedahuy vdh ON v.MaVe = vdh.MaVe";
$result_stats = $connetor->query($sql_stats);
if (!$result_stats) {
    error_log("Lỗi truy vấn thống kê: " . $connetor->error);
    $stats = ['da_ban' => 0, 'da_su_dung' => 0, 'da_huy' => 0];
} else {
    $stats = $result_stats->fetch_assoc();
}

// Đếm tổng số vé để phân trang
$sql_count = "SELECT COUNT(*) as total FROM ve v 
              LEFT JOIN khachhang k ON v.MaKhachHang = k.MaKhachHang";
$where_conditions = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_ve'])) {
    $search_term = $connetor->real_escape_string($_POST['search_term']);
    $loai_ve_filter = isset($_POST['loai_ve']) ? $connetor->real_escape_string($_POST['loai_ve']) : '';
    
    if (!empty($search_term)) {
        $where_conditions[] = "(v.MaVe LIKE '%$search_term%' OR k.HoTen LIKE '%$search_term%' OR k.SoDienThoai LIKE '%$search_term%')";
    }
    if (!empty($loai_ve_filter) && $loai_ve_filter != 'all') {
        $where_conditions[] = "v.LoaiVe = '$loai_ve_filter'";
    }
}
if (!empty($where_conditions)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions);
}

$result_count = $connetor->query($sql_count);
if (!$result_count) {
    error_log("Lỗi truy vấn đếm: " . $connetor->error);
    $total = 0;
} else {
    $total = $result_count->fetch_assoc()['total'];
}

$total_pages = ceil($total / $limit);

// Xử lý tìm kiếm vé
$base_query = "SELECT v.MaVe, v.MaKhachHang, v.NgayMua, v.LoaiVe, v.GiaVe, v.PhuongThucThanhToan, v.TrangThai,
                      k.HoTen, k.SoDienThoai
               FROM ve v
               LEFT JOIN khachhang k ON v.MaKhachHang = k.MaKhachHang";
$params = [];
$types = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_ve'])) {
    $search_term = $connetor->real_escape_string($_POST['search_term']);
    $loai_ve_filter = isset($_POST['loai_ve']) ? $connetor->real_escape_string($_POST['loai_ve']) : '';
    
    $where_conditions = [];
    if (!empty($search_term)) {
        $where_conditions[] = "(v.MaVe LIKE ? OR k.HoTen LIKE ? OR k.SoDienThoai LIKE ?)";
        $search_param = "%$search_term%";
        $params = [$search_param, $search_param, $search_param];
        $types = "sss";
    }
    if (!empty($loai_ve_filter) && $loai_ve_filter != 'all') {
        $where_conditions[] = "v.LoaiVe = ?";
        $params[] = $loai_ve_filter;
        $types .= "s";
    }
    if (!empty($where_conditions)) {
        $base_query .= " WHERE " . implode(" AND ", $where_conditions);
    }
}

$base_query .= " ORDER BY v.NgayMua DESC LIMIT ?, ?";
$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt = $connetor->prepare($base_query);
if (!$stmt) {
    error_log("Lỗi prepare truy vấn danh sách vé: " . $connetor->error);
} else {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result_ve = $stmt->get_result();
    if ($result_ve && $result_ve->num_rows > 0) {
        $ve_list = $result_ve->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// Xử lý hoàn tiền
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hoan_tien'])) {
    $ma_ve = $connetor->real_escape_string($_POST['ma_ve']);
    $connetor->begin_transaction();
    try {
        $stmt_ve = $connetor->prepare("SELECT v.MaKhachHang, v.GiaVe 
                                      FROM ve v 
                                      WHERE v.MaVe = ? AND v.TrangThai = 'hoatdong' AND DATE(v.NgayMua) = CURDATE()");
        $stmt_ve->bind_param("s", $ma_ve);
        $stmt_ve->execute();
        $result_ve = $stmt_ve->get_result();
        if ($result_ve->num_rows === 0) {
            throw new Exception("Vé không hợp lệ hoặc không mua hôm nay.");
        }
        $ve_info = $result_ve->fetch_assoc();
        $ma_khach_hang = $ve_info['MaKhachHang'];
        $tien_hoan = (float)$ve_info['GiaVe'];
        $stmt_ve->close();

        $stmt_vi = $connetor->prepare("UPDATE vitienkhachhang SET SoDu = SoDu + ?, NgayCapNhat = NOW() WHERE MaKhachHang = ?");
        $stmt_vi->bind_param("ds", $tien_hoan, $ma_khach_hang);
        if (!$stmt_vi->execute()) {
            throw new Exception("Lỗi cập nhật ví tiền.");
        }
        $stmt_vi->close();

        $stmt_ve_update = $connetor->prepare("UPDATE ve SET TrangThai = 'dahuy' WHERE MaVe = ?");
        $stmt_ve_update->bind_param("s", $ma_ve);
        if (!$stmt_ve_update->execute()) {
            throw new Exception("Lỗi cập nhật trạng thái vé.");
        }
        $stmt_ve_update->close();

        $ma_huy = 'H' . time();
        $stmt_vedahuy = $connetor->prepare("INSERT INTO vedahuy (MaHuy, MaVe, NgayHuy, HoanTien, TrangThaiXuLy) VALUES (?, ?, NOW(), 'co', 'daxuly')");
        $stmt_vedahuy->bind_param("ss", $ma_huy, $ma_ve);
        if (!$stmt_vedahuy->execute()) {
            throw new Exception("Lỗi thêm vào bảng vedahuy.");
        }
        $stmt_vedahuy->close();

        $connetor->commit();
        $thong_bao = "Hoàn tiền vé $ma_ve thành công! Số tiền " . number_format($tien_hoan) . "đ đã được hoàn.";
    } catch (Exception $e) {
        $connetor->rollback();
        $loi = $e->getMessage();
        error_log("Lỗi hoàn tiền vé $ma_ve: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Vé</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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

        body {
            background: #f4f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .dashboard-container {
            max-width: 1500px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .dashboard-header h2 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .dashboard-header h2 i {
            font-size: 2rem;
            color: var(--secondary-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            border: 1px solid #dfe6e9;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark-color);
            margin: 0.5rem 0;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.95rem;
            text-transform: uppercase;
        }

        .stat-icon {
            font-size: 1.8rem;
            margin-bottom: 0.75rem;
        }

        .sold .stat-icon { color: #2ecc71; }
        .pending .stat-icon { color: #f1c40f; }
        .cancelled .stat-icon { color: var(--danger-color); }

        .search-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            border: 1px solid #dfe6e9;
        }

        .search-form {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input, .filter-select {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border: 2px solid #dfe6e9;
            border-radius: var(--border-radius);
            font-size: 1rem;
            background: var(--light-color);
        }

        .search-input:focus, .filter-select:focus {
            border-color: var(--secondary-color);
            outline: none;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--secondary-color);
            color: var(--light-color);
        }

        .btn-danger {
            background: var(--danger-color);
            color: var(--light-color);
        }

        .tickets-grid {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .ticket-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid #dfe6e9;
        }

        .ticket-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #dfe6e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--light-color);
        }

        .ticket-id {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ticket-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .status-hoatdong {
            background: #b8e994;
            color: #219653;
        }

        .status-dasudung {
            background: #fffa65;
            color: #f39c12;
        }

        .status-dahuy {
            background: #ff8787;
            color: var(--danger-color);
        }

        .ticket-body {
            padding: 1.5rem;
        }

        .ticket-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }

        .info-group {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 1rem;
        }

        .ticket-actions {
            padding: 1rem 1.5rem;
            background: var(--light-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            border-top: 1px solid #dfe6e9;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--box-shadow);
        }

        .alert-success {
            background: #e6f9e6;
            color: #219653;
            border-left: 4px solid #2ecc71;
        }

        .alert-danger {
            background: #ffebec;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.75rem;
            margin-top: 2rem;
        }

        .page-link {
            padding: 0.6rem 1rem;
            border: 2px solid #dfe6e9;
            border-radius: var(--border-radius);
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 500;
        }

        .page-link:hover,
        .page-link.active {
            background: var(--secondary-color);
            color: var(--light-color);
            border-color: var(--secondary-color);
        }

        .page-nav {
            padding: 0.6rem 1rem;
            border: 2px solid #dfe6e9;
            border-radius: var(--border-radius);
            color: var(--dark-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
            }

            .search-input, .filter-select {
                width: 100%;
                padding: 0.6rem 1.2rem;
            }

            .btn-primary {
                width: 100%;
                justify-content: center;
            }

            .ticket-info {
                grid-template-columns: 1fr;
            }

            .ticket-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-danger {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2><i class="fas fa-ticket-alt"></i> Quản Lý Vé</h2>
        </div>

        <div class="stats-grid">
            <div class="stat-card sold">
                <i class="fas fa-check-circle stat-icon"></i>
                <div class="stat-value"><?= number_format($stats['da_ban']) ?></div>
                <div class="stat-label">Vé hoạt động</div>
            </div>
            <div class="stat-card pending">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value"><?= number_format($stats['da_su_dung']) ?></div>
                <div class="stat-label">Đã sử dụng</div>
            </div>
            <div class="stat-card cancelled">
                <i class="fas fa-times-circle stat-icon"></i>
                <div class="stat-value"><?= number_format($stats['da_huy']) ?></div>
                <div class="stat-label">Vé đã hủy</div>
            </div>
        </div>

        <?php if ($thong_bao): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($thong_bao) ?>
            </div>
        <?php endif; ?>

        <?php if ($loi): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($loi) ?>
            </div>
        <?php endif; ?>

        <div class="search-section">
            <form method="POST" class="search-form">
                <input type="text" 
                       name="search_term" 
                       class="search-input" 
                       placeholder="Tìm mã vé, tên khách hàng, số điện thoại..."
                       value="<?= htmlspecialchars($search_term) ?>">
                <select name="loai_ve" class="filter-select">
                    <option value="all" <?= $loai_ve_filter == 'all' || empty($loai_ve_filter) ? 'selected' : '' ?>>Tất cả loại vé</option>
                    <?php foreach ($loai_ve_list as $loai_ve): ?>
                        <option value="<?= htmlspecialchars($loai_ve) ?>" <?= $loai_ve_filter == $loai_ve ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loai_ve) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="search_ve" class="btn btn-primary">
                    <i class="fas fa-search"></i> Tìm kiếm
                </button>
            </form>
        </div>

        <div class="tickets-grid">
            <?php if (empty($ve_list)): ?>
                <div class="alert alert-danger" style="text-align: center;">
                    <i class="fas fa-info-circle"></i> Không tìm thấy vé nào.
                </div>
            <?php else: ?>
                <?php foreach ($ve_list as $ve): ?>
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <div class="ticket-id">
                                <i class="fas fa-ticket-alt"></i> Mã vé: <?= htmlspecialchars($ve['MaVe']) ?>
                            </div>
                            <div class="ticket-status status-<?= strtolower($ve['TrangThai']) ?>">
                                <?php
                                $trang_thai_text = [
                                    'hoatdong' => 'Hoạt động',
                                    'dasudung' => 'Đã sử dụng',
                                    'dahuy' => 'Đã hủy'
                                ];
                                echo $trang_thai_text[$ve['TrangThai']] ?? $ve['TrangThai'];
                                ?>
                            </div>
                        </div>
                        <div class="ticket-body">
                            <div class="ticket-info">
                                <div class="info-group">
                                    <span class="info-label">Khách hàng</span>
                                    <span class="info-value"><?= htmlspecialchars($ve['HoTen'] ?? 'Không xác định') ?></span>
                                </div>
                                <div class="info-group">
                                    <span class="info-label">Số điện thoại</span>
                                    <span class="info-value"><?= htmlspecialchars($ve['SoDienThoai'] ?? 'Không xác định') ?></span>
                                </div>
                                <div class="info-group">
                                    <span class="info-label">Loại vé</span>
                                    <span class="info-value"><?= htmlspecialchars($ve['LoaiVe']) ?></span>
                                </div>
                                <div class="info-group">
                                    <span class="info-label">Ngày mua</span>
                                    <span class="info-value"><?= date('d/m/Y H:i', strtotime($ve['NgayMua'])) ?></span>
                                </div>
                                <div class="info-group">
                                    <span class="info-label">Phương thức</span>
                                    <span class="info-value">
                                        <?php
                                        $phuong_thuc_text = [
                                            'tienmat' => 'Tiền mặt',
                                            'the' => 'Thẻ',
                                            'tructuyen' => 'Trực tuyến'
                                        ];
                                        echo $phuong_thuc_text[$ve['PhuongThucThanhToan']] ?? $ve['PhuongThucThanhToan'];
                                        ?>
                                    </span>
                                </div>
                                <div class="info-group">
                                    <span class="info-label">Giá vé</span>
                                    <span class="info-value"><?= number_format($ve['GiaVe']) ?>đ</span>
                                </div>
                            </div>
                        </div>
                        <?php if ($ve['TrangThai'] == 'hoatdong' && date('Y-m-d', strtotime($ve['NgayMua'])) === date('Y-m-d')): ?>
                            <div class="ticket-actions">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc chắn muốn hoàn tiền vé này?');">
                                    <input type="hidden" name="ma_ve" value="<?= htmlspecialchars($ve['MaVe']) ?>">
                                    <button type="submit" name="hoan_tien" class="btn btn-danger">
                                        <i class="fas fa-undo"></i> Hoàn tiền
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page_layout=quanlyve&page=<?= $page - 1 ?>" class="page-nav">
                        <i class="fas fa-chevron-left"></i> Trước
                    </a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page_layout=quanlyve&page=<?= $i ?>" 
                       class="page-link <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page_layout=quanlyve&page=<?= $page + 1 ?>" class="page-nav">
                        Sau <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>