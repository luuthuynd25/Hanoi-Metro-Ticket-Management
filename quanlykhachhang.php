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
    display: flex;
    align-items: center;
    gap: 1rem;
}

.dashboard-title i {
    color: var(--secondary-color);
}

.alert {
    padding: 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-success {
    background-color: rgba(39, 174, 96, 0.1);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}

.alert-danger {
    background-color: rgba(231, 76, 60, 0.1);
    color: var(--danger-color);
    border: 1px solid var(--danger-color);
}

.controls-section {
    background: white;
    padding: 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.search-form {
    flex: 1;
    display: flex;
    gap: 1rem;
}

.search-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid #ddd;
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.search-input:focus {
    border-color: var(--secondary-color);
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.btn-primary {
    background: var(--secondary-color);
    color: white;
}

.btn-search {
    background: var(--primary-color);
    color: white;
}

.btn-reset {
    background: var(--light-color);
    color: var(--dark-color);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.table-container {
    background: white;
    padding: 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
    overflow-x: auto;
}

.customer-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.customer-table th {
    background: var(--primary-color);
    color: white;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
}

.customer-table th:first-child {
    border-top-left-radius: var(--border-radius);
}

.customer-table th:last-child {
    border-top-right-radius: var(--border-radius);
}

.customer-table td {
    padding: 1rem;
    border-bottom: 1px solid #eee;
}

.customer-table tbody tr:hover {
    background: #f8f9fa;
}

.customer-table .empty {
    text-align: center;
    padding: 2rem;
    color: #666;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    padding: 0.5rem;
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
    color: white;
}

.btn-edit {
    background: var(--warning-color);
}

.btn-delete {
    background: var(--danger-color);
}

.btn-view {
    background: var(--success-color);
}

.action-btn:hover {
    transform: translateY(-2px);
    opacity: 0.9;
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

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal-content {
    position: relative;
    background: white;
    width: 90%;
    max-width: 600px;
    margin: 2rem auto;
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
}

.close-modal {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--dark-color);
    transition: var(--transition);
}

.close-modal:hover {
    color: var(--danger-color);
}

.modal-title {
    color: var(--primary-color);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    font-weight: 500;
    color: var(--dark-color);
}

.form-input {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.form-input:focus {
    border-color: var(--secondary-color);
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.required {
    color: var(--danger-color);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
    }
    
    .controls-section {
        flex-direction: column;
    }
    
    .search-form {
        flex-direction: column;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1 class="dashboard-title">
            <i class="fas fa-users"></i>
            Quản Lý Khách Hàng
        </h1>
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

    <div class="controls-section">
        <form method="GET" class="search-form">
            <input type="hidden" name="page_layout" value="quanlykhachhang">
            <input type="text" 
                   name="tim_kiem" 
                   class="search-input" 
                   placeholder="Tìm kiếm theo tên, số điện thoại, email..." 
                   value="<?= htmlspecialchars($tim_kiem) ?>">
            <button type="submit" class="btn btn-search">
                <i class="fas fa-search"></i>
                Tìm kiếm
            </button>
            <?php if ($tim_kiem): ?>
                <a href="?page_layout=quanlykhachhang" class="btn btn-reset">
                    <i class="fas fa-undo"></i>
                    Đặt lại
                </a>
            <?php endif; ?>
        </form>
        <button class="btn btn-primary" onclick="openModal(null)">
            <i class="fas fa-plus"></i>
            Thêm khách hàng
        </button>
    </div>

    <div class="table-container">
        <table class="customer-table">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Mã KH</th>
                    <th>Họ Tên</th>
                    <th>Số điện thoại</th>
                    <th>Email</th>
                    <th>Số dư ví</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($khach_hang)): ?>
                    <tr>
                        <td colspan="7" class="empty">
                            <i class="fas fa-info-circle"></i>
                            Không có khách hàng nào<?= $tim_kiem ? " phù hợp với \"$tim_kiem\"" : "" ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($khach_hang as $index => $kh): ?>
                        <tr>
                            <td><?= $start + $index + 1 ?></td>
                            <td><?= htmlspecialchars($kh['MaKhachHang']) ?></td>
                            <td><?= htmlspecialchars($kh['HoTen']) ?></td>
                            <td><?= htmlspecialchars($kh['SoDienThoai']) ?></td>
                            <td><?= htmlspecialchars($kh['Email'] ?? 'N/A') ?></td>
                            <td><?= number_format($kh['SoDu'] ?? 0, 0, ',', '.') ?> VND</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn btn-edit" 
                                            onclick='openModal(<?= json_encode($kh) ?>)' 
                                            title="Sửa thông tin">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?page_layout=quanlykhachhang&xoa=<?= $kh['MaKhachHang'] ?>" 
                                       class="action-btn btn-delete" 
                                       onclick="return confirm('Bạn có chắc muốn xóa khách hàng này?')"
                                       title="Xóa khách hàng">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                    <a href="?page_layout=quanlyve&ma_kh=<?= $kh['MaKhachHang'] ?>" 
                                       class="action-btn btn-view"
                                       title="Xem vé">
                                        <i class="fas fa-ticket-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="custom-pagination">
                <?php if ($page > 1): ?>
                    <a href="?page_layout=quanlykhachhang&page=<?= $page - 1 ?>&tim_kiem=<?= urlencode($tim_kiem) ?>" 
                       class="page-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page_layout=quanlykhachhang&page=<?= $i ?>&tim_kiem=<?= urlencode($tim_kiem) ?>" 
                       class="page-btn <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page_layout=quanlykhachhang&page=<?= $page + 1 ?>&tim_kiem=<?= urlencode($tim_kiem) ?>" 
                       class="page-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="khachhang-modal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h3 class="modal-title" id="modal-title">
            <i class="fas fa-user"></i>
            <span>Thêm khách hàng mới</span>
        </h3>
        
        <form method="POST" id="khachhang-form">
            <input type="hidden" name="ma_kh" id="modal-ma-kh">
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">
                        Họ tên
                        <span class="required">*</span>
                    </label>
                    <input type="text" 
                           name="ho_ten" 
                           id="modal-ho-ten" 
                           class="form-input" 
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Số điện thoại
                        <span class="required">*</span>
                    </label>
                    <input type="tel" 
                           name="sdt" 
                           id="modal-sdt" 
                           class="form-input" 
                           pattern="[0-9]{10,11}" 
                           title="Số điện thoại phải có 10-11 số"
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" 
                           name="email" 
                           id="modal-email" 
                           class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Số dư ví
                        <span class="required">*</span>
                    </label>
                    <input type="number" 
                           name="so_du" 
                           id="modal-so-du" 
                           class="form-input" 
                           min="0" 
                           value="0" 
                           required>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" 
                        class="btn btn-reset" 
                        onclick="closeModal()">
                    <i class="fas fa-times"></i>
                    Hủy
                </button>
                <button type="submit" 
                        name="luu_khach_hang" 
                        class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Lưu
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(khachHang) {
    const modal = document.getElementById('khachhang-modal');
    const title = document.querySelector('#modal-title span');
    const form = document.getElementById('khachhang-form');
    
    if (khachHang) {
        title.textContent = 'Cập nhật thông tin khách hàng';
        form.querySelector('#modal-ma-kh').value = khachHang.MaKhachHang;
        form.querySelector('#modal-ho-ten').value = khachHang.HoTen;
        form.querySelector('#modal-sdt').value = khachHang.SoDienThoai;
        form.querySelector('#modal-email').value = khachHang.Email || '';
        form.querySelector('#modal-so-du').value = khachHang.SoDu || 0;
    } else {
        title.textContent = 'Thêm khách hàng mới';
        form.reset();
        form.querySelector('#modal-ma-kh').value = '';
    }
    
    modal.style.display = 'block';
    
    // Add animation
    const modalContent = modal.querySelector('.modal-content');
    modalContent.style.opacity = '0';
    modalContent.style.transform = 'translateY(-20px)';
    
    setTimeout(() => {
        modalContent.style.transition = 'all 0.3s ease';
        modalContent.style.opacity = '1';
        modalContent.style.transform = 'translateY(0)';
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('khachhang-modal');
    const modalContent = modal.querySelector('.modal-content');
    
    modalContent.style.opacity = '0';
    modalContent.style.transform = 'translateY(-20px)';
    
    setTimeout(() => {
        modal.style.display = 'none';
        modalContent.style.transition = '';
    }, 300);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('khachhang-modal');
    if (event.target == modal) {
        closeModal();
    }
}

// Add animation to alerts
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach((alert, index) => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            alert.style.transition = 'all 0.3s ease';
            alert.style.opacity = '1';
            alert.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php mysqli_close($connetor); ?>