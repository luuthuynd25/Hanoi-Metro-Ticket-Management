<?php
require_once 'connect.php';

// Khởi tạo biến
$thong_tin_ve = [];
$thong_bao = '';
$loi = '';
$khach_hang_moi = false;
$ma_khach_hang = null;
$so_du_vi = 0;

// Lấy danh sách ga từ bảng `ga`
$ga_list = [];
$result = $connetor->query("SELECT MaGa, TenGa FROM ga WHERE TrangThai = 'HoatDong'");
while ($row = $result->fetch_assoc()) {
    $ga_list[$row['MaGa']] = $row['TenGa'];
}

// Giá vé cố định
$gia_ve_options = [
    'Luot' => 12000,
    'Ngay' => 24000,
    'Thang' => 200000
];

// Xử lý kiểm tra khách hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kiem_tra_khach_hang'])) {
    $sdt = trim($_POST['sdt']);
    if (!preg_match("/^[0-9]{10,11}$/", $sdt)) {
        $loi = "Số điện thoại không hợp lệ (10-11 số)!";
    } else {
        $stmt = $connetor->prepare("SELECT MaKhachHang, HoTen, Email FROM KhachHang WHERE SoDienThoai = ? LIMIT 1");
        $stmt->bind_param("s", $sdt);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $kh_info = $result->fetch_assoc();
            $ma_khach_hang = $kh_info['MaKhachHang'];
            $ho_ten = $kh_info['HoTen'];
            $email = $kh_info['Email'];
            $stmt = $connetor->prepare("SELECT SoDu FROM ViTienKhachHang WHERE MaKhachHang = ?");
            $stmt->bind_param("s", $ma_khach_hang);
            $stmt->execute();
            $so_du_vi = $stmt->get_result()->fetch_assoc()['SoDu'];
        } else {
            $khach_hang_moi = true;
        }
        $stmt->close();
    }
}

