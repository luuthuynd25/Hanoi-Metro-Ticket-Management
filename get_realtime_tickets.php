<?php
require_once 'connect.php';
header('Content-Type: application/json');

$limit = 10;
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$start = ($page - 1) * $limit;
$search_term = isset($_POST['search_term']) ? $connetor->real_escape_string($_POST['search_term']) : '';

// Thống kê
$sql_stats = "SELECT 
                COUNT(CASE WHEN v.TrangThai = 'hoatdong' THEN 1 END) AS da_ban,
                COUNT(CASE WHEN v.TrangThai = 'dasudung' THEN 1 END) AS da_su_dung,
                COUNT(vdh.MaHuy) AS da_huy
              FROM ve v
              LEFT JOIN vedahuy vdh ON v.MaVe = vdh.MaVe";
$result_stats = $connetor->query($sql_stats);
$stats = $result_stats ? $result_stats->fetch_assoc() : ['da_ban' => 0, 'da_su_dung' => 0, 'da_huy' => 0];

// Đếm tổng số vé
$sql_count = "SELECT COUNT(*) as total FROM ve v 
              LEFT JOIN khachhang k ON v.MaKhachHang = k.MaKhachHang";
$count_params = [];
if ($search_term) {
    $sql_count .= " WHERE v.MaVe LIKE ? OR k.HoTen LIKE ? OR k.SoDienThoai LIKE ?";
    $search_param = "%$search_term%";
    $count_params = [$search_param, $search_param, $search_param];
}
$stmt_count = $connetor->prepare($sql_count);
if (!empty($count_params)) {
    $stmt_count->bind_param("sss", ...$count_params);
}
$stmt_count->execute();
$total = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();
$total_pages = ceil($total / $limit);

// Danh sách vé
$base_query = "SELECT v.MaVe, v.MaKhachHang, v.NgayMua, v.LoaiVe, v.GiaVe, v.PhuongThucThanhToan, v.TrangThai,
                      k.HoTen, k.SoDienThoai
               FROM ve v
               LEFT JOIN khachhang k ON v.MaKhachHang = k.MaKhachHang";
$params = [];
$types = "ii";
if ($search_term) {
    $base_query .= " WHERE v.MaVe LIKE ? OR k.HoTen LIKE ? OR k.SoDienThoai LIKE ?";
    $params = [$search_param, $search_param, $search_param];
    $types = "sss" . $types;
}
$base_query .= " ORDER BY v.NgayMua DESC LIMIT ?, ?";
$params[] = $start;
$params[] = $limit;

$stmt = $connetor->prepare($base_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$ve_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'stats' => $stats,
    've_list' => $ve_list,
    'total_pages' => $total_pages,
    'current_page' => $page
]);
?>