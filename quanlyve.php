<?php
require_once 'connect.php';

// Kiểm tra kết nối
if (!$connetor) {
    die("Kết nối cơ sở dữ liệu thất bại: " . mysqli_connect_error());
}

// Khởi tạo biến
$thong_bao = '';
$loi = '';
$ve_list = [];
$search_term = '';

// Phân trang
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Giá vé cố định (đồng bộ với muave.php và banve.php)
$gia_ve_options = [
    'Luot' => 12000,
    'Ngay' => 24000,
    'Thang' => 200000
];

// Thống kê số lượng vé đã bán
$sql_stats = "SELECT 
                COUNT(CASE WHEN TrangThai = 'DaThanhToan' THEN 1 END) AS da_ban,
                COUNT(CASE WHEN TrangThai = 'ChuaThanhToan' THEN 1 END) AS chua_thanh_toan,
                COUNT(CASE WHEN TrangThai = 'DaHuy' THEN 1 END) AS da_huy
              FROM Ve";
$result_stats = $connetor->query($sql_stats);
if (!$result_stats) {
    die("Lỗi truy vấn thống kê: " . $connetor->error);
}
$stats = $result_stats->fetch_assoc();

// Đếm tổng số vé để phân trang
$sql_count = "SELECT COUNT(*) as total FROM Ve v 
              LEFT JOIN KhachHang k ON v.MaKhachHang = k.MaKhachHang";
$count_params = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_ve'])) {
    $search_term = $connetor->real_escape_string($_POST['search_term']);
    $sql_count .= " WHERE v.MaVe LIKE ? OR k.HoTen LIKE ? OR k.SoDienThoai LIKE ?";
    $search_param = "%$search_term%";
    $count_params = [$search_param, $search_param, $search_param];
}

$stmt_count = $connetor->prepare($sql_count);
if (!$stmt_count) {
    die("Lỗi prepare truy vấn đếm: " . $connetor->error);
}
if (!empty($count_params)) {
    $stmt_count->bind_param("sss", ...$count_params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);
$stmt_count->close();

// Xử lý tìm kiếm vé
$base_query = "SELECT v.MaVe, v.MaKhachHang, v.NgayMua, v.LoaiVe, v.NgaySuDung, v.PhuongThucThanhToan, v.TrangThai,
                      k.HoTen, k.SoDienThoai
               FROM Ve v
               LEFT JOIN KhachHang k ON v.MaKhachHang = k.MaKhachHang";
$params = [];
$types = "ii"; // Cho LIMIT

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_ve'])) {
    $search_term = $connetor->real_escape_string($_POST['search_term']);
    $base_query .= " WHERE v.MaVe LIKE ? OR k.HoTen LIKE ? OR k.SoDienThoai LIKE ?";
    $search_param = "%$search_term%";
    $params = [$search_param, $search_param, $search_param];
    $types = "sss" . $types;
}

$base_query .= " ORDER BY v.NgayMua DESC LIMIT ?, ?";
$params[] = $start;
$params[] = $limit;

