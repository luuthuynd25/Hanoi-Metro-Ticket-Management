<?php
require_once 'connect.php';

// Xử lý xóa ga tàu
if (isset($_GET['delete'])) {
    $ma_ga = $connetor->real_escape_string($_GET['delete']);
    $connetor->begin_transaction();
    try {
        $stmt = $connetor->prepare("DELETE FROM ga WHERE MaGa = ?");
        $stmt->bind_param("s", $ma_ga);
        $stmt->execute();
        $connetor->commit();
        header("Location: ?page_layout=quanlygatau&thong_bao=Xóa ga tàu thành công!");
        exit();
    } catch (Exception $e) {
        $connetor->rollback();
        header("Location: ?page_layout=quanlygatau&loi=" . urlencode($e->getMessage()));
        exit();
    }
}

// Xử lý thêm và sửa ga tàu
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ten_ga = $connetor->real_escape_string($_POST['ten_ga']);
    $vi_tri_dia_ly = $connetor->real_escape_string($_POST['vi_tri_dia_ly']);
    $so_san_ga = (int)$_POST['so_san_ga'];
    $trang_thai = $connetor->real_escape_string($_POST['trang_thai']);
    
    $connetor->begin_transaction();
    try {
        if (isset($_POST['them_ga'])) {
            $ma_ga = 'GA' . time() . rand(100, 999);
            $stmt = $connetor->prepare("INSERT INTO ga (MaGa, TenGa, ViTriDiaLy, SoSanGa, TrangThai) 
                                        VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $ma_ga, $ten_ga, $vi_tri_dia_ly, $so_san_ga, $trang_thai);
        } elseif (isset($_POST['sua_ga'])) {
            $ma_ga = $connetor->real_escape_string($_POST['ma_ga']);
            $stmt = $connetor->prepare("UPDATE ga SET TenGa = ?, ViTriDiaLy = ?, SoSanGa = ?, TrangThai = ? WHERE MaGa = ?");
            $stmt->bind_param("ssiss", $ten_ga, $vi_tri_dia_ly, $so_san_ga, $trang_thai, $ma_ga);
        }
        $stmt->execute();
        $connetor->commit();
        $thong_bao = isset($_POST['them_ga']) ? "Thêm ga tàu thành công!" : "Cập nhật ga tàu thành công!";
        header("Location: ?page_layout=quanlygatau&thong_bao=" . urlencode($thong_bao));
        exit();
    } catch (Exception $e) {
        $connetor->rollback();
        header("Location: ?page_layout=quanlygatau&loi=" . urlencode($e->getMessage()));
        exit();
    }
}

