<?php
require_once 'connect.php';

// Xử lý xóa đoàn tàu
if (isset($_GET['delete'])) {
    $ma_tau = $connetor->real_escape_string($_GET['delete']);
    $connetor->begin_transaction();
    try {
        $stmt = $connetor->prepare("DELETE FROM tau WHERE MaTau = ?");
        $stmt->bind_param("s", $ma_tau);
        $stmt->execute();
        $connetor->commit();
        header("Location: ?page_layout=quanlydoantau&thong_bao=Xóa đoàn tàu thành công!");
        exit();
    } catch (Exception $e) {
        $connetor->rollback();
        header("Location: ?page_layout=quanlydoantau&loi=" . urlencode($e->getMessage()));
        exit();
    }
}

// Xử lý thêm và sửa đoàn tàu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['them_doantau']) || isset($_POST['sua_doantau']))) {
    $so_hieu = $connetor->real_escape_string($_POST['so_hieu']);
    $loai_tau = $connetor->real_escape_string($_POST['loai_tau']);
    $suc_chua = (int)$_POST['suc_chua'];
    $trang_thai = $connetor->real_escape_string($_POST['trang_thai']);
    $ngay_san_xuat = $connetor->real_escape_string($_POST['ngay_san_xuat']);

    if (empty($so_hieu) || empty($loai_tau) || $suc_chua <= 0) {
        $loi = "Vui lòng nhập đầy đủ thông tin và sức chứa phải lớn hơn 0!";
    } else {
        $connetor->begin_transaction();
        try {
            if (isset($_POST['them_doantau'])) {
                $ma_tau = 'TAU' . time() . rand(100, 999);
                $stmt = $connetor->prepare("INSERT INTO tau (MaTau, SoHieu, LoaiTau, SucChua, TrangThaiHoatDong, NgaySanXuat) 
                                            VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssiss", $ma_tau, $so_hieu, $loai_tau, $suc_chua, $trang_thai, $ngay_san_xuat);
            } elseif (isset($_POST['sua_doantau'])) {
                $ma_tau = $connetor->real_escape_string($_POST['ma_tau']);
                $stmt = $connetor->prepare("UPDATE tau SET SoHieu = ?, LoaiTau = ?, SucChua = ?, TrangThaiHoatDong = ?, NgaySanXuat = ? 
                                            WHERE MaTau = ?");
                $stmt->bind_param("ssisss", $so_hieu, $loai_tau, $suc_chua, $trang_thai, $ngay_san_xuat, $ma_tau);
            }
            $stmt->execute();
            $connetor->commit();
            $thong_bao = isset($_POST['them_doantau']) ? "Thêm đoàn tàu thành công!" : "Cập nhật đoàn tàu thành công!";
            header("Location: ?page_layout=quanlydoantau&thong_bao=" . urlencode($thong_bao));
            exit();
        } catch (Exception $e) {
            $connetor->rollback();
            header("Location: ?page_layout=quanlydoantau&loi=" . urlencode($e->getMessage()));
            exit();
        }
    }
}

// Phân trang và tìm kiếm
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;
$search_term = isset($_GET['search_term']) ? $connetor->real_escape_string($_GET['search_term']) : '';

$sql_count = "SELECT COUNT(*) as total FROM tau" . ($search_term ? " WHERE SoHieu LIKE '%$search_term%' OR MaTau LIKE '%$search_term%' OR LoaiTau LIKE '%$search_term%'" : "");
$count_result = $connetor->query($sql_count);
$total = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

$sql = "SELECT * FROM tau" . ($search_term ? " WHERE SoHieu LIKE ? OR MaTau LIKE ? OR LoaiTau LIKE ?" : "") . " ORDER BY NgaySanXuat DESC LIMIT ?, ?";
$stmt = $connetor->prepare($sql);
if ($search_term) {
    $search_param = "%$search_term%";
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $start, $limit);
} else {
    $stmt->bind_param("ii", $start, $limit);
}
$stmt->execute();
$result = $stmt->get_result();

