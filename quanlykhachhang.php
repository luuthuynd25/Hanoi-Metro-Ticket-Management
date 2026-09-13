<?php
require_once 'connect.php';

// Khởi tạo biến
$thong_bao = '';
$loi = '';
$tim_kiem = isset($_GET['tim_kiem']) ? $connetor->real_escape_string($_GET['tim_kiem']) : '';

// Phân trang
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$sql_count = "SELECT COUNT(*) as total FROM KhachHang" . ($tim_kiem ? " WHERE HoTen LIKE '%$tim_kiem%' OR SoDienThoai LIKE '%$tim_kiem%' OR Email LIKE '%$tim_kiem%'" : "");
$count_result = $connetor->query($sql_count);
$total = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// Xử lý tìm kiếm
$sql = "SELECT k.*, vtk.SoDu 
        FROM KhachHang k
        LEFT JOIN ViTienKhachHang vtk ON k.MaKhachHang = vtk.MaKhachHang" .
        ($tim_kiem ? " WHERE k.HoTen LIKE ? OR k.SoDienThoai LIKE ? OR k.Email LIKE ?" : "") .
        " ORDER BY k.HoTen LIMIT ?, ?";
$stmt = $connetor->prepare($sql);
if ($tim_kiem) {
    $search_param = "%$tim_kiem%";
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $start, $limit);
} else {
    $stmt->bind_param("ii", $start, $limit);
}
$stmt->execute();
$result = $stmt->get_result();
$khach_hang = $result->fetch_all(MYSQLI_ASSOC);

// Xử lý thêm/sửa khách hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['luu_khach_hang'])) {
    $ma_kh = $_POST['ma_kh'] ? $connetor->real_escape_string($_POST['ma_kh']) : '';
    $ho_ten = $connetor->real_escape_string($_POST['ho_ten']);
    $sdt = $connetor->real_escape_string($_POST['sdt']);
    $email = $connetor->real_escape_string($_POST['email']);
    $so_du = (float)$_POST['so_du'];

    if (empty($ho_ten) || empty($sdt)) {
        $loi = "Vui lòng nhập đầy đủ họ tên và số điện thoại!";
    } elseif (!preg_match("/^[0-9]{10,11}$/", $sdt)) {
        $loi = "Số điện thoại không hợp lệ (10-11 số)!";
    } elseif ($so_du < 0) {
        $loi = "Số dư ví không thể âm!";
    } else {
        $connetor->begin_transaction();
        try {
            if (empty($ma_kh)) {
                $ma_kh = 'KH' . time() . rand(100, 999);
                $stmt = $connetor->prepare("INSERT INTO KhachHang (MaKhachHang, HoTen, SoDienThoai, Email) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $ma_kh, $ho_ten, $sdt, $email);
                $stmt->execute();

                $stmt = $connetor->prepare("INSERT INTO ViTienKhachHang (MaKhachHang, SoDu) VALUES (?, ?)");
                $stmt->bind_param("sd", $ma_kh, $so_du);
                $stmt->execute();
                $thong_bao = "Thêm khách hàng thành công!";
            } else {
                $stmt = $connetor->prepare("UPDATE KhachHang SET HoTen = ?, SoDienThoai = ?, Email = ? WHERE MaKhachHang = ?");
                $stmt->bind_param("ssss", $ho_ten, $sdt, $email, $ma_kh);
                $stmt->execute();

                $stmt = $connetor->prepare("UPDATE ViTienKhachHang SET SoDu = ? WHERE MaKhachHang = ?");
                $stmt->bind_param("ds", $so_du, $ma_kh);
                $stmt->execute();
                $thong_bao = "Cập nhật khách hàng thành công!";
            }
            $connetor->commit();
        } catch (Exception $e) {
            $connetor->rollback();
            $loi = "Lỗi: " . $e->getMessage();
        }
    }
}

