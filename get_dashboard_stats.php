<?php
require_once 'connect.php';

header('Content-Type: application/json');

function getStatistics($connetor) {
    $stats = [];
    
    $sql = "SELECT COUNT(*) AS total FROM Ga WHERE TrangThai = 'HoatDong'";
    $result = $connetor->query($sql);
    $stats['total_ga'] = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
    
    $sql = "SELECT COUNT(*) AS total FROM Tau WHERE TrangThaiHoatDong = 'HoatDong'";
    $result = $connetor->query($sql);
    $stats['total_tau'] = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
    
    $sql = "SELECT COUNT(*) AS total FROM Ve WHERE TrangThai = 'DaThanhToan'";
    $result = $connetor->query($sql);
    $stats['total_ve'] = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
    
    $sql = "SELECT COUNT(*) AS total FROM KhachHang";
    $result = $connetor->query($sql);
    $stats['total_khachhang'] = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
    
    return $stats;
}

$stats = getStatistics($connetor);
echo json_encode($stats);
$connetor->close();
?>