<?php
require_once 'koneksi.php';

// Cek apakah user sudah login
requireLogin();

// Ambil ID artikel dari URL
$artikel_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($artikel_id <= 0) {
    header('Location: index.php');
    exit();
}

// Ambil ID penulis yang login
$penulis_id = $_SESSION['penulis_id'];

// Cek apakah artikel milik user yang sedang login dan ambil nama gambarnya
$article = null;
try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.judul, a.gambar
        FROM artikel a
        JOIN artikel_penulis ap ON a.id = ap.id_artikel
        WHERE a.id = :artikel_id AND ap.id_penulis = :penulis_id
    ");
    $stmt->bindParam(':artikel_id', $artikel_id, PDO::PARAM_INT);
    $stmt->bindParam(':penulis_id', $penulis_id, PDO::PARAM_INT);
    $stmt->execute();
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$article) {
        header('Location: index.php');
        exit("Artikel tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya.");
    }
} catch(PDOException $e) {
    header('Location: index.php');
    exit("Terjadi kesalahan sistem saat memverifikasi artikel.");
}

// Proses penghapusan artikel
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Mulai transaction
        $pdo->beginTransaction();

        // 1. Dapatkan nama file gambar sebelum dihapus dari database
        $gambar_file = $article['gambar'];
        $gambar_path = 'assets/image/' . $gambar_file;

        // 2. Hapus relasi dari artikel_kategori (jika ada)
        $stmt_kategori = $pdo->prepare("DELETE FROM artikel_kategori WHERE id_artikel = :artikel_id");
        $stmt_kategori->bindParam(':artikel_id', $artikel_id, PDO::PARAM_INT);
        $stmt_kategori->execute();

        // 3. Hapus relasi dari artikel_penulis
        $stmt_penulis = $pdo->prepare("DELETE FROM artikel_penulis WHERE id_artikel = :artikel_id");
        $stmt_penulis->bindParam(':artikel_id', $artikel_id, PDO::PARAM_INT);
        $stmt_penulis->execute();
        
        // 4. Hapus artikel utama
        $stmt_artikel = $pdo->prepare("DELETE FROM artikel WHERE id = :artikel_id");
        $stmt_artikel->bindParam(':artikel_id', $artikel_id, PDO::PARAM_INT);
        $stmt_artikel->execute();
        
        // Commit transaction
        $pdo->commit();

        // 5. Hapus file gambar dari server lokal (setelah berhasil dihapus dari DB)
        if (!empty($gambar_file) && file_exists($gambar_path) && strpos($gambar_file, 'gambar_artikel_') === 0) {
            unlink($gambar_path);
        }

        // Redirect ke index dengan pesan sukses
        header('Location: index.php?deleted=1');
        exit();
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = 'Terjadi kesalahan saat menghapus artikel: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Artikel - Kota Madura</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gradient-to-br from-yellow-50 to-yellow-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-yellow-600 to-yellow-700 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-white">Kota Madura</h1>
                    <p class="text-yellow-100">Portal Artikel & Berita</p>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-yellow-100">Selamat datang, <?php echo htmlspecialchars($_SESSION['penulis_nama']); ?></span>
                    <a href="index.php?logout=1" class="bg-yellow-800 hover:bg-yellow-900 text-white px-4 py-2 rounded-md transition duration-300">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-yellow-500 shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8 py-4">
                <a href="index.php" class="text-white font-medium hover:text-yellow-200 transition duration-300">Dashboard</a>
                <a href="tambah_artikel.php" class="text-white font-medium hover:text-yellow-200 transition duration-300">Tambah Artikel</a>
                <span class="text-yellow-200 font-medium border-b-2 border-yellow-200">Hapus Artikel</span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-red-600 mb-2">Konfirmasi Penghapusan</h2>
                <p class="text-gray-600">Anda yakin ingin menghapus artikel ini?</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
                <h3 class="text-lg font-semibold text-yellow-800 mb-2">Artikel yang akan dihapus:</h3>
                <p class="text-yellow-700 font-medium"><?php echo htmlspecialchars($article['judul']); ?></p>
            </div>

            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Peringatan!</h3>
                        <p class="text-sm text-red-700 mt-1">
                            Tindakan ini tidak dapat dibatalkan. Artikel akan dihapus secara permanen dari sistem.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" action="">
                <div class="flex justify-center space-x-4">
                    <button type="submit" name="confirm_delete" value="1"
                            class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-md transition duration-300 flex items-center">
                        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Ya, Hapus Artikel
                    </button>
                    <a href="index.php" 
                       class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-md transition duration-300 inline-flex items-center">
                        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-yellow-800 text-white mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <p>&copy; 2024 Kota Madura. Semua hak dilindungi.</p>
            </div>
        </div>
    </footer>
</body>
</html>