// Xử lý xóa khách hàng
if (isset($_GET['xoa'])) {
    $ma_kh = $connetor->real_escape_string($_GET['xoa']);
    $connetor->begin_transaction();
    try {
        $stmt = $connetor->prepare("SELECT COUNT(*) AS total FROM Ve WHERE MaKhachHang = ?");
        $stmt->bind_param("s", $ma_kh);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()['total'] > 0) {
            throw new Exception("Không thể xóa khách hàng vì đã có vé liên quan!");
        }

        $stmt = $connetor->prepare("DELETE FROM ViTienKhachHang WHERE MaKhachHang = ?");
        $stmt->bind_param("s", $ma_kh);
        $stmt->execute();

        $stmt = $connetor->prepare("DELETE FROM KhachHang WHERE MaKhachHang = ?");
        $stmt->bind_param("s", $ma_kh);
        $stmt->execute();

        $connetor->commit();
        $thong_bao = "Xóa khách hàng thành công!";
    } catch (Exception $e) {
        $connetor->rollback();
        $loi = "Lỗi: " . $e->getMessage();
    }
}
?>

<div class="quanly-khachhang">
    <h2><i class="fas fa-users"></i> Quản Lý Khách Hàng</h2>

    <!-- Thông báo -->
    <?php if ($thong_bao): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($thong_bao) ?></div>
    <?php endif; ?>
    <?php if ($loi): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($loi) ?></div>
    <?php endif; ?>

    <!-- Tìm kiếm và nút thêm -->
    <div class="kh-controls">
        <form method="GET" class="search-form">
            <input type="hidden" name="page_layout" value="quanlykhachhang">
            <div class="input-group">
                <input type="text" name="tim_kiem" placeholder="Tìm theo tên, SĐT, email..." value="<?= htmlspecialchars($tim_kiem) ?>">
                <button type="submit" class="btn btn-search"><i class="fas fa-search"></i> Tìm</button>
                <?php if ($tim_kiem): ?>
                    <a href="?page_layout=quanlykhachhang" class="btn btn-reset"><i class="fas fa-undo"></i> Đặt lại</a>
                <?php endif; ?>
            </div>
        </form>
        <button class="btn btn-primary" onclick="openModal(null)"><i class="fas fa-plus"></i> Thêm Khách Hàng</button>
    </div>

    <!-- Danh sách khách hàng -->
    <div class="khachhang-list">
        <table>
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Mã KH</th>
                    <th>Họ Tên</th>
                    <th>SĐT</th>
                    <th>Email</th>
                    <th>Số Dư Ví</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($khach_hang)): ?>
                    <tr><td colspan="7" class="empty">Không có khách hàng nào<?= $tim_kiem ? " phù hợp với \"$tim_kiem\"" : "" ?></td></tr>
                <?php else: ?>
                    <?php foreach ($khach_hang as $index => $kh): ?>
                        <tr>
                            <td><?= $start + $index + 1 ?></td>
                            <td><?= htmlspecialchars($kh['MaKhachHang']) ?></td>
                            <td><?= htmlspecialchars($kh['HoTen']) ?></td>
                            <td><?= htmlspecialchars($kh['SoDienThoai']) ?></td>
                            <td><?= htmlspecialchars($kh['Email'] ?? 'N/A') ?></td>
                            <td><?= number_format($kh['SoDu'] ?? 0, 0, ',', '.') ?> VND</td>
                            <td class="actions">
                                <button class="btn-edit" onclick='openModal(<?= json_encode($kh) ?>)'><i class="fas fa-edit"></i></button>
                                <a href="?page_layout=quanlykhachhang&xoa=<?= $kh['MaKhachHang'] ?>" class="btn-delete" onclick="return confirm('Bạn có chắc muốn xóa khách hàng này?')"><i class="fas fa-trash-alt"></i></a>
                                <a href="?page_layout=quanlyve&ma_kh=<?= $kh['MaKhachHang'] ?>" class="btn-view"><i class="fas fa-ticket-alt"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Phân trang -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page_layout=quanlykhachhang&page=<?= $page - 1 ?>&tim_kiem=<?= urlencode($tim_kiem) ?>">« Trước</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page_layout=quanlykhachhang&page=<?= $i ?>&tim_kiem=<?= urlencode($tim_kiem) ?>" <?= $i == $page ? 'class="active"' : '' ?>><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page_layout=quanlykhachhang&page=<?= $page + 1 ?>&tim_kiem=<?= urlencode($tim_kiem) ?>">Sau »</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal thêm/sửa khách hàng -->
    <div id="khachhang-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">×</span>
            <h3 id="modal-title"><i class="fas fa-user"></i> Thêm Khách Hàng</h3>
            <form method="POST" id="khachhang-form">
                <input type="hidden" name="ma_kh" id="modal-ma-kh">
                <div class="form-row">
                    <div class="form-group">
                        <label>Họ Tên *</label>
                        <input type="text" name="ho_ten" id="modal-ho-ten" required>
                    </div>
                    <div class="form-group">
                        <label>Số Điện Thoại *</label>
                        <input type="tel" name="sdt" id="modal-sdt" required pattern="[0-9]{10,11}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="modal-email">
                    </div>
                    <div class="form-group">
                        <label>Số Dư Ví (VND)</label>
                        <input type="number" name="so_du" id="modal-so-du" step="1000" min="0" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="luu_khach_hang" class="btn btn-primary"><i class="fas fa-save"></i> Lưu</button>
                    <button type="button" class="btn btn-cancel" onclick="closeModal()"><i class="fas fa-times"></i> Hủy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.quanly-khachhang {
    padding: 30px;
    max-width: 1200px;
    margin: 0 auto;
    background: #f5f7fa;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

h2 {
    font-size: 28px;
    color: #007bff;
    margin-bottom: 30px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.kh-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    gap: 20px;
}

.search-form .input-group {
    display: flex;
    gap: 10px;
}

.search-form input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
    transition: all 0.3s ease;
}