$stmt = $connetor->prepare($base_query);
if (!$stmt) {
    die("Lỗi prepare truy vấn danh sách vé: " . $connetor->error);
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result_ve = $stmt->get_result();
if ($result_ve && $result_ve->num_rows > 0) {
    $ve_list = $result_ve->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

// Xử lý hoàn tiền
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hoan_tien'])) {
    $ma_ve = $connetor->real_escape_string($_POST['ma_ve']);
    $connetor->begin_transaction();
    try {
        // Kiểm tra trạng thái vé và thông tin liên quan
        $stmt = $connetor->prepare("SELECT TrangThai, PhuongThucThanhToan, MaKhachHang, LoaiVe, NgaySuDung 
                                    FROM Ve WHERE MaVe = ?");
        if (!$stmt) {
            throw new Exception("Lỗi prepare truy vấn kiểm tra vé: " . $connetor->error);
        }
        $stmt->bind_param("s", $ma_ve);
        $stmt->execute();
        $ve_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ve_info) {
            throw new Exception("Không tìm thấy vé!");
        }
        if ($ve_info['TrangThai'] != 'DaThanhToan') {
            throw new Exception("Vé không thể hoàn tiền do trạng thái không hợp lệ!");
        }
        if (strtotime($ve_info['NgaySuDung']) < strtotime(date('Y-m-d'))) {
            throw new Exception("Không thể hoàn tiền vì ngày sử dụng đã qua!");
        }

        // Tính số tiền hoàn dựa trên loại vé
        $loai_ve = $ve_info['LoaiVe'];
        if (!isset($gia_ve_options[$loai_ve])) {
            throw new Exception("Loại vé không hợp lệ!");
        }
        $so_tien = $gia_ve_options[$loai_ve];

        // Cập nhật trạng thái vé thành "DaHuy"
        $stmt = $connetor->prepare("UPDATE Ve SET TrangThai = 'DaHuy' WHERE MaVe = ?");
        if (!$stmt) {
            throw new Exception("Lỗi prepare cập nhật trạng thái vé: " . $connetor->error);
        }
        $stmt->bind_param("s", $ma_ve);
        $stmt->execute();
        $stmt->close();

        // Nếu thanh toán bằng ví điện tử, hoàn tiền vào ví
        if ($ve_info['PhuongThucThanhToan'] == 'ViDienTu') {
            $ma_khach_hang = $ve_info['MaKhachHang'];
            $stmt = $connetor->prepare("UPDATE ViTienKhachHang SET SoDu = SoDu + ? WHERE MaKhachHang = ?");
            if (!$stmt) {
                throw new Exception("Lỗi prepare cập nhật ví: " . $connetor->error);
            }
            $stmt->bind_param("ds", $so_tien, $ma_khach_hang);
            $stmt->execute();
            $stmt->close();

            // Ghi lại giao dịch hoàn tiền
            $ma_gd = 'GD' . time() . rand(100, 999);
            $ngay_gd = date('Y-m-d H:i:s');
            $noi_dung = "Hoàn tiền vé $ma_ve";
            $stmt = $connetor->prepare("INSERT INTO GiaoDichVi (MaGiaoDich, MaKhachHang, SoTien, LoaiGiaoDich, NgayGiaoDich, NoiDung) 
                                        VALUES (?, ?, ?, 'HoanTien', ?, ?)");
            if (!$stmt) {
                throw new Exception("Lỗi prepare ghi giao dịch: " . $connetor->error);
            }
            $stmt->bind_param("ssdss", $ma_gd, $ma_khach_hang, $so_tien, $ngay_gd, $noi_dung);
            $stmt->execute();
            $stmt->close();
        }

        // Thêm vào bảng HuyVe
        $ma_huy = 'HV' . time() . rand(100, 999);
        $ngay_huy = date('Y-m-d H:i:s');
        $stmt = $connetor->prepare("INSERT INTO HuyVe (MaHuy, MaVe, NgayHuy, LyDo, HoanTien, TrangThaiXuLy) 
                                    VALUES (?, ?, ?, 'Khách yêu cầu', 'Co', 'DaXuLy')");
        if (!$stmt) {
            throw new Exception("Lỗi prepare ghi hủy vé: " . $connetor->error);
        }
        $stmt->bind_param("sss", $ma_huy, $ma_ve, $ngay_huy);
        $stmt->execute();
        $stmt->close();

        $connetor->commit();
        $thong_bao = "Hoàn tiền vé $ma_ve thành công! Số tiền hoàn: " . number_format($so_tien, 0, ',', '.') . " VND";

        // Cập nhật lại danh sách vé
        $stmt = $connetor->prepare($base_query);
        if (!$stmt) {
            die("Lỗi prepare truy vấn danh sách vé sau hoàn tiền: " . $connetor->error);
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result_ve = $stmt->get_result();
        if ($result_ve && $result_ve->num_rows > 0) {
            $ve_list = $result_ve->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    } catch (Exception $e) {
        $connetor->rollback();
        $loi = "Lỗi: " . $e->getMessage();
    }
}
?>

<!-- Nội dung chính để include vào admin.php -->
<div class="quan-ly-ve-content">
    <!-- Thông báo -->
    <?php if (!empty($thong_bao)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($thong_bao) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($loi)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($loi) ?>
        </div>
    <?php endif; ?>

    <!-- Thống kê vé -->
    <div class="stats-section">
        <h3><i class="fas fa-chart-bar"></i> Thống Kê Vé</h3>
        <div class="stats-row">
            <div class="stat-item">
                <span>Đã bán</span>
                <strong><?= $stats['da_ban'] ?? 0 ?></strong>
            </div>
            <div class="stat-item">
                <span>Chưa thanh toán</span>
                <strong><?= $stats['chua_thanh_toan'] ?? 0 ?></strong>
            </div>
            <div class="stat-item">
                <span>Đã hủy</span>
                <strong><?= $stats['da_huy'] ?? 0 ?></strong>
            </div>
        </div>
    </div>

    <!-- Tìm kiếm vé -->
    <div class="search-section">
        <h3><i class="fas fa-search"></i> Tìm Kiếm Vé</h3>
        <form method="POST" class="search-form">
            <div class="form-row">
                <div class="form-group">
                    <input type="text" name="search_term" class="form-control" placeholder="Nhập mã vé, tên khách hàng hoặc SĐT" value="<?= htmlspecialchars($search_term) ?>">
                </div>
                <button type="submit" name="search_ve" class="btn btn-primary">
                    <i class="fas fa-search"></i> Tìm
                </button>
                <?php if ($search_term): ?>
                    <a href="?page_layout=quanlyve" class="btn btn-reset"><i class="fas fa-undo"></i> Đặt lại</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Danh sách vé -->
    <div class="ve-list-section">
        <h3><i class="fas fa-ticket-alt"></i> Danh Sách Vé</h3>
        <?php if (!empty($ve_list)): ?>
            <table class="ve-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã Vé</th>
                        <th>Tên Khách Hàng</th>
                        <th>SĐT</th>
                        <th>Loại Vé</th>
                        <th>Ngày Mua</th>
                        <th>Ngày Sử Dụng</th>
                        <th>Phương Thức</th>
                        <th>Trạng Thái</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ve_list as $index => $ve): ?>
                        <tr>
                            <td><?= $start + $index + 1 ?></td>
                            <td><?= htmlspecialchars($ve['MaVe']) ?></td>
                            <td><?= htmlspecialchars($ve['HoTen'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($ve['SoDienThoai'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($ve['LoaiVe']) ?></td>
                            <td><?= date('H:i d/m/Y', strtotime($ve['NgayMua'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($ve['NgaySuDung'])) ?></td>
                            <td><?= htmlspecialchars($ve['PhuongThucThanhToan']) ?></td>
                            <td>
                                <span class="status <?= strtolower(str_replace(' ', '', $ve['TrangThai'])) ?>">
                                    <?= htmlspecialchars($ve['TrangThai']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($ve['TrangThai'] == 'DaThanhToan' && strtotime($ve['NgaySuDung']) >= strtotime(date('Y-m-d'))): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="ma_ve" value="<?= $ve['MaVe'] ?>">
                                        <button type="submit" name="hoan_tien" class="btn btn-danger"
                                                onclick="return confirm('Bạn có chắc muốn hoàn tiền vé này?');">
                                            <i class="fas fa-undo"></i> Hoàn tiền
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Phân trang -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page_layout=quanlyve&page=<?= $page - 1 ?>">« Trước</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page_layout=quanlyve&page=<?= $i ?>" <?= $i == $page ? 'class="active"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page_layout=quanlyve&page=<?= $page + 1 ?>">Sau »</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>Không tìm thấy vé nào! <?= $search_term ? "Hãy thử tìm kiếm với từ khóa khác." : "Có vẻ chưa có vé nào được tạo." ?></p>
        <?php endif; ?>
    </div>
</div>

<style>
.quan-ly-ve-content {
    padding: 30px;
    max-width: 1200px;
    margin: 0 auto;
    background: #f5f7fa;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

h3 {
    font-size: 22px;
    color: #1E3A8A;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.stats-section, .search-section, .ve-list-section {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
}

.stats-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.stat-item {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    flex: 1;
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.stat-item span {
    display: block;
    color: #6b7280;
    font-size: 16px;
}

.stat-item strong {
    font-size: 28px;
    color: #28a745;
}

.search-form .form-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.form-group {
    flex: 1;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #3B82F6;
    box-shadow: 0 0 8px rgba(59, 130, 246, 0.3);
    outline: none;
}

.btn {
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    color: #fff;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(90deg, #1E3A8A, #3B82F6);
}

.btn-primary:hover {
    background: linear-gradient(90deg, #1e40af, #2563eb);
    box-shadow: 0 6px 15px rgba(59, 130, 246, 0.4);
}

.btn-reset {
    background: #6c757d;
}

.btn-reset:hover {
    background: #5a6268;
}

.btn-danger {
    background: #e74c3c;
}

.btn-danger:hover {
    background: #c0392b;
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
}

.ve-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.ve-table th, .ve-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
}

.ve-table th {
    background: linear-gradient(45deg, #1E3A8A, #3B82F6);
    color: white;
    text-transform: uppercase;
    font-weight: 600;
}

.ve-table th:first-child { border-top-left-radius: 10px; }
.ve-table th:last-child { border-top-right-radius: 10px; }

.ve-table tr:hover {
    background: #f8f9fa;
}

.status {
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 14px;
    color: white;
    text-transform: capitalize;
}

.status.dathanhtoan {
    background: #28a745;
}

.status.chuathanhtoan {
    background: #f39c12;
    color: #fff;
}

.status.dahuy {
    background: #e74c3c;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
}

.pagination {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 20px;
}

.pagination a {
    padding: 10px 15px;
    text-decoration: none;
    color: #1E3A8A;
    border: 1px solid #ddd;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.pagination a:hover {
    background: #1E3A8A;
    color: white;
}

.pagination a.active {
    background: #1E3A8A;
    color: white;
}

@media (max-width: 768px) {
    .stats-row {
        flex-direction: column;
    }
    .search-form .form-row {
        flex-direction: column;
        gap: 10px;
    }
    .ve-table {
        font-size: 14px;
    }
}
</style>