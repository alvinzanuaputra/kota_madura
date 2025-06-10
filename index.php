<?php
require_once 'koneksi.php';

// Cek apakah user sudah login
requireLogin();

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Ambil ID penulis yang login
$penulis_id = $_SESSION['penulis_id'];

// Ambil ID kategori dari URL jika ada
$kategori_id = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : null;

// Ambil semua kategori untuk filter
$categories = [];
try {
    $stmt_kategori = $pdo->prepare("SELECT id, nama FROM kategori ORDER BY nama ASC");
    $stmt_kategori->execute();
    $categories = $stmt_kategori->fetchAll();
} catch(PDOException $e) {
    // Anda bisa tambahkan logging error di sini
    // error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}


// Ambil artikel berdasarkan penulis yang login dan kategori (jika dipilih)
$articles = [];
try {
    $sql = "
        SELECT
            a.*,
            p.nama AS penulis_nama,
            GROUP_CONCAT(k.nama ORDER BY k.nama ASC SEPARATOR ', ') AS kategori_nama
        FROM
            artikel a
        JOIN
            artikel_penulis ap ON a.id = ap.id_artikel
        JOIN
            penulis p ON ap.id_penulis = p.id
        LEFT JOIN
            artikel_kategori ak_concat ON a.id = ak_concat.id_artikel
        LEFT JOIN
            kategori k ON ak_concat.id_kategori = k.id
    ";
    $conditions = ["ap.id_penulis = :penulis_id"]; // Selalu filter berdasarkan penulis yang login
    $params = [':penulis_id' => $penulis_id];

    if ($kategori_id) {
        $sql .= " JOIN artikel_kategori ak_filter ON a.id = ak_filter.id_artikel";
        $conditions[] = "ak_filter.id_kategori = :kategori_id";
        $params[':kategori_id'] = $kategori_id;
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " GROUP BY a.id"; // Penting untuk GROUP_CONCAT agar kategori digabungkan per artikel
    $sql .= " ORDER BY a.tanggal DESC"; // Urutkan berdasarkan tanggal artikel

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        // Menggunakan bindValue untuk penanganan tipe data yang lebih fleksibel
        $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $articles = $stmt->fetchAll();

} catch(PDOException $e) {
    // Tambahkan logging error untuk debugging lebih lanjut jika diperlukan
    // error_log("Error fetching articles: " . $e->getMessage());
    $articles = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Kota Madura</title>
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
                    <a href="?logout=1" class="bg-yellow-800 hover:bg-yellow-900 text-white px-4 py-2 rounded-md transition duration-300">
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
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-yellow-800 mb-4">Daftar Artikel</h2>
            <a href="tambah_artikel.php" 
               class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-md transition duration-300 inline-block">
                + Tambah Artikel Baru
            </a>
        </div>

        <!-- Articles Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php if (empty($articles)): ?>
                <div class="col-span-full text-center py-12">
                    <div class="text-yellow-600 text-lg">Belum ada artikel yang dipublikasikan.</div>
                    <a href="tambah_artikel.php" class="text-yellow-800 font-medium hover:underline mt-2 inline-block">
                        Buat artikel pertama Anda
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($articles as $article): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                        <?php if (!empty($article['gambar'])): ?>
                            <img src="assets/image/<?php echo htmlspecialchars($article['gambar']); ?>" 
                                 alt="<?php echo htmlspecialchars($article['judul']); ?>" 
                                 class="w-full h-48 object-cover">
                        <?php endif; ?>
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-yellow-800 mb-2 line-clamp-2">
                                <?php echo htmlspecialchars($article['judul']); ?>
                            </h3>
                            <p class="text-gray-600 mb-4 line-clamp-3">
                                <?php echo htmlspecialchars(substr(strip_tags($article['isi']), 0, 150)); ?>...
                            </p>
                            <div class="flex justify-between items-center text-sm text-gray-500 mb-2">
                                <?php if (!empty($article['kategori_nama'])): ?>
                                    <span class="text-yellow-700 font-semibold">Kategori: <?php echo htmlspecialchars($article['kategori_nama']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-between items-center text-sm text-gray-500 mb-4">
                                <span>Oleh: <?php echo htmlspecialchars($article['penulis_nama']); ?></span>
                                <span><?php echo date('d M Y', strtotime($article['tanggal'])); ?></span>
                            </div>
                            <div class="flex space-x-2">
                                <a href="edit_artikel.php?id=<?php echo $article['id']; ?>" 
                                   class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-300">
                                    Edit
                                </a>
                                <a href="hapus_artikel.php?id=<?php echo $article['id']; ?>" 
                                   class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition duration-300"
                                   onclick="return confirm('Yakin ingin menghapus artikel ini?')">
                                    Hapus
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</body>
</html>