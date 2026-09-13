<?php
require_once 'connect.php';

// Khởi tạo biến
$thong_tin_ve = [];
$thong_bao = '';
$loi = '';
$khach_hang_moi = false;
$ma_khach_hang = null;
$ho_ten = '';
$sdt = '';
$so_du = 0;

// Lấy danh sách ga từ bảng `ga`
$ga_list = [];
$sql_ga = "SELECT MaGa, TenGa FROM ga WHERE TrangThai = 'hoatdong' ORDER BY TenGa";
$result_ga = $connetor->query($sql_ga);
if ($result_ga === false) {
    $loi = "Lỗi truy vấn danh sách ga: " . $connetor->error;
} else {
    while ($row = $result_ga->fetch_assoc()) {
        $ga_list[$row['MaGa']] = $row['TenGa'];
    }
}

// Giá vé cố định (hardcode vì bảng loaive không tồn tại)
$gia_ve_options = [
    'Luot' => [
        'ten' => 'Vé lượt',
        'gia' => 12000
    ],
    'Ngay' => [
        'ten' => 'Vé ngày',
        'gia' => 24000
    ],
    'Thang' => [
        'ten' => 'Vé tháng',
        'gia' => 200000
    ]
];

// Xử lý kiểm tra khách hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_customer'])) {
    $sdt = trim($_POST['sdt']);
    if (empty($sdt)) {
        $loi = "Vui lòng nhập số điện thoại!";
    } elseif (!preg_match("/^[0-9]{10,11}$/", $sdt)) {
        $loi = "Số điện thoại không hợp lệ (10-11 số)!";
    } else {
        $stmt = $connetor->prepare("
            SELECT k.MaKhachHang, k.HoTen, k.SoDienThoai, v.SoDu 
            FROM khachhang k 
            LEFT JOIN vitienkhachhang v ON k.MaKhachHang = v.MaKhachHang 
            WHERE k.SoDienThoai = ?
        ");
        
        if (!$stmt) {
            $loi = "Lỗi truy vấn: " . $connetor->error;
        } else {
            $stmt->bind_param("s", $sdt);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $khach_hang_moi = true;
            } else {
                $kh_info = $result->fetch_assoc();
                $ma_khach_hang = $kh_info['MaKhachHang'];
                $ho_ten = $kh_info['HoTen'];
                $sdt = $kh_info['SoDienThoai'];
                $so_du = $kh_info['SoDu'] ?? 0;
                $khach_hang_moi = false;
            }
            $stmt->close();
        }
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
            $stmt = $connetor->prepare("INSERT INTO khachhang (MaKhachHang, HoTen, SoDienThoai, Email) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $ma_khach_hang, $ho_ten, $sdt, $email);
            $stmt->execute();
            
            // Tạo ví điện tử cho khách hàng mới
            $stmt = $connetor->prepare("INSERT INTO vitienkhachhang (MaKhachHang, SoDu, NgayCapNhat) VALUES (?, 0, NOW())");
            $stmt->bind_param("s", $ma_khach_hang);
            $stmt->execute();
            
            $connetor->commit();
            $thong_bao = "Thêm khách hàng thành công! Vui lòng nhập thông tin vé.";
            $khach_hang_moi = false;
        } catch (Exception $e) {
            $connetor->rollback();
            $loi = "Lỗi khi thêm khách hàng: " . $e->getMessage();
        }
    }
}