// Lấy dữ liệu sửa
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $connetor->real_escape_string($_GET['edit']);
    $stmt = $connetor->prepare("SELECT * FROM tau WHERE MaTau = ?");
    $stmt->bind_param("s", $edit_id);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $edit_data = $edit_result->fetch_assoc();
    }
}
?>

<div class="quanlydoantau-content">
    <!-- Thông báo -->
    <?php if (isset($_GET['thong_bao'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars(urldecode($_GET['thong_bao'])) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['loi'])): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars(urldecode($_GET['loi'])) ?></div>
    <?php endif; ?>

    <!-- Form tìm kiếm -->
    <div class="search-container">
        <form method="GET" action="">
            <input type="hidden" name="page_layout" value="quanlydoantau">
            <div class="search-box">
                <input type="text" name="search_term" placeholder="Tìm kiếm theo mã, số hiệu, loại tàu..." value="<?= htmlspecialchars($search_term) ?>">
                <button type="submit" class="btn btn-search"><i class="fas fa-search"></i> Tìm kiếm</button>
                <?php if ($search_term): ?>
                    <a href="?page_layout=quanlydoantau" class="btn btn-reset"><i class="fas fa-undo"></i> Đặt lại</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Form thêm/sửa đoàn tàu -->
    <div class="form-container">
        <h3><i class="fas fa-train"></i> <?= $edit_data ? 'Sửa Thông Tin Đoàn Tàu' : 'Thêm Đoàn Tàu Mới' ?></h3>
        <form method="POST">
            <?php if ($edit_data): ?>
                <input type="hidden" name="ma_tau" value="<?= htmlspecialchars($edit_data['MaTau']) ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Số Hiệu</label>
                    <input type="text" name="so_hieu" value="<?= $edit_data ? htmlspecialchars($edit_data['SoHieu']) : '' ?>" required>
                </div>
                <div class="form-group">
                    <label>Loại Tàu</label>
                    <input type="text" name="loai_tau" value="<?= $edit_data ? htmlspecialchars($edit_data['LoaiTau']) : '' ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Sức Chứa</label>
                    <input type="number" name="suc_chua" value="<?= $edit_data ? htmlspecialchars($edit_data['SucChua']) : '' ?>" required min="1">
                </div>
                <div class="form-group">
                    <label>Trạng Thái</label>
                    <select name="trang_thai" required>
                        <option value="HoatDong" <?= $edit_data && $edit_data['TrangThaiHoatDong'] == 'HoatDong' ? 'selected' : '' ?>>Hoạt Động</option>
                        <option value="BaoTri" <?= $edit_data && $edit_data['TrangThaiHoatDong'] == 'BaoTri' ? 'selected' : '' ?>>Bảo Trì</option>
                        <option value="Ngung" <?= $edit_data && $edit_data['TrangThaiHoatDong'] == 'Ngung' ? 'selected' : '' ?>>Ngừng</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Ngày Sản Xuất</label>
                <input type="date" name="ngay_san_xuat" value="<?= $edit_data ? htmlspecialchars($edit_data['NgaySanXuat']) : '' ?>" required>
            </div>
            <div class="form-actions">
                <button type="submit" name="<?= $edit_data ? 'sua_doantau' : 'them_doantau' ?>" class="btn btn-save">
                    <i class="fas fa-save"></i> <?= $edit_data ? 'Cập nhật' : 'Thêm' ?>
                </button>
                <?php if ($edit_data): ?>
                    <a href="?page_layout=quanlydoantau" class="btn btn-cancel"><i class="fas fa-times"></i> Hủy</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Danh sách đoàn tàu -->
    <div class="table-container">
        <h3><i class="fas fa-list"></i> Danh Sách Đoàn Tàu</h3>
        <?php if ($search_term && $result->num_rows > 0): ?>
            <p>Tìm thấy <?= $result->num_rows ?> kết quả cho "<?= htmlspecialchars($search_term) ?>"</p>
        <?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th>Mã Tàu</th>
                    <th>Số Hiệu</th>
                    <th>Loại Tàu</th>
                    <th>Sức Chứa</th>
                    <th>Trạng Thái</th>
                    <th>Ngày Sản Xuất</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['MaTau']) ?></td>
                            <td><?= htmlspecialchars($row['SoHieu']) ?></td>
                            <td><?= htmlspecialchars($row['LoaiTau']) ?></td>
                            <td><?= htmlspecialchars($row['SucChua']) ?></td>
                            <td><?= htmlspecialchars($row['TrangThaiHoatDong']) ?></td>
                            <td><?= htmlspecialchars($row['NgaySanXuat']) ?></td>
                            <td>
                                <a href="?page_layout=quanlydoantau&edit=<?= $row['MaTau'] ?>" class="btn-edit" title="Sửa"><i class="fas fa-edit"></i></a>
                                <a href="?page_layout=quanlydoantau&delete=<?= $row['MaTau'] ?>" class="btn-delete" title="Xóa" onclick="return confirm('Bạn có chắc muốn xóa đoàn tàu này?')"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty">Không có dữ liệu<?= $search_term ? " phù hợp với \"$search_term\"" : "" ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Phân trang -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page_layout=quanlydoantau&page=<?= $page - 1 ?>&search_term=<?= urlencode($search_term) ?>">« Trước</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page_layout=quanlydoantau&page=<?= $i ?>&search_term=<?= urlencode($search_term) ?>" <?= $i == $page ? 'class="active"' : '' ?>><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page_layout=quanlydoantau&page=<?= $page + 1 ?>&search_term=<?= urlencode($search_term) ?>">Sau »</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.quanlydoantau-content {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