// Phân trang
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$sql_count = "SELECT COUNT(*) as total FROM ga";
$count_result = $connetor->query($sql_count);
$total = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// Xử lý tìm kiếm
$search_term = "";
if (isset($_GET['search_term'])) {
    $search_term = $connetor->real_escape_string($_GET['search_term']);
    $sql = "SELECT * FROM ga WHERE 
            MaGa LIKE ? OR 
            TenGa LIKE ? OR 
            ViTriDiaLy LIKE ? OR 
            SoSanGa LIKE ?
            LIMIT ?, ?";
    $stmt = $connetor->prepare($sql);
    $search_param = "%$search_term%";
    $stmt->bind_param("ssssii", $search_param, $search_param, $search_param, $search_param, $start, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM ga LIMIT ?, ?";
    $stmt = $connetor->prepare($sql);
    $stmt->bind_param("ii", $start, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<div class="container">
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    <div class="particles-container" id="particlesContainer"></div>

    <h2><i class="fas fa-subway"></i> Quản Lý Ga Tàu</h2>

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
            <input type="hidden" name="page_layout" value="quanlygatau">
            <div class="search-box">
                <input type="text" name="search_term" placeholder="Tìm kiếm ga tàu..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-search"><i class="fas fa-search"></i> Tìm Kiếm</button>
                <?php if (!empty($search_term)) { ?>
                    <a href="?page_layout=quanlygatau" class="btn btn-reset"><i class="fas fa-undo"></i> Đặt Lại</a>
                <?php } ?>
            </div>
        </form>
    </div>
    
    <!-- Form thêm ga tàu -->
    <div class="form-container reveal">
        <h3><i class="fas fa-plus-circle"></i> Thêm Ga Tàu Mới</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label>Tên Ga</label>
                <input type="text" name="ten_ga" required>
            </div>
            <div class="form-group">
                <label>Vị Trí Địa Lý</label>
                <input type="text" name="vi_tri_dia_ly" required>
            </div>
            <div class="form-group">
                <label>Số Sân Ga</label>
                <input type="number" name="so_san_ga" required min="1">
            </div>
            <div class="form-group">
                <label>Trạng Thái</label>
                <select name="trang_thai" required>
                    <option value="HoatDong">Hoạt Động</option>
                    <option value="BaoTri">Bảo Trì</option>
                    <option value="Ngung">Ngừng</option>
                </select>
            </div>
            <button type="submit" name="them_ga" class="btn btn-save"><i class="fas fa-save"></i> Thêm Ga</button>
        </form>
    </div>

    <!-- Bảng danh sách ga tàu -->
    <div class="table-container reveal">
        <h3><i class="fas fa-list"></i> <?php echo !empty($search_term) ? "Kết Quả Tìm Kiếm: \"$search_term\"" : "Danh Sách Ga Tàu"; ?></h3>
        <?php if (!empty($search_term) && $result->num_rows > 0) { ?>
            <p>Tìm thấy <?php echo $result->num_rows; ?> kết quả</p>
        <?php } ?>
        <table>
            <thead>
                <tr>
                    <th>Mã Ga</th>
                    <th>Tên Ga</th>
                    <th>Vị Trí Địa Lý</th>
                    <th>Số Sân Ga</th>
                    <th>Trạng Thái</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr class='table-row'>";
                        echo "<td>" . htmlspecialchars($row['MaGa']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['TenGa']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['ViTriDiaLy']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['SoSanGa']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['TrangThai']) . "</td>";
                        echo "<td>
                            <div class='tooltip'>
                                <a href='#' class='btn-edit' data-maga='{$row['MaGa']}' 
                                   data-tenga='{$row['TenGa']}' data-vitri='{$row['ViTriDiaLy']}' 
                                   data-sosanga='{$row['SoSanGa']}' data-trangthai='{$row['TrangThai']}'>
                                   <i class='fas fa-edit'></i>
                                </a>
                                <span class='tooltip-text'>Sửa thông tin ga</span>
                            </div>
                            <div class='tooltip'>
                                <a href='?page_layout=quanlygatau&delete=" . $row['MaGa'] . "' class='btn-delete' onclick='return confirm(\"Bạn có chắc muốn xóa?\")'>
                                    <i class='fas fa-trash-alt'></i>
                                </a>
                                <span class='tooltip-text'>Xóa ga tàu</span>
                            </div>
                        </td>";
                        echo "</tr>";
                    }
                } else {
                    if (!empty($search_term)) {
                        echo "<tr><td colspan='6'>Không tìm thấy kết quả nào cho \"$search_term\"</td></tr>";
                    } else {
                        echo "<tr><td colspan='6'>Chưa có dữ liệu</td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>

        <!-- Phân trang -->
        <div class="pagination">
            <?php
            if ($page > 1) {
                echo "<a href='?page_layout=quanlygatau&page=" . ($page - 1) . "&search_term=$search_term' class='page-nav'><i class='fas fa-chevron-left'></i> Trang trước</a>";
            }
            for ($i = 1; $i <= $total_pages; $i++) {
                echo "<a href='?page_layout=quanlygatau&page=$i&search_term=$search_term' class='page-link" . ($i == $page ? " active" : "") . "'>$i</a>";
            }
            if ($page < $total_pages) {
                echo "<a href='?page_layout=quanlygatau&page=" . ($page + 1) . "&search_term=$search_term' class='page-nav'>Trang sau <i class='fas fa-chevron-right'></i></a>";
            }
            ?>
        </div>
    </div>
</div>

<!-- Modal chỉnh sửa -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <span class="close-modal">×</span>
        <h3><i class="fas fa-edit"></i> Chỉnh Sửa Ga Tàu</h3>
        <form method="POST" action="">
            <input type="hidden" name="ma_ga" id="modal-ma-ga">
            <div class="form-group">
                <label>Tên Ga</label>
                <input type="text" name="ten_ga" id="modal-ten-ga" required>
            </div>
            <div class="form-group">
                <label>Vị Trí Địa Lý</label>
                <input type="text" name="vi_tri_dia_ly" id="modal-vi-tri" required>
            </div>
            <div class="form-group">
                <label>Số Sân Ga</label>
                <input type="number" name="so_san_ga" id="modal-so-san-ga" required min="1">
            </div>
            <div class="form-group">
                <label>Trạng Thái</label>
                <select name="trang_thai" id="modal-trang-thai" required>
                    <option value="HoatDong">Hoạt Động</option>
                    <option value="BaoTri">Bảo Trì</option>
                    <option value="Ngung">Ngừng</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="submit" name="sua_ga" class="btn btn-save"><i class="fas fa-save"></i> Cập Nhật</button>
                <button type="button" class="btn btn-cancel close-modal-btn"><i class="fas fa-times"></i> Hủy</button>
            </div>
        </form>
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

.container {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
    background: #f4f7fa;
}

h2 {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 25px;
    text-transform: uppercase;
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
 Cina: 1;
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
    font-weight: 600;
    text-transform: uppercase;
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

.form-container, .table-container {
    background: white;
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 30px;
}

.form-container h3, .table-container h3 {
    font-size: 22px;
    color: var(--primary-color);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--dark-color);
    font-weight: 500;
    text-transform: uppercase;
}

.form-group input, .form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #dfe6e9;
    border-radius: var(--border-radius);
    background: var(--light-color);
    font-size: 16px;
    transition: var(--transition);
}

.form-group input:focus, .form-group select:focus {
    border-color: var(--secondary-color);
    background: #fff;
    box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
    outline: none;
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

th, td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #dfe6e9;
}

th {
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
    color: var(--light-color);
    font-weight: 600;
    text-transform: uppercase;
}

th:first-child { border-top-left-radius: var(--border-radius); }
th:last-child { border-top-right-radius: var(--border-radius); }

tbody tr:hover {
    background: #f4f7fa;
}

.btn-edit, .btn-delete {
    padding: 8px 15px;
    border-radius: var(--border-radius);
    text-decoration: none;
    color: var(--light-color);
    font-size: 14px;
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

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
}

.modal-content {
    background: white;
    margin: 80px auto;
    padding: 30px;
    width: 90%;
    max-width: 550px;
    border-radius: var(--border-radius);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    transform: scale(0.7);
    opacity: 0;
    transition: all 0.3s ease;
}

.modal-content.active {
    transform: scale(1);
    opacity: 1;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 30px;
    color: var(--dark-color);
    cursor: pointer;
}

.close-modal:hover {
    color: var(--danger-color);
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.pagination {
    margin-top: 25px;
    display: flex;
    gap: 10px;
    justify-content: center;
}

.page-link, .page-nav {
    padding: 10px 20px;
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
    .search-box {
        flex-direction: column;
        gap: 10px;
    }
    .search-box input, .search-box button {
        width: 100%;
    }
    table {
        font-size: 14px;
    }
    th, td {
        padding: 10px;
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
            if (this.classList.contains('btn-delete') && !confirm('Bạn có chắc muốn xóa?')) {
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

    // Modal animation
    const modal = document.getElementById('editModal');
    const modalContent = modal.querySelector('.modal-content');
    const editButtons = document.querySelectorAll('.btn-edit');

    editButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const maGa = this.getAttribute('data-maga');
            const tenGa = this.getAttribute('data-tenga');
            const viTri = this.getAttribute('data-vitri');
            const soSanGa = this.getAttribute('data-sosanga');
            const trangThai = this.getAttribute('data-trangthai');

            document.getElementById('modal-ma-ga').value = maGa;
            document.getElementById('modal-ten-ga').value = tenGa;
            document.getElementById('modal-vi-tri').value = viTri;
            document.getElementById('modal-so-san-ga').value = soSanGa;
            document.getElementById('modal-trang-thai').value = trangThai;

            modal.style.display = 'block';
            setTimeout(() => {
                modalContent.classList.add('active');
            }, 10);
        });
    });

    const closeModal = () => {
        modalContent.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    };

    document.querySelectorAll('.close-modal, .close-modal-btn').forEach(el => {
        el.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

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