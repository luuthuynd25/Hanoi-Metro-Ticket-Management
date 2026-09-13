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
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    <div class="particles-container" id="particlesContainer"></div>

    <!-- Thông báo -->
    <?php if (isset($_GET['thong_bao'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars(urldecode($_GET['thong_bao'])) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['loi'])): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars(urldecode($_GET['loi'])) ?></div>
    <?php endif; ?>

    <!-- Form tìm kiếm -->
    <div class="search-container reveal">
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
    <div class="form-container reveal">
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
    <div class="table-container reveal">
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
                        <tr class="table-row">
                            <td><?= htmlspecialchars($row['MaTau']) ?></td>
                            <td><?= htmlspecialchars($row['SoHieu']) ?></td>
                            <td><?= htmlspecialchars($row['LoaiTau']) ?></td>
                            <td><?= htmlspecialchars($row['SucChua']) ?></td>
                            <td><?= htmlspecialchars($row['TrangThaiHoatDong']) ?></td>
                            <td><?= htmlspecialchars($row['NgaySanXuat']) ?></td>
                            <td>
                                <div class="tooltip">
                                    <a href="?page_layout=quanlydoantau&edit=<?= $row['MaTau'] ?>" class="btn-edit" title="Sửa"><i class="fas fa-edit"></i></a>
                                    <span class="tooltip-text">Sửa thông tin tàu</span>
                                </div>
                                <div class="tooltip">
                                    <a href="?page_layout=quanlydoantau&delete=<?= $row['MaTau'] ?>" class="btn-delete" title="Xóa" onclick="return confirm('Bạn có chắc muốn xóa đoàn tàu này?')"><i class="fas fa-trash-alt"></i></a>
                                    <span class="tooltip-text">Xóa đoàn tàu</span>
                                </div>
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
                <a href="?page_layout=quanlydoantau&page=<?= $page - 1 ?>&search_term=<?= urlencode($search_term) ?>" class="page-nav"><i class="fas fa-chevron-left"></i> Trước</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page_layout=quanlydoantau&page=<?= $i ?>&search_term=<?= urlencode($search_term) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page_layout=quanlydoantau&page=<?= $page + 1 ?>&search_term=<?= urlencode($search_term) ?>" class="page-nav">Sau <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>

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

.quanlydoantau-content {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
    background: #f4f7fa;
}

h3 {
    color: var(--primary-color);
    margin-bottom: 20px;
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-container {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    padding: 20px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
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
    border-radius: var(--border-radius);
    background: var(--light-color);
    font-size: 16px;
    transition: var(--transition);
}

.search-box input[type="text"]:focus {
    background: #fff;
    box-shadow: 0 0 10px rgba(52, 152, 219, 0.3);
    outline: none;
}

.btn {
    padding: 12px 25px;
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    color: var(--light-color);
    font-size: 16px;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 5px;
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: var(--transition);
}

.btn:hover::before {
    left: 100%;
}

.btn-search {
    background: var(--secondary-color);
}

.btn-search:hover {
    background: #2980b9;
}

.btn-reset {
    background: #6c757d;
}

.btn-reset:hover {
    background: #5a6268;
}

.form-container, .table-container {
    background: white;
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
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
    color: var(--dark-color);
    text-transform: uppercase;
}

.form-group input, .form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #dfe6e9;
    border-radius: var(--border-radius);
    font-size: 16px;
    transition: var(--transition);
    background: var(--light-color);
}

.form-group input:focus, .form-group select:focus {
    border-color: var(--secondary-color);
    box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
    outline: none;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn-save {
    background: var(--secondary-color);
}

.btn-save:hover {
    background: #2980b9;
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
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: var(--box-shadow);
    animation: slideIn 0.5s ease;
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

.table-container table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.table-container th, .table-container td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #dfe6e9;
}

.table-container th {
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
    color: var(--light-color);
    text-transform: uppercase;
}

.table-container th:first-child { border-top-left-radius: var(--border-radius); }
.table-container th:last-child { border-top-right-radius: var(--border-radius); }

.table-container tr:hover {
    background: #f4f7fa;
}

.table-container .empty {
    text-align: center;
    padding: 20px;
    color: #6c757d;
}

.btn-edit, .btn-delete {
    padding: 8px 12px;
    border-radius: var(--border-radius);
    color: var(--light-color);
    text-decoration: none;
    transition: var(--transition);
}

.btn-edit {
    background: #f39c12;
}

.btn-edit:hover {
    background: #e67e22;
}

.btn-delete {
    background: var(--danger-color);
}

.btn-delete:hover {
    background: #c0392b;
}

.tooltip {
    position: relative;
    display: inline-block;
}

.tooltip .tooltip-text {
    visibility: hidden;
    width: 120px;
    background-color: var(--dark-color);
    color: var(--light-color);
    text-align: center;
    padding: 0.5rem;
    border-radius: 6px;
    position: absolute;
    z-index: 1;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0;
    transition: opacity 0.3s;
}

.tooltip:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}

.pagination {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 20px;
}

.page-link, .page-nav {
    padding: 10px 15px;
    text-decoration: none;
    color: var(--dark-color);
    border: 1px solid #dfe6e9;
    border-radius: var(--border-radius);
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 5px;
}

.page-link:hover,
.page-link.active,
.page-nav:hover {
    background: var(--secondary-color);
    color: var(--light-color);
    border-color: var(--secondary-color);
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease;
}

.loading-overlay.active {
    opacity: 1;
    visibility: visible;
}

.spinner {
    border: 5px solid var(--light-color);
    border-top: 5px solid var(--secondary-color);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
}

.particles-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 9998;
}

.particle {
    position: absolute;
    background: var(--secondary-color);
    border-radius: 50%;
    opacity: 0;
}

.reveal {
    opacity: 0;
    transform: translateY(20px);
}

.reveal.visible {
    opacity: 1;
    transform: translateY(0);
    transition: all 0.5s ease;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeInScale {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes particleRise {
    0% {
        opacity: 0.8;
        transform: translateY(0);
    }
    100% {
        opacity: 0;
        transform: translateY(-100px);
    }
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Loading overlay for actions
    const loadingOverlay = document.getElementById('loadingOverlay');
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            loadingOverlay.classList.add('active');
        });
    });

    const actionLinks = document.querySelectorAll('.btn-edit, .btn-delete, .page-link, .page-nav');
    actionLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.classList.contains('btn-delete') && !confirm('Bạn có chắc muốn xóa đoàn tàu này?')) {
                e.preventDefault();
                return;
            }
            loadingOverlay.classList.add('active');
        });
    });

    // Particle effect on success
    if (document.querySelector('.alert-success')) {
        createParticles();
    }

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

    // Ripple effect for buttons
    const buttons = document.querySelectorAll('.btn, .btn-edit, .btn-delete, .page-link, .page-nav');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const rect = button.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            button.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // Fade-in and scale animation for table rows
    const tableRows = document.querySelectorAll('.table-row');
    tableRows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'scale(0.95)';
        setTimeout(() => {
            row.style.transition = 'all 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'scale(1)';
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