// Xử lý bán vé
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment'])) {
    $ga_di = isset($_POST['ga_di']) && !empty($_POST['ga_di']) ? $_POST['ga_di'] : null;
    $ga_den = isset($_POST['ga_den']) && !empty($_POST['ga_den']) ? $_POST['ga_den'] : null;
    $so_luong = (int)$_POST['so_luong'];
    $loai_ve = $_POST['loai_ve'];
    $phuong_thuc = $_POST['phuong_thuc'];
    $sdt = trim($_POST['sdt']);

    // Kiểm tra khách hàng và số dư ví
    $stmt = $connetor->prepare("
        SELECT k.MaKhachHang, k.HoTen, v.SoDu 
        FROM khachhang k 
        LEFT JOIN vitienkhachhang v ON k.MaKhachHang = v.MaKhachHang 
        WHERE k.SoDienThoai = ?
    ");
    
    if (!$stmt) {
        $loi = "Lỗi truy vấn: " . $connetor->error;
    } else {
        $stmt->bind_param("s", $sdt);
        if (!$stmt->execute()) {
            $loi = "Lỗi thực thi truy vấn: " . $stmt->error;
        } else {
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $loi = "Không tìm thấy thông tin khách hàng!";
            } else {
                $kh_info = $result->fetch_assoc();
                $ma_khach_hang = $kh_info['MaKhachHang'];
                $ho_ten = $kh_info['HoTen'];
                $so_du = $kh_info['SoDu'] ?? 0;
                $stmt->close();

                // Kiểm tra loại vé và số lượng
                if (!isset($gia_ve_options[$loai_ve])) {
                    $loi = "Loại vé không hợp lệ!";
                } elseif ($so_luong < 1 || $so_luong > 10) {
                    $loi = "Số lượng vé phải từ 1 đến 10!";
                } elseif ($loai_ve === 'Luot' && (empty($ga_di) || empty($ga_den))) {
                    $loi = "Vui lòng chọn ga đi và ga đến cho vé lượt!";
                } elseif ($loai_ve === 'Luot' && $ga_di === $ga_den) {
                    $loi = "Ga đi và ga đến không được trùng nhau!";
                } else {
                    // Kiểm tra GaDi và GaDen có tồn tại trong bảng `ga` không
                    if ($loai_ve === 'Luot') {
                        $stmt = $connetor->prepare("SELECT MaGa FROM ga WHERE MaGa = ?");
                        $stmt->bind_param("s", $ga_di);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->num_rows === 0) {
                            $loi = "Ga đi không tồn tại!";
                        }
                        $stmt->close();

                        if (empty($loi)) {
                            $stmt = $connetor->prepare("SELECT MaGa FROM ga WHERE MaGa = ?");
                            $stmt->bind_param("s", $ga_den);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows === 0) {
                                $loi = "Ga đến không tồn tại!";
                            }
                            $stmt->close();
                        }
                    }

                    if (empty($loi)) {
                        $gia_ve = $gia_ve_options[$loai_ve]['gia'];
                        $tong_tien = $gia_ve * $so_luong;

                        // Kiểm tra phương thức thanh toán và số dư
                        if ($phuong_thuc === 'ViDienTu') {
                            if ($so_du < $tong_tien) {
                                $loi = "Số dư không đủ để thanh toán! Cần: " . number_format($tong_tien, 0, ',', '.') . " VND. Số dư hiện tại: " . number_format($so_du, 0, ',', '.') . " VND";
                            }
                        }

                        if (empty($loi)) {
                            $connetor->begin_transaction();
                            try {
                                // Xử lý hành trình (MaHanhTrinh)
                                $ma_hanh_trinh = null;
                                if ($loai_ve === 'Luot') {
                                    // Tạo mã hành trình
                                    $ma_hanh_trinh = 'HT' . substr(md5($ga_di . $ga_den . time()), 0, 8);
                                    $mo_ta = $ga_list[$ga_di] . " → " . $ga_list[$ga_den];
                                    
                                    // Kiểm tra xem hành trình đã tồn tại chưa
                                    $stmt = $connetor->prepare("SELECT MaHanhTrinh FROM hanhtrinh WHERE MaHanhTrinh = ?");
                                    $stmt->bind_param("s", $ma_hanh_trinh);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows === 0) {
                                        // Thêm hành trình mới nếu chưa tồn tại
                                        $stmt = $connetor->prepare("INSERT INTO hanhtrinh (MaHanhTrinh, MoTa) VALUES (?, ?)");
                                        $stmt->bind_param("ss", $ma_hanh_trinh, $mo_ta);
                                        $stmt->execute();
                                    }
                                    $stmt->close();
                                }

                                // Nếu thanh toán bằng ví điện tử, cập nhật số dư
                                if ($phuong_thuc === 'ViDienTu') {
                                    $stmt = $connetor->prepare("
                                        UPDATE vitienkhachhang 
                                        SET SoDu = SoDu - ?, NgayCapNhat = NOW()
                                        WHERE MaKhachHang = ? 
                                        AND SoDu >= ?
                                    ");
                                    
                                    if (!$stmt) {
                                        throw new Exception("Lỗi cập nhật ví: " . $connetor->error);
                                    }
                                    
                                    $stmt->bind_param("dsd", $tong_tien, $ma_khach_hang, $tong_tien);
                                    if (!$stmt->execute() || $stmt->affected_rows === 0) {
                                        throw new Exception("Không thể cập nhật số dư ví. Vui lòng thử lại!");
                                    }
                                    $stmt->close();
                                }

                                // Tạo vé và lưu vào bảng `ve`
                                for ($i = 0; $i < $so_luong; $i++) {
                                    $ma_ve = 'VE' . time() . rand(100, 999);
                                    
                                    // Chuyển đổi phương thức thanh toán sang giá trị enum
                                    $phuong_thuc_db = match ($phuong_thuc) {
                                        'TienMat' => 'tienmat',
                                        'The' => 'the',
                                        'ViDienTu' => 'tructuyen',
                                        default => 'tienmat'
                                    };

                                    // Insert vào bảng `ve`
                                    $stmt = $connetor->prepare("
                                        INSERT INTO ve (MaVe, MaKhachHang, MaHanhTrinh, NgayMua, LoaiVe, GiaVe, PhuongThucThanhToan, TrangThai, GaDi, GaDen)
                                        VALUES (?, ?, ?, NOW(), ?, ?, ?, 'hoatdong', ?, ?)
                                    ");
                                    
                                    if (!$stmt) {
                                        throw new Exception("Lỗi tạo vé: " . $connetor->error);
                                    }
                                    
                                    // Với vé không phải "Vé lượt", GaDi và GaDen nên là null
                                    $ga_di_param = $loai_ve === 'Luot' ? $ga_di : null;
                                    $ga_den_param = $loai_ve === 'Luot' ? $ga_den : null;

                                    $stmt->bind_param("ssssdsss", 
                                        $ma_ve, 
                                        $ma_khach_hang, 
                                        $ma_hanh_trinh, 
                                        $loai_ve,
                                        $gia_ve, 
                                        $phuong_thuc_db,
                                        $ga_di_param,
                                        $ga_den_param
                                    );
                                    if (!$stmt->execute()) {
                                        throw new Exception("Không thể tạo vé: " . $stmt->error);
                                    }
                                    $stmt->close();

                                    // Insert vào bảng `lichsumuave`
                                    $ma_lich_su = 'LS' . time() . rand(100, 999);
                                    $stmt = $connetor->prepare("
                                        INSERT INTO lichsumuave (MaLichSu, MaKhachHang, MaVe, NgayMua)
                                        VALUES (?, ?, ?, NOW())
                                    ");
                                    
                                    if (!$stmt) {
                                        throw new Exception("Lỗi tạo lịch sử: " . $connetor->error);
                                    }
                                    
                                    $stmt->bind_param("sss", $ma_lich_su, $ma_khach_hang, $ma_ve);
                                    if (!$stmt->execute()) {
                                        throw new Exception("Không thể tạo lịch sử: " . $stmt->error);
                                    }
                                    $stmt->close();

                                    // Lưu thông tin vé để hiển thị
                                    $thong_tin_ve[] = [
                                        'ma_ve' => $ma_ve,
                                        'ho_ten' => $ho_ten,
                                        'sdt' => $sdt,
                                        'ga_di' => $loai_ve === 'Luot' ? $ga_list[$ga_di] : null,
                                        'ga_den' => $loai_ve === 'Luot' ? $ga_list[$ga_den] : null,
                                        'loai_ve' => $gia_ve_options[$loai_ve]['ten'],
                                        'gia_ve' => $gia_ve,
                                        'ngay_mua' => date('Y-m-d H:i:s'),
                                        'phuong_thuc' => $phuong_thuc
                                    ];
                                }

                                $connetor->commit();
                                $thong_bao = "Mua vé thành công! Tổng tiền: " . number_format($tong_tien, 0, ',', '.') . " VND";
                                
                                if ($phuong_thuc === 'ViDienTu') {
                                    $so_du -= $tong_tien;
                                    $thong_bao .= ". Số dư ví còn lại: " . number_format($so_du, 0, ',', '.') . " VND";
                                }
                            } catch (Exception $e) {
                                $connetor->rollback();
                                $loi = "Lỗi: " . $e->getMessage();
                            }
                        }
                    }
                }
            }
        }
    }
}
?>