h3 {
    color: #007bff;
    margin-bottom: 20px;
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-container {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    margin-bottom: 30px;
}

.search-box {
    display: flex;
    gap: 15px;
    align-items: center;
}

.search-box input[type="text"] {
    flex: 1;
    padding: 12px 15px;
    border: none;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.9);
    font-size: 16px;
    transition: all 0.3s ease;
}

.search-box input[type="text"]:focus {
    background: #fff;
    box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
    outline: none;
}

.btn {
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    color: white;
    font-size: 16px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-search {
    background: #28a745;
}

.btn-search:hover {
    background: #218838;
}

.btn-reset {
    background: #6c757d;
}

.btn-reset:hover {
    background: #5a6268;
}

.form-container, .table-container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 30px;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.form-group {
    flex: 1;
    min-width: 200px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    text-transform: uppercase;
}

.form-group input, .form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.form-group input:focus, .form-group select:focus {
    border-color: #007bff;
    outline: none;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn-save {
    background: #28a745;
}

.btn-save:hover {
    background: #218838;
}

.btn-cancel {
    background: #6c757d;
}

.btn-cancel:hover {
    background: #5a6268;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
}

.table-container table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.table-container th, .table-container td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
}

.table-container th {
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    text-transform: uppercase;
}

.table-container th:first-child { border-top-left-radius: 12px; }
.table-container th:last-child { border-top-right-radius: 12px; }

.table-container tr:hover {
    background: #f8f9fa;
}

.table-container .empty {
    text-align: center;
    padding: 20px;
    color: #6c757d;
}

.btn-edit, .btn-delete {
    padding: 8px 12px;
    border-radius: 4px;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-edit {
    background: #f39c12;
}

.btn-edit:hover {
    background: #e67e22;
}

.btn-delete {
    background: #e74c3c;
}

.btn-delete:hover {
    background: #c0392b;
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
    color: #007bff;
    border: 1px solid #ddd;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.pagination a:hover {
    background: #007bff;
    color: white;
}

.pagination a.active {
    background: #007bff;
    color: white;
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
    .search-box {
        flex-direction: column;
        gap: 10px;
    }
    .search-box input, .search-box button {
        width: 100%;
    }
}
</style>