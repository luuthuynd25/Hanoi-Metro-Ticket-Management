<?php
require_once 'connect.php';

// Xử lý xóa ga tàu
if (isset($_GET['delete'])) {
    $ma_ga = $connetor->real_escape_string($_GET['delete']);
    $sql = "DELETE FROM ga WHERE MaGa = ?";
    $stmt = $connetor->prepare($sql);
    $stmt->bind_param("s", $ma_ga);
    $stmt->execute();
    header("Location: ?page_layout=quanlygatau");
    exit();
}

// Xử lý thêm và sửa ga tàu
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ten_ga = $connetor->real_escape_string($_POST['ten_ga']);
    $vi_tri_dia_ly = $connetor->real_escape_string($_POST['vi_tri_dia_ly']);
    $so_san_ga = (int)$_POST['so_san_ga'];
    $trang_thai = $connetor->real_escape_string($_POST['trang_thai']);
    
    if (isset($_POST['them_ga'])) {
        $ma_ga = 'GA' . time() . rand(100, 999); // Tạo mã ga duy nhất
        $sql = "INSERT INTO ga (MaGa, TenGa, ViTriDiaLy, SoSanGa, TrangThai) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $connetor->prepare($sql);
        $stmt->bind_param("sssis", $ma_ga, $ten_ga, $vi_tri_dia_ly, $so_san_ga, $trang_thai);
    } elseif (isset($_POST['sua_ga'])) {
        $ma_ga = $connetor->real_escape_string($_POST['ma_ga']);
        $sql = "UPDATE ga SET TenGa = ?, ViTriDiaLy = ?, SoSanGa = ?, TrangThai = ? WHERE MaGa = ?";
        $stmt = $connetor->prepare($sql);
        $stmt->bind_param("ssiss", $ten_ga, $vi_tri_dia_ly, $so_san_ga, $trang_thai, $ma_ga);
    }
    $stmt->execute();
    header("Location: ?page_layout=quanlygatau");
    exit();
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
    <h2>Quản Lý Ga Tàu</h2>
    
    <!-- Form tìm kiếm -->
    <div class="search-container">
        <form method="GET" action="">
            <input type="hidden" name="page_layout" value="quanlygatau">
            <div class="search-box">
                <input type="text" name="search_term" placeholder="Tìm kiếm ga tàu..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-search">Tìm Kiếm</button>
                <?php if (!empty($search_term)) { ?>
                    <a href="?page_layout=quanlygatau" class="btn btn-reset">Đặt Lại</a>
                <?php } ?>
            </div>
        </form>
    </div>
    
    <!-- Form thêm ga tàu -->
    <div class="form-container">
        <h3>Thêm Ga Tàu Mới</h3>
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
                <select name="trang_thai" class="form-control" required>
                    <option value="HoatDong">Hoạt Động</option>
                    <option value="BaoTri">Bảo Trì</option>
                    <option value="Ngung">Ngừng</option>
                </select>
            </div>
            <button type="submit" name="them_ga" class="btn">Thêm Ga</button>
        </form>
    </div>

    <!-- Bảng danh sách ga tàu -->
    <div class="table-container">
        <h3><?php echo !empty($search_term) ? "Kết Quả Tìm Kiếm: \"$search_term\"" : "Danh Sách Ga Tàu"; ?></h3>
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
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['MaGa']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['TenGa']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['ViTriDiaLy']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['SoSanGa']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['TrangThai']) . "</td>";
                        echo "<td>
                            <a href='#' class='btn-edit' data-maga='{$row['MaGa']}' 
                               data-tenga='{$row['TenGa']}' data-vitri='{$row['ViTriDiaLy']}' 
                               data-sosanga='{$row['SoSanGa']}' data-trangthai='{$row['TrangThai']}'>Sửa</a>
                            <a href='?page_layout=quanlygatau&delete=" . $row['MaGa'] . "' class='btn-delete' onclick='return confirm(\"Bạn có chắc muốn xóa?\")'>Xóa</a>
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
                echo "<a href='?page_layout=quanlygatau&page=" . ($page - 1) . "&search_term=$search_term'>Trang trước</a>";
            }
            for ($i = 1; $i <= $total_pages; $i++) {
                echo "<a href='?page_layout=quanlygatau&page=$i&search_term=$search_term'" . ($i == $page ? " class='active'" : "") . ">$i</a>";
            }
            if ($page < $total_pages) {
                echo "<a href='?page_layout=quanlygatau&page=" . ($page + 1) . "&search_term=$search_term'>Trang sau</a>";
            }
            ?>
        </div>
    </div>
</div>

<!-- Modal chỉnh sửa -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <span class="close-modal">×</span>
        <h3>Chỉnh Sửa Ga Tàu</h3>
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
                <select name="trang_thai" id="modal-trang-thai" class="form-control" required>
                    <option value="HoatDong">Hoạt Động</option>
                    <option value="BaoTri">Bảo Trì</option>
                    <option value="Ngung">Ngừng</option>
                </select>
            </div>
            <button type="submit" name="sua_ga" class="btn">Cập Nhật</button>
            <button type="button" class="btn btn-cancel close-modal-btn">Hủy</button>
        </form>
    </div>
</div>

<style>
.container {
    padding: 20px;
    background: transparent;
    max-width: 1200px;
    margin: 0 auto;
}

h2 {
    font-size: 28px;
    font-weight: 700;
    color: #007bff;
    margin-bottom: 25px;
    text-transform: uppercase;
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
    color: #333;
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
    font-weight: 600;
    text-transform: uppercase;
    transition: all 0.3s ease;
    color: white;
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

.form-container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 30px;
}

.form-container h3 {
    font-size: 22px;
    color: #007bff;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-weight: 500;
    text-transform: uppercase;
}

.form-group input, .form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
    font-size: 16px;
}

.form-group input:focus, .form-group select:focus {
    border-color: #007bff;
    background: #fff;
    outline: none;
}

.form-container .btn {
    background: #28a745;
}

.form-container .btn:hover {
    background: #218838;
}

.table-container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}

.table-container h3 {
    font-size: 22px;
    color: #007bff;
    margin-bottom: 20px;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

th, td {
    padding: 15px;
    text-align: left;
}

th {
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
}

th:first-child { border-top-left-radius: 12px; }
th:last-child { border-top-right-radius: 12px; }

tbody tr:hover {
    background: #f8f9fa;
}

.btn-edit, .btn-delete {
    padding: 8px 15px;
    border-radius: 6px;
    text-decoration: none;
    color: white;
    font-size: 14px;
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
    background: #fff;
    margin: 80px auto;
    padding: 30px;
    width: 90%;
    max-width: 550px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.modal-content h3 {
    font-size: 24px;
    color: #007bff;
    margin-bottom: 25px;
}

.modal-content .btn {
    background: #28a745;
}

.modal-content .btn:hover {
    background: #218838;
}

.btn-cancel {
    background: #6c757d;
}

.btn-cancel:hover {
    background: #5a6268;
}

.pagination {
    margin-top: 25px;
    display: flex;
    gap: 10px;
    justify-content: center;
}

.pagination a {
    padding: 10px 20px;
    text-decoration: none;
    color: #007bff;
    border: 1px solid #ddd;
    border-radius: 8px;
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
    const modal = document.getElementById('editModal');
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
        });
    });

    document.querySelectorAll('.close-modal, .close-modal-btn').forEach(el => {
        el.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>