// Xử lý thêm khách hàng mới
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['them_khach_hang'])) {
    $ho_ten = trim($_POST['ho_ten']);
    $sdt = trim($_POST['sdt']);
    $email = trim($_POST['email'] ?? '');
    if (empty($ho_ten) || empty($sdt)) {
        $loi = "Vui lòng nhập đầy đủ họ tên và số điện thoại!";
    } elseif (!preg_match("/^[0-9]{10,11}$/", $sdt)) {
        $loi = "Số điện thoại không hợp lệ (10-11 số)!";
    } else {
        $connetor->begin_transaction();
        try {
            $ma_khach_hang = 'KH' . time() . rand(100, 999);
            $stmt = $connetor->prepare("INSERT INTO KhachHang (MaKhachHang, HoTen, SoDienThoai, Email) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $ma_khach_hang, $ho_ten, $sdt, $email);
            $stmt->execute();

            $stmt = $connetor->prepare("INSERT INTO ViTienKhachHang (MaKhachHang, SoDu) VALUES (?, 0)");
            $stmt->bind_param("s", $ma_khach_hang);
            $stmt->execute();

            $connetor->commit();
            $thong_bao = "Thêm khách hàng thành công! Vui lòng nhập thông tin vé.";
            $khach_hang_moi = false;
            $so_du_vi = 0;
        } catch (Exception $e) {
            $connetor->rollback();
            $loi = "Lỗi khi thêm khách hàng: " . $e->getMessage();
        }
    }
}

// Xử lý bán vé
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment'])) {
    $ga_di = $_POST['ga_di'];
    $ga_den = $_POST['ga_den'];
    $so_luong = (int)$_POST['so_luong'];
    $loai_ve = $_POST['loai_ve'];
    $phuong_thuc = $_POST['phuong_thuc'];
    $sdt = trim($_POST['sdt']);

    $stmt = $connetor->prepare("SELECT MaKhachHang, HoTen FROM KhachHang WHERE SoDienThoai = ? LIMIT 1");
    $stmt->bind_param("s", $sdt);
    $stmt->execute();
    $kh_info = $stmt->get_result()->fetch_assoc();
    $ma_khach_hang = $kh_info['MaKhachHang'];
    $ho_ten = $kh_info['HoTen'];

    if ($ga_di == $ga_den) {
        $loi = "Ga đi và ga đến không được trùng nhau!";
    } elseif (!isset($gia_ve_options[$loai_ve])) {
        $loi = "Loại vé không hợp lệ!";
    } elseif (!array_key_exists($ga_di, $ga_list) || !array_key_exists($ga_den, $ga_list)) {
        $loi = "Ga không hợp lệ!";
    } else {
        $connetor->begin_transaction();
        try {
            $gia_ve = $gia_ve_options[$loai_ve];
            $tong_tien = $gia_ve * $so_luong;

            if ($phuong_thuc == 'ViDienTu') {
                $stmt = $connetor->prepare("SELECT SoDu FROM ViTienKhachHang WHERE MaKhachHang = ?");
                $stmt->bind_param("s", $ma_khach_hang);
                $stmt->execute();
                $so_du = $stmt->get_result()->fetch_assoc()['SoDu'];
                if ($so_du < $tong_tien) {
                    throw new Exception("Số dư ví không đủ! Số dư hiện tại: " . number_format($so_du, 0, ',', '.') . " VND");
                }
                $stmt = $connetor->prepare("UPDATE ViTienKhachHang SET SoDu = SoDu - ? WHERE MaKhachHang = ?");
                $stmt->bind_param("ds", $tong_tien, $ma_khach_hang);
                $stmt->execute();
            }

            $ngay_mua = date('Y-m-d H:i:s');
            for ($i = 0; $i < $so_luong; $i++) {
                $ma_ve = 'VE' . time() . rand(100, 999) . $i;
                $stmt = $connetor->prepare("INSERT INTO Ve (MaVe, MaKhachHang, NgayMua, LoaiVe, PhuongThucThanhToan, TrangThai) 
                                            VALUES (?, ?, ?, ?, ?, 'DaThanhToan')");
                $stmt->bind_param("sssss", $ma_ve, $ma_khach_hang, $ngay_mua, $loai_ve, $phuong_thuc);
                $stmt->execute();

                if ($phuong_thuc == 'ViDienTu') {
                    $ma_gd = 'GD' . time() . rand(100, 999) . $i;
                    $stmt = $connetor->prepare("INSERT INTO GiaoDichVi (MaGiaoDich, MaKhachHang, SoTien, LoaiGiaoDich, NgayGiaoDich, NoiDung) 
                                                VALUES (?, ?, ?, 'ThanhToanVe', ?, 'Thanh toán vé $ma_ve')");
                    $stmt->bind_param("ssds", $ma_gd, $ma_khach_hang, $gia_ve, $ngay_mua);
                    $stmt->execute();
                }

                $thong_tin_ve[] = [
                    'ma_ve' => $ma_ve,
                    'gia_ve' => $gia_ve,
                    'ga_di' => $ga_di,
                    'ga_den' => $ga_den,
                    'loai_ve' => $loai_ve,
                    'phuong_thuc' => $phuong_thuc
                ];
            }

            $connetor->commit();
            $thong_bao = "Mua vé thành công! Tổng tiền: " . number_format($tong_tien, 0, ',', '.') . " VND";
        } catch (Exception $e) {
            $connetor->rollback();
            $loi = "Lỗi: " . $e->getMessage();
        }
    }
}
?>

<div class="ban-ve-container">
    <h2><i class="fas fa-ticket-alt"></i> Bán Vé Tàu</h2>

    <!-- Thông báo -->
    <?php if ($thong_bao): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($thong_bao) ?></div>
    <?php endif; ?>
    <?php if ($loi): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($loi) ?></div>
    <?php endif; ?>

    <!-- Form kiểm tra khách hàng -->
    <?php if (empty($thong_tin_ve) && !$khach_hang_moi): ?>
        <div class="form-section">
            <h3>Kiểm Tra Khách Hàng</h3>
            <form method="POST" class="ban-ve-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Số Điện Thoại</label>
                        <input type="tel" name="sdt" class="form-control" placeholder="Nhập số điện thoại" required pattern="[0-9]{10,11}">
                    </div>
                    <div class="form-group">
                        <label>Họ Tên (Tùy Chọn)</label>
                        <input type="text" name="ho_ten" class="form-control" placeholder="Nhập họ tên">
                    </div>
                </div>
                <button type="submit" name="kiem_tra_khach_hang" class="btn btn-primary"><i class="fas fa-search"></i> Kiểm Tra</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Form thêm khách hàng mới -->
    <?php if ($khach_hang_moi): ?>
        <div class="form-section">
            <h3>Thêm Khách Hàng Mới</h3>
            <form method="POST" class="ban-ve-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Họ Tên</label>
                        <input type="text" name="ho_ten" class="form-control" required placeholder="Nhập họ tên">
                    </div>
                    <div class="form-group">
                        <label>Số Điện Thoại</label>
                        <input type="tel" name="sdt" class="form-control" value="<?= htmlspecialchars($_POST['sdt']) ?>" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email (Tùy Chọn)</label>
                    <input type="email" name="email" class="form-control" placeholder="Nhập email">
                </div>
                <div class="form-actions">
                    <button type="submit" name="them_khach_hang" class="btn btn-primary"><i class="fas fa-save"></i> Thêm</button>
                    <a href="?page_layout=banve" class="btn btn-cancel"><i class="fas fa-times"></i> Hủy</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Form bán vé -->
    <?php if (empty($thong_tin_ve) && !$khach_hang_moi && $ma_khach_hang): ?>
        <div class="form-section">
            <h3>Thông Tin Mua Vé</h3>
            <form method="POST" class="ban-ve-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Họ Tên</label>
                        <input type="text" name="ho_ten" class="form-control" value="<?= htmlspecialchars($ho_ten) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Số Điện Thoại</label>
                        <input type="tel" name="sdt" class="form-control" value="<?= htmlspecialchars($_POST['sdt']) ?>" readonly>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Ga Đi</label>
                        <select name="ga_di" class="form-control" required>
                            <option value="">Chọn ga đi</option>
                            <?php foreach ($ga_list as $ma_ga => $ten_ga): ?>
                                <option value="<?= $ma_ga ?>"><?= $ten_ga ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ga Đến</label>
                        <select name="ga_den" class="form-control" required>
                            <option value="">Chọn ga đến</option>
                            <?php foreach ($ga_list as $ma_ga => $ten_ga): ?>
                                <option value="<?= $ma_ga ?>"><?= $ten_ga ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Số Lượng Vé</label>
                        <select name="so_luong" class="form-control" required>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> vé</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Loại Vé</label>
                        <select name="loai_ve" class="form-control" required onchange="updateTotal()">
                            <?php foreach ($gia_ve_options as $loai => $gia): ?>
                                <option value="<?= $loai ?>" data-price="<?= $gia ?>"><?= $loai ?> (<?= number_format($gia, 0, ',', '.') ?> VND)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Phương Thức Thanh Toán</label>
                        <select name="phuong_thuc" class="form-control" required onchange="updateTotal()">
                            <option value="TienMat">Tiền Mặt</option>
                            <option value="The">Thẻ Ngân Hàng</option>
                            <option value="ViDienTu">Ví Điện Tử (Số dư: <?= number_format($so_du_vi, 0, ',', '.') ?> VND)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tổng Tiền</label>
                        <input type="text" id="total_price" class="form-control" readonly value="0 VND">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="process_payment" class="btn btn-primary"><i class="fas fa-check"></i> Thanh Toán</button>
                    <button type="reset" class="btn btn-cancel" onclick="resetForm()"><i class="fas fa-undo"></i> Đặt Lại</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Hiển thị vé đã mua -->
    <?php if (!empty($thong_tin_ve)): ?>
        <div class="ticket-section">
            <h3>Vé Đã Mua</h3>
            <div class="ticket-list">
                <?php foreach ($thong_tin_ve as $ve): ?>
                    <div class="ticket-item">
                        <div class="ticket-header">Mã Vé: <?= htmlspecialchars($ve['ma_ve']) ?></div>
                        <div class="ticket-details">
                            <p><strong>Họ Tên:</strong> <?= htmlspecialchars($ho_ten) ?></p>
                            <p><strong>SĐT:</strong> <?= htmlspecialchars($_POST['sdt']) ?></p>
                            <p><strong>Tuyến:</strong> <?= $ga_list[$ve['ga_di']] ?> → <?= $ga_list[$ve['ga_den']] ?></p>
                            <p><strong>Loại Vé:</strong> <?= $ve['loai_ve'] ?></p>
                            <p><strong>Giá:</strong> <?= number_format($ve['gia_ve'], 0, ',', '.') ?> VND</p>
                            <p><strong>Phương Thức:</strong> <?= $ve['phuong_thuc'] ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="ticket-actions">
                <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> In Vé</button>
                <a href="?page_layout=banve" class="btn btn-primary"><i class="fas fa-plus"></i> Mua Vé Mới</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.ban-ve-container {
    padding: 30px;
    max-width: 1000px;
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

h3 {
    font-size: 22px;
    color: #333;
    margin-bottom: 20px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
}

.form-section, .ticket-section {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
}

.ban-ve-form .form-row {
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

.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: #fff;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.2);
    outline: none;
}

.form-control[readonly] {
    background: #f0f0f0;
    color: #666;
}

.btn {
    padding: 12px 25px;
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

.btn-primary {
    background: #007bff;
}

.btn-primary:hover {
    background: #0056b3;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.btn-cancel {
    background: #6c757d;
}

.btn-cancel:hover {
    background: #5a6268;
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 20px;
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

.ticket-list {
    display: grid;
    gap: 20px;
}

.ticket-item {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    border-left: 5px solid #007bff;
    transition: transform 0.3s ease;
}

.ticket-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.ticket-header {
    font-size: 18px;
    font-weight: bold;
    color: #007bff;
    margin-bottom: 15px;
}

.ticket-details p {
    margin: 8px 0;
    font-size: 16px;
    color: #333;
}

.ticket-details strong {
    color: #555;
}

.ticket-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 30px;
}

@media (max-width: 768px) {
    .ban-ve-form .form-row {
        flex-direction: column;
        gap: 15px;
    }
    .ticket-list {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function updateTotal() {
    const soLuong = parseInt(document.querySelector('select[name="so_luong"]').value);
    const loaiVe = document.querySelector('select[name="loai_ve"]');
    const giaVe = parseInt(loaiVe.options[loaiVe.selectedIndex].getAttribute('data-price'));
    const total = soLuong * giaVe;
    document.getElementById('total_price').value = total.toLocaleString('vi-VN') + ' VND';
}

function resetForm() {
    document.querySelector('.ban-ve-form').reset();
    document.getElementById('total_price').value = '0 VND';
}

document.addEventListener('DOMContentLoaded', function() {
    updateTotal();
});
</script>