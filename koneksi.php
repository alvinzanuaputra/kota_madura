<?php
// Konfigurasi database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'kota_madura';
$port = 3306;

// Membuat koneksi
try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;port=$port", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

// Memulai session
session_start();

// Fungsi untuk mengecek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['penulis_id']) && !empty($_SESSION['penulis_id']);
}

// Fungsi untuk redirect jika belum login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Fungsi untuk redirect jika sudah login
function requireLogout() {
    if (isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}
?>