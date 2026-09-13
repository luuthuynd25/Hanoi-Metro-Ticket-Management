-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 17, 2025 at 03:30 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `quanlyvetau1`
--

-- --------------------------------------------------------

--
-- Table structure for table `baocaodoanhthu`
--

CREATE TABLE `baocaodoanhthu` (
  `MaBaoCao` varchar(50) NOT NULL,
  `ThoiGian` datetime DEFAULT NULL,
  `TongVeBan` int(11) DEFAULT NULL,
  `TongDoanhThu` decimal(15,2) DEFAULT NULL,
  `MaNhanVien` int(11) DEFAULT NULL,
  `ChiTietKenhBan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ga`
--

CREATE TABLE `ga` (
  `MaGa` varchar(50) NOT NULL,
  `TenGa` varchar(100) DEFAULT NULL,
  `ViTriDiaLy` varchar(200) DEFAULT NULL,
  `SoSanGa` int(11) DEFAULT NULL,
  `TrangThai` enum('hoatdong','ngunghoatdong') DEFAULT 'hoatdong'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hanhtrinh`
--

CREATE TABLE `hanhtrinh` (
  `MaHanhTrinh` varchar(50) NOT NULL,
  `MoTa` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `khachhang`
--

CREATE TABLE `khachhang` (
  `MaKhachHang` varchar(50) NOT NULL,
  `HoTen` varchar(100) DEFAULT NULL,
  `SoDienThoai` varchar(20) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `khach_hang_register`
--

CREATE TABLE `khach_hang_register` (
  `MaKhachHang` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_account` varchar(255) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `gender` enum('nam','nu','khac') DEFAULT 'khac',
  `nam_sinh` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lichsumuave`
--

CREATE TABLE `lichsumuave` (
  `MaLichSu` varchar(50) NOT NULL,
  `MaKhachHang` varchar(50) DEFAULT NULL,
  `MaVe` varchar(50) DEFAULT NULL,
  `NgayMua` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quanlydoantau`
--

CREATE TABLE `quanlydoantau` (
  `id` int(11) NOT NULL,
  `so_luong_toa` int(11) DEFAULT NULL,
  `suc_chua` int(11) DEFAULT NULL,
  `gio_xuat_phat` datetime DEFAULT NULL,
  `gio_den` datetime DEFAULT NULL,
  `thoi_gian_dung_tai_cac_ga` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quanlygatau`
--

CREATE TABLE `quanlygatau` (
  `id` int(11) NOT NULL,
  `MaGa` varchar(50) DEFAULT NULL,
  `so_luong_san_ga` int(11) DEFAULT NULL,
  `suc_chua_hanh_khach` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `register`
--

CREATE TABLE `register` (
  `MaNhanVien` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_account` varchar(255) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `gender` enum('nam','nu','khac') DEFAULT 'khac'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `register`
--

INSERT INTO `register` (`MaNhanVien`, `email`, `password_account`, `full_name`, `gender`) VALUES
(0, 'admin@gmail.com', '123', 'Nguyễn Đức Thịnh', 'nam');

-- --------------------------------------------------------

--
-- Table structure for table `tau`
--

CREATE TABLE `tau` (
  `MaTau` varchar(50) NOT NULL,
  `SoHieu` varchar(50) DEFAULT NULL,
  `LoaiTau` varchar(50) DEFAULT NULL,
  `SucChua` int(11) DEFAULT NULL,
  `TrangThaiHoatDong` enum('hoatdong','ngunghoatdong') DEFAULT 'hoatdong',
  `NgaySanXuat` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `thongbaokhachhang`
--

CREATE TABLE `thongbaokhachhang` (
  `MaThongBao` varchar(50) NOT NULL,
  `MaKhachHang` varchar(50) DEFAULT NULL,
  `NoiDung` text DEFAULT NULL,
  `NgayGui` datetime DEFAULT NULL,
  `TrangThaiDoc` enum('dadoc','chuadoc') DEFAULT 'chuadoc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `toatau`
--

CREATE TABLE `toatau` (
  `MaToa` varchar(50) NOT NULL,
  `MaTau` varchar(50) DEFAULT NULL,
  `LoaiToa` varchar(50) DEFAULT NULL,
  `SucChua` int(11) DEFAULT NULL,
  `TrangThaiSuDung` enum('hoatdong','ngunghoatdong') DEFAULT 'hoatdong'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ve`
--

CREATE TABLE `ve` (
  `MaVe` varchar(50) NOT NULL,
  `MaKhachHang` varchar(50) DEFAULT NULL,
  `MaHanhTrinh` varchar(50) DEFAULT NULL,
  `NgayMua` datetime DEFAULT NULL,
  `LoaiVe` varchar(50) DEFAULT NULL,
  `PhuongThucThanhToan` enum('tienmat','the','tructuyen') DEFAULT 'tienmat',
  `TrangThai` enum('hoatdong','dahuy','dasudung') DEFAULT 'hoatdong',
  `GiaVe` decimal(15,2) DEFAULT NULL,
  `GaDi` varchar(50) DEFAULT NULL,
  `GaDen` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vedahuy`
--

CREATE TABLE `vedahuy` (
  `MaHuy` varchar(50) NOT NULL,
  `MaVe` varchar(50) DEFAULT NULL,
  `NgayHuy` datetime DEFAULT NULL,
  `LyDo` text DEFAULT NULL,
  `HoanTien` enum('co','khong') DEFAULT 'khong',
  `TrangThaiXuLy` enum('dangxuly','daxuly','tuchoi') DEFAULT 'dangxuly'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vedamua`
--

CREATE TABLE `vedamua` (
  `id` int(11) NOT NULL,
  `khach_hang_id` int(11) DEFAULT NULL,
  `MaVe` varchar(50) DEFAULT NULL,
  `so_luong_ve` int(11) DEFAULT NULL,
  `ngay_su_dung` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vitienkhachhang`
--

CREATE TABLE `vitienkhachhang` (
  `MaKhachHang` varchar(50) NOT NULL,
  `SoDu` decimal(15,2) DEFAULT NULL,
  `NgayCapNhat` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `yeucauhuyve`
--

CREATE TABLE `yeucauhuyve` (
  `MaYeuCau` varchar(50) NOT NULL,
  `MaKhachHang` varchar(50) DEFAULT NULL,
  `MaVe` varchar(50) DEFAULT NULL,
  `LyDo` text DEFAULT NULL,
  `KetQuaXuLy` enum('dangxuly','duocphep','tuchoi') DEFAULT 'dangxuly'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `baocaodoanhthu`
--
ALTER TABLE `baocaodoanhthu`
  ADD PRIMARY KEY (`MaBaoCao`),
  ADD KEY `MaNhanVien` (`MaNhanVien`);

--
-- Indexes for table `ga`
--
ALTER TABLE `ga`
  ADD PRIMARY KEY (`MaGa`);

--
-- Indexes for table `hanhtrinh`
--
ALTER TABLE `hanhtrinh`
  ADD PRIMARY KEY (`MaHanhTrinh`);

--
-- Indexes for table `khachhang`
--
ALTER TABLE `khachhang`
  ADD PRIMARY KEY (`MaKhachHang`);

--
-- Indexes for table `khach_hang_register`
--
ALTER TABLE `khach_hang_register`
  ADD PRIMARY KEY (`MaKhachHang`);

--
-- Indexes for table `lichsumuave`
--
ALTER TABLE `lichsumuave`
  ADD PRIMARY KEY (`MaLichSu`),
  ADD KEY `MaKhachHang` (`MaKhachHang`),
  ADD KEY `MaVe` (`MaVe`);

--
-- Indexes for table `quanlydoantau`
--
ALTER TABLE `quanlydoantau`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quanlygatau`
--
ALTER TABLE `quanlygatau`
  ADD PRIMARY KEY (`id`),
  ADD KEY `MaGa` (`MaGa`);

--
-- Indexes for table `register`
--
ALTER TABLE `register`
  ADD PRIMARY KEY (`MaNhanVien`);

--
-- Indexes for table `tau`
--
ALTER TABLE `tau`
  ADD PRIMARY KEY (`MaTau`);

--
-- Indexes for table `thongbaokhachhang`
--
ALTER TABLE `thongbaokhachhang`
  ADD PRIMARY KEY (`MaThongBao`),
  ADD KEY `MaKhachHang` (`MaKhachHang`);

--
-- Indexes for table `toatau`
--
ALTER TABLE `toatau`
  ADD PRIMARY KEY (`MaToa`),
  ADD KEY `MaTau` (`MaTau`);

--
-- Indexes for table `ve`
--
ALTER TABLE `ve`
  ADD PRIMARY KEY (`MaVe`),
  ADD KEY `MaKhachHang` (`MaKhachHang`),
  ADD KEY `MaHanhTrinh` (`MaHanhTrinh`),
  ADD KEY `GaDi` (`GaDi`),
  ADD KEY `GaDen` (`GaDen`);

--
-- Indexes for table `vedahuy`
--
ALTER TABLE `vedahuy`
  ADD PRIMARY KEY (`MaHuy`),
  ADD KEY `MaVe` (`MaVe`);

--
-- Indexes for table `vedamua`
--
ALTER TABLE `vedamua`
  ADD PRIMARY KEY (`id`),
  ADD KEY `khach_hang_id` (`khach_hang_id`),
  ADD KEY `MaVe` (`MaVe`);

--
-- Indexes for table `vitienkhachhang`
--
ALTER TABLE `vitienkhachhang`
  ADD PRIMARY KEY (`MaKhachHang`);

--
-- Indexes for table `yeucauhuyve`
--
ALTER TABLE `yeucauhuyve`
  ADD PRIMARY KEY (`MaYeuCau`),
  ADD KEY `MaKhachHang` (`MaKhachHang`),
  ADD KEY `MaVe` (`MaVe`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `baocaodoanhthu`
--
ALTER TABLE `baocaodoanhthu`
  ADD CONSTRAINT `baocaodoanhthu_ibfk_1` FOREIGN KEY (`MaNhanVien`) REFERENCES `register` (`MaNhanVien`);

--
-- Constraints for table `lichsumuave`
--
ALTER TABLE `lichsumuave`
  ADD CONSTRAINT `lichsumuave_ibfk_1` FOREIGN KEY (`MaKhachHang`) REFERENCES `khachhang` (`MaKhachHang`),
  ADD CONSTRAINT `lichsumuave_ibfk_2` FOREIGN KEY (`MaVe`) REFERENCES `ve` (`MaVe`);

--
-- Constraints for table `quanlygatau`
--
ALTER TABLE `quanlygatau`
  ADD CONSTRAINT `quanlygatau_ibfk_1` FOREIGN KEY (`MaGa`) REFERENCES `ga` (`MaGa`);

--
-- Constraints for table `thongbaokhachhang`
--
ALTER TABLE `thongbaokhachhang`
  ADD CONSTRAINT `thongbaokhachhang_ibfk_1` FOREIGN KEY (`MaKhachHang`) REFERENCES `khachhang` (`MaKhachHang`);

--
-- Constraints for table `toatau`
--
ALTER TABLE `toatau`
  ADD CONSTRAINT `toatau_ibfk_1` FOREIGN KEY (`MaTau`) REFERENCES `tau` (`MaTau`);

--
-- Constraints for table `ve`
--
ALTER TABLE `ve`
  ADD CONSTRAINT `ve_ibfk_1` FOREIGN KEY (`MaKhachHang`) REFERENCES `khachhang` (`MaKhachHang`),
  ADD CONSTRAINT `ve_ibfk_2` FOREIGN KEY (`MaHanhTrinh`) REFERENCES `hanhtrinh` (`MaHanhTrinh`),
  ADD CONSTRAINT `ve_ibfk_3` FOREIGN KEY (`GaDi`) REFERENCES `ga` (`MaGa`),
  ADD CONSTRAINT `ve_ibfk_4` FOREIGN KEY (`GaDen`) REFERENCES `ga` (`MaGa`);

--
-- Constraints for table `vedahuy`
--
ALTER TABLE `vedahuy`
  ADD CONSTRAINT `vedahuy_ibfk_1` FOREIGN KEY (`MaVe`) REFERENCES `ve` (`MaVe`);

--
-- Constraints for table `vedamua`
--
ALTER TABLE `vedamua`
  ADD CONSTRAINT `vedamua_ibfk_1` FOREIGN KEY (`khach_hang_id`) REFERENCES `khach_hang_register` (`MaKhachHang`),
  ADD CONSTRAINT `vedamua_ibfk_2` FOREIGN KEY (`MaVe`) REFERENCES `ve` (`MaVe`);

--
-- Constraints for table `vitienkhachhang`
--
ALTER TABLE `vitienkhachhang`
  ADD CONSTRAINT `vitienkhachhang_ibfk_1` FOREIGN KEY (`MaKhachHang`) REFERENCES `khachhang` (`MaKhachHang`);

--
-- Constraints for table `yeucauhuyve`
--
ALTER TABLE `yeucauhuyve`
  ADD CONSTRAINT `yeucauhuyve_ibfk_1` FOREIGN KEY (`MaKhachHang`) REFERENCES `khachhang` (`MaKhachHang`),
  ADD CONSTRAINT `yeucauhuyve_ibfk_2` FOREIGN KEY (`MaVe`) REFERENCES `ve` (`MaVe`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
