<?php
require_once 'connect.php';

header('Content-Type: application/json');

$data = [];

try {
    // Gộp các truy vấn doanh thu và số vé để tối ưu
    $stmt = $connetor->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN DATE(NgayMua) = CURDATE() THEN GiaVe ELSE 0 END), 0) AS doanh_thu_ngay,
            COALESCE(SUM(CASE WHEN MONTH(NgayMua) = MONTH(CURDATE()) AND YEAR(NgayMua) = YEAR(CURDATE()) THEN GiaVe ELSE 0 END), 0) AS doanh_thu_thang,
            COALESCE(COUNT(CASE WHEN DATE(NgayMua) = CURDATE() THEN 1 END), 0) AS ve_ban_ngay
        FROM ve 
        WHERE TrangThai = ?
    ");
    $status_ve = 'hoatdong';
    $stmt->bind_param("s", $status_ve);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['doanh_thu_ngay'] = $row['doanh_thu_ngay'];
    $data['doanh_thu_thang'] = $row['doanh_thu_thang'];
    $data['ve_ban_ngay'] = $row['ve_ban_ngay'];
    $stmt->close();

    // Giao dịch gần đây
    $stmt = $connetor->prepare("
        SELECT v.MaVe, v.NgayMua, v.TrangThai, v.GiaVe, 
               kh.HoTen, kh.Email, qdt.gio_xuat_phat AS ThoiGianDi,
               g1.TenGa AS GaDi, g2.TenGa AS GaDen
        FROM ve v 
        JOIN khachhang kh ON v.MaKhachHang = kh.MaKhachHang 
        JOIN hanhtrinh ht ON v.MaHanhTrinh = ht.MaHanhTrinh
        JOIN ga g1 ON v.GaDi = g1.MaGa
        JOIN ga g2 ON v.GaDen = g2.MaGa
        LEFT JOIN quanlydoantau qdt ON qdt.gio_xuat_phat >= NOW()
        WHERE v.TrangThai = ?
        ORDER BY v.NgayMua DESC 
        LIMIT 5
    ");
    $stmt->bind_param("s", $status_ve);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_transactions = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recent_transactions[] = $row;
        }
    }
    $data['recent_transactions'] = $recent_transactions;
    $stmt->close();

    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>