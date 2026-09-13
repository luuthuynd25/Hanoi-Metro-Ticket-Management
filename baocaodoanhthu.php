<?php
include 'connect.php'; // Kết nối cơ sở dữ liệu từ file connect.php

// Xử lý tìm kiếm theo khoảng thời gian
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$report_type = isset($_POST['report_type']) ? $_POST['report_type'] : 'day';

// Truy vấn dữ liệu doanh thu từ bảng `ve`
$sql = "
    SELECT 
        DATE(v.NgayMua) AS Ngay,
        COUNT(v.MaVe) AS TongVeBan,
        SUM(CASE WHEN v.TrangThai = 'DaThanhToan' THEN 50000 ELSE 0 END) AS TongDoanhThu
    FROM ve v
    WHERE v.NgayMua BETWEEN ? AND ?
    GROUP BY DATE(v.NgayMua)
";
if ($report_type == 'month') {
    $sql = "
        SELECT 
            DATE_FORMAT(v.NgayMua, '%Y-%m') AS Thang,
            COUNT(v.MaVe) AS TongVeBan,
            SUM(CASE WHEN v.TrangThai = 'DaThanhToan' THEN 50000 ELSE 0 END) AS TongDoanhThu
        FROM ve v
        WHERE v.NgayMua BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(v.NgayMua, '%Y-%m')
    ";
} elseif ($report_type == 'quarter') {
    $sql = "
        SELECT 
            CONCAT(YEAR(v.NgayMua), '-Q', QUARTER(v.NgayMua)) AS Quy,
            COUNT(v.MaVe) AS TongVeBan,
            SUM(CASE WHEN v.TrangThai = 'DaThanhToan' THEN 50000 ELSE 0 END) AS TongDoanhThu
        FROM ve v
        WHERE v.NgayMua BETWEEN ? AND ?
        GROUP BY YEAR(v.NgayMua), QUARTER(v.NgayMua)
    ";
} elseif ($report_type == 'year') {
    $sql = "
        SELECT 
            YEAR(v.NgayMua) AS Nam,
            COUNT(v.MaVe) AS TongVeBan,
            SUM(CASE WHEN v.TrangThai = 'DaThanhToan' THEN 50000 ELSE 0 END) AS TongDoanhThu
        FROM ve v
        WHERE v.NgayMua BETWEEN ? AND ?
        GROUP BY YEAR(v.NgayMua)
    ";
}

$stmt = $connetor->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$total_tickets = 0;
$total_revenue = 0;
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
    $total_tickets += $row['TongVeBan'];
    $total_revenue += $row['TongDoanhThu'];
}
?>

<div class="report-container">
    <h2>Báo Cáo Doanh Thu</h2>
    <form class="filter-form" method="POST">
        <label>Từ ngày:</label>
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        <label>Đến ngày:</label>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
        <label>Loại báo cáo:</label>
        <select name="report_type">
            <option value="day" <?php echo $report_type == 'day' ? 'selected' : ''; ?>>Theo ngày</option>
            <option value="month" <?php echo $report_type == 'month' ? 'selected' : ''; ?>>Theo tháng</option>
            <option value="quarter" <?php echo $report_type == 'quarter' ? 'selected' : ''; ?>>Theo quý</option>
            <option value="year" <?php echo $report_type == 'year' ? 'selected' : ''; ?>>Theo năm</option>
        </select>
        <button type="submit">Tìm kiếm</button>
    </form>

    <table class="report-table">
        <thead>
            <tr>
                <th><?php echo $report_type == 'day' ? 'Ngày' : ($report_type == 'month' ? 'Tháng' : ($report_type == 'quarter' ? 'Quý' : 'Năm')); ?></th>
                <th>Tổng vé bán</th>
                <th>Tổng doanh thu (VND)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data)): ?>
                <tr>
                    <td colspan="3">Không có dữ liệu trong khoảng thời gian này.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row[$report_type == 'day' ? 'Ngay' : ($report_type == 'month' ? 'Thang' : ($report_type == 'quarter' ? 'Quy' : 'Nam'))]); ?></td>
                        <td><?php echo htmlspecialchars($row['TongVeBan']); ?></td>
                        <td><?php echo number_format($row['TongDoanhThu'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="summary">
        <p>Tổng số vé bán: <?php echo htmlspecialchars($total_tickets); ?></p>
        <p>Tổng doanh thu: <?php echo number_format($total_revenue, 2); ?> VND</p>
    </div>
</div>

<style>
    .report-container {
        padding: 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin: 20px;
    }
    .filter-form {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        align-items: center;
        flex-wrap: wrap;
    }
    .filter-form label {
        font-weight: bold;
    }
    .filter-form input, .filter-form select {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .filter-form button {
        padding: 8px 15px;
        background: #007bff;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.3s;
    }
    .filter-form button:hover {
        background: #0056b3;
    }
    .report-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .report-table th, .report-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    .report-table th {
        background: #f8f9fa;
        font-weight: bold;
    }
    .report-table td {
        font-size: 14px;
    }
    .summary {
        font-weight: bold;
        font-size: 1.1em;
        padding: 10px 0;
    }
</style>