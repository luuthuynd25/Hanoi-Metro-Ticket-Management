<?php
$server = 'localhost';
$user = 'root';
$pass = '';
$database = 'quanlyvetau';

$connetor = new mysqli($server, $user, $pass, $database);

if ($connetor->connect_error) {
    die("Kết nối thất bại: " . $connetor->connect_error);
}
$connetor->set_charset("utf8");

?>