.search-form input:focus {
    border-color: #007bff;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.2);
    outline: none;
}

.btn {
    padding: 12px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    color: #fff;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
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

.btn-primary {
    background: #007bff;
}

.btn-primary:hover {
    background: #0056b3;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.khachhang-list {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
}

.khachhang-list table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.khachhang-list th, .khachhang-list td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
}

.khachhang-list th {
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    text-transform: uppercase;
    font-weight: 600;
}

.khachhang-list th:first-child { border-top-left-radius: 10px; }
.khachhang-list th:last-child { border-top-right-radius: 10px; }

.khachhang-list tr:hover {
    background: #f8f9fa;
}

.khachhang-list .empty {
    text-align: center;
    padding: 20px;
    color: #6c757d;
}

.actions button, .actions a {
    padding: 8px 12px;
    border-radius: 4px;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
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

.btn-view {
    background: #17a2b8;
}

.btn-view:hover {
    background: #138496;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 6px;
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

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-content {
    background: #fff;
    margin: 100px auto;
    padding: 25px;
    width: 90%;
    max-width: 600px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    position: relative;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
    transition: color 0.3s ease;
}

.close-modal:hover {
    color: #e74c3c;
}

.modal h3 {
    font-size: 22px;
    color: #007bff;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
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
    color: #555;
    font-size: 14px;
    text-transform: uppercase;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
    transition: all 0.3s ease;
}

.form-group input:focus {
    border-color: #007bff;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.2);
    outline: none;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
}

.btn-cancel {
    background: #6c757d;
}

.btn-cancel:hover {
    background: #5a6268;
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
    border-radius: 6px;
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
    .kh-controls {
        flex-direction: column;
        gap: 15px;
    }
    .search-form .input-group {
        flex-direction: column;
        gap: 10px;
    }
    .search-form input, .search-form button {
        width: 100%;
    }
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<script>
function openModal(khachHang) {
    const modal = document.getElementById('khachhang-modal');
    const form = document.getElementById('khachhang-form');
    const title = document.getElementById('modal-title');
    
    if (khachHang) {
        title.innerHTML = '<i class="fas fa-user-edit"></i> Sửa Khách Hàng';
        document.getElementById('modal-ma-kh').value = khachHang.MaKhachHang;
        document.getElementById('modal-ho-ten').value = khachHang.HoTen;
        document.getElementById('modal-sdt').value = khachHang.SoDienThoai;
        document.getElementById('modal-email').value = khachHang.Email || '';
        document.getElementById('modal-so-du').value = khachHang.SoDu || 0;
    } else {
        title.innerHTML = '<i class="fas fa-user-plus"></i> Thêm Khách Hàng';
        form.reset();
        document.getElementById('modal-ma-kh').value = '';
    }
    
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('khachhang-modal').style.display = 'none';
    window.history.replaceState({}, document.title, window.location.pathname + '?page_layout=quanlykhachhang' + <?= $tim_kiem ? "'&tim_kiem=" . urlencode($tim_kiem) . "'" : "''" ?>);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    });
});
</script>