<!-- Nội dung chính, tương thích với admin.php -->
<style>
    .card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .card h3 {
        margin-bottom: 15px;
        color: #2c3e50;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }
    .form-group input, .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-sizing: border-box;
    }
    .form-actions {
        display: flex;
        gap: 10px;
    }
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        color: white;
        text-decoration: none;
        display: inline-block;
    }
    .btn-primary {
        background-color: #3498db;
    }
    .btn-cancel {
        background-color: #ecf0f1;
        color: #2c3e50;
    }
    .alert {
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
    }
    .alert-success {
        background-color: #d4edda;
        color: #155724;
    }
    .alert-info {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
    }
    .ticket-details {
        margin-top: 20px;
    }
    .ticket-item {
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-bottom: 10px;
    }
    .ticket-item p {
        margin: 5px 0;
    }
    .total-price {
        font-weight: bold;
        margin: 10px 0;
    }
</style>

<div class="card">
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

    <?php if (!$ma_khach_hang && !$khach_hang_moi): ?>
        <div class="form-section">
            <h3>Kiểm Tra Khách Hàng</h3>
            <form method="POST" class="ban-ve-form">
                <div class="form-group">
                    <label>Số Điện Thoại <span class="required">*</span></label>
                    <input type="tel" name="sdt" class="form-control" required placeholder="Nhập số điện thoại">
                </div>
                <div class="form-actions">
                    <button type="submit" name="check_customer" class="btn btn-primary">
                        <i class="fas fa-search"></i> Kiểm Tra
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($khach_hang_moi): ?>
        <div class="form-section">
            <h3>Thêm Khách Hàng Mới</h3>
            <form method="POST" class="ban-ve-form">
                <div class="form-group">
                    <label>Họ Tên <span class="required">*</span></label>
                    <input type="text" name="ho_ten" class="form-control" required placeholder="Nhập họ tên" value="<?= htmlspecialchars($ho_ten) ?>">
                </div>
                <div class="form-group">
                    <label>Số Điện Thoại</label>
                    <input type="tel" name="sdt" class="form-control" value="<?= htmlspecialchars($sdt) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Nhập email">
                </div>
                <div class="form-actions">
                    <button type="submit" name="them_khach_hang" class="btn btn-primary">
                        <i class="fas fa-save"></i> Thêm
                    </button>
                    <a href="?page_layout=banve" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Hủy
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if (empty($thong_tin_ve) && !$khach_hang_moi && $ma_khach_hang): ?>
        <div class="form-section">
            <h3>Thông Tin Mua Vé</h3>
            <div class="customer-info">
                <div class="alert alert-info">
                    <i class="fas fa-wallet"></i>
                    Số dư ví: <strong><?= number_format($so_du, 0, ',', '.') ?> VND</strong>
                </div>
            </div>
            <form method="POST" class="ban-ve-form" id="payment-form">
                <div class="form-group">
                    <label>Họ Tên</label>
                    <input type="text" name="ho_ten" class="form-control" value="<?= htmlspecialchars($ho_ten) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Số Điện Thoại</label>
                    <input type="tel" name="sdt" class="form-control" value="<?= htmlspecialchars($sdt) ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="loai_ve">Loại vé <span class="required">*</span></label>
                    <select name="loai_ve" id="loai_ve" class="form-control" required>
                        <option value="">Chọn loại vé</option>
                        <?php foreach ($gia_ve_options as $ma => $info): ?>
                            <option value="<?php echo htmlspecialchars($ma); ?>">
                                <?php echo htmlspecialchars($info['ten']); ?> 
                                (<?php echo number_format($info['gia'], 0, ',', '.'); ?> VND)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="hanhtrinh_section" style="display: none;">
                    <div class="form-group">
                        <label for="ga_di">Ga đi <span class="required">*</span></label>
                        <select name="ga_di" id="ga_di" class="form-control">
                            <option value="">Chọn ga đi</option>
                            <?php foreach ($ga_list as $ma => $ten): ?>
                                <option value="<?php echo htmlspecialchars($ma); ?>"><?php echo htmlspecialchars($ten); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ga_den">Ga đến <span class="required">*</span></label>
                        <select name="ga_den" id="ga_den" class="form-control">
                            <option value="">Chọn ga đến</option>
                            <?php foreach ($ga_list as $ma => $ten): ?>
                                <option value="<?php echo htmlspecialchars($ma); ?>"><?php echo htmlspecialchars($ten); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="so_luong">Số lượng vé <span class="required">*</span></label>
                    <input type="number" name="so_luong" id="so_luong" class="form-control" min="1" max="10" value="1" required>
                </div>
                <div class="form-group">
                    <label for="phuong_thuc">Phương thức thanh toán <span class="required">*</span></label>
                    <select name="phuong_thuc" id="phuong_thuc" class="form-control" required>
                        <option value="">Chọn phương thức thanh toán</option>
                        <option value="TienMat">Tiền mặt</option>
                        <option value="The">Thẻ</option>
                        <option value="ViDienTu">Ví điện tử</option>
                    </select>
                </div>
                <div class="total-price" id="total-price">
                    Tổng tiền: 0 VND
                </div>
                <div class="form-actions">
                    <button type="submit" name="process_payment" class="btn btn-primary">
                        <i class="fas fa-check"></i> Thanh Toán
                    </button>
                    <a href="?page_layout=banve" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Hủy
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if (!empty($thong_tin_ve)): ?>
        <div class="form-section">
            <h3>Chi Tiết Vé</h3>
            <div class="ticket-details">
                <?php foreach ($thong_tin_ve as $index => $ve): ?>
                    <div class="ticket-item">
                        <p><strong>Mã Vé:</strong> <?= htmlspecialchars($ve['ma_ve']) ?></p>
                        <p><strong>Khách hàng:</strong> <?= htmlspecialchars($ve['ho_ten']) ?></p>
                        <p><strong>Số điện thoại:</strong> <?= htmlspecialchars($ve['sdt']) ?></p>
                        <p><strong>Loại Vé:</strong> <?= htmlspecialchars($ve['loai_ve']) ?></p>
                        <?php if ($ve['loai_ve'] === 'Vé lượt'): ?>
                            <p><strong>Ga Đi:</strong> <?= htmlspecialchars($ve['ga_di']) ?></p>
                            <p><strong>Ga Đến:</strong> <?= htmlspecialchars($ve['ga_den']) ?></p>
                        <?php endif; ?>
                        <p><strong>Giá Vé:</strong> <?= number_format($ve['gia_ve'], 0, ',', '.') ?> VND</p>
                        <p><strong>Ngày mua:</strong> <?= date('d/m/Y H:i:s', strtotime($ve['ngay_mua'])) ?></p>
                        <p><strong>Phương Thức:</strong>
                            <?php
                            $phuong_thuc_text = [
                                'TienMat' => 'Tiền Mặt',
                                'The' => 'Thẻ',
                                'ViDienTu' => 'Ví Điện Tử'
                            ];
                            echo htmlspecialchars($phuong_thuc_text[$ve['phuong_thuc']] ?? $ve['phuong_thuc']);
                            ?>
                        </p>
                        <div class="ticket-actions">
                            <button onclick="inVe(<?php echo htmlspecialchars(json_encode($ve)); ?>)" class="btn btn-primary">
                                <i class="fas fa-print"></i> In Vé
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-actions">
                <a href="?page_layout=banve" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Mua Vé Mới
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loaiVeSelect = document.getElementById('loai_ve');
    const hanhTrinhSection = document.getElementById('hanhtrinh_section');
    const gaDiSelect = document.getElementById('ga_di');
    const gaDenSelect = document.getElementById('ga_den');
    const soLuongInput = document.getElementById('so_luong');
    const totalPriceDiv = document.getElementById('total-price');
    const giaVeOptions = <?php echo json_encode($gia_ve_options); ?>;

    // Hàm cập nhật tổng tiền
    function updateTotalPrice() {
        const loaiVe = loaiVeSelect.value;
        const soLuong = parseInt(soLuongInput.value) || 0;
        const giaVe = giaVeOptions[loaiVe]?.gia || 0;
        const tongTien = giaVe * soLuong;
        totalPriceDiv.textContent = `Tổng tiền: ${tongTien.toLocaleString('vi-VN')} VND`;
    }

    // Xử lý hiển thị/ẩn section hành trình
    loaiVeSelect.addEventListener('change', function() {
        if (this.value === 'Luot') {
            hanhTrinhSection.style.display = 'block';
            gaDiSelect.required = true;
            gaDenSelect.required = true;
        } else {
            hanhTrinhSection.style.display = 'none';
            gaDiSelect.required = false;
            gaDenSelect.required = false;
            gaDiSelect.value = '';
            gaDenSelect.value = '';
        }
        updateTotalPrice();
    });

    // Cập nhật danh sách ga đến khi chọn ga đi
    gaDiSelect.addEventListener('change', function() {
        const selectedGaDi = this.value;
        const gaDenOptions = gaDenSelect.options;
        
        // Bật tất cả các option ga đến
        for (let i = 0; i < gaDenOptions.length; i++) {
            gaDenOptions[i].disabled = false;
        }
        
        // Disable ga đến trùng với ga đi
        if (selectedGaDi) {
            for (let i = 0; i < gaDenOptions.length; i++) {
                if (gaDenOptions[i].value === selectedGaDi) {
                    gaDenOptions[i].disabled = true;
                }
            }
        }
        
        // Reset ga đến nếu đang chọn trùng với ga đi
        if (gaDenSelect.value === selectedGaDi) {
            gaDenSelect.value = '';
        }
    });

    // Cập nhật tổng tiền khi thay đổi số lượng
    soLuongInput.addEventListener('input', updateTotalPrice);

    // Khởi tạo trạng thái ban đầu
    if (loaiVeSelect.value === 'Luot') {
        hanhTrinhSection.style.display = 'block';
        gaDiSelect.required = true;
        gaDenSelect.required = true;
    }
    updateTotalPrice();
});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function inVe(veInfo) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Thiết lập font chữ
    doc.setFont("helvetica");
    doc.setFontSize(16);
    
    // Tiêu đề
    doc.text("VÉ TÀU", 105, 20, { align: "center" });
    
    // Thông tin vé
    doc.setFontSize(12);
    let y = 40;
    const lineHeight = 10;
    
    doc.text(`Mã vé: ${veInfo.ma_ve}`, 20, y); y += lineHeight;
    doc.text(`Khách hàng: ${veInfo.ho_ten}`, 20, y); y += lineHeight;
    doc.text(`Số điện thoại: ${veInfo.sdt}`, 20, y); y += lineHeight;
    doc.text(`Loại vé: ${veInfo.loai_ve}`, 20, y); y += lineHeight;
    
    if (veInfo.loai_ve === 'Vé lượt') {
        doc.text(`Ga đi: ${veInfo.ga_di}`, 20, y); y += lineHeight;
        doc.text(`Ga đến: ${veInfo.ga_den}`, 20, y); y += lineHeight;
    }
    
    doc.text(`Giá vé: ${new Intl.NumberFormat('vi-VN').format(veInfo.gia_ve)} VND`, 20, y); y += lineHeight;
    doc.text(`Ngày mua: ${new Date(veInfo.ngay_mua).toLocaleString('vi-VN')}`, 20, y); y += lineHeight;
    doc.text(`Phương thức thanh toán: ${veInfo.phuong_thuc}`, 20, y); y += lineHeight;
    
    // Lưu file PDF
    doc.save(`Ve_${veInfo.ma_ve}.pdf`);
}
</script>