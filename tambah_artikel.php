<?php
require_once 'koneksi.php';

// Cek apakah user sudah login
requireLogin();

$error = '';
$success = '';
$all_categories = []; // Untuk menyimpan semua kategori yang tersedia

// Ambil ID penulis yang login
$penulis_id = $_SESSION['penulis_id'];

// --- Ambil semua kategori untuk form ---
try {
    $stmt_kategori = $pdo->prepare("SELECT id, nama FROM kategori ORDER BY nama ASC");
    $stmt_kategori->execute();
    $all_categories = $stmt_kategori->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Terjadi kesalahan saat mengambil daftar kategori!';
    // error_log("Error fetching categories for add article: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $judul = trim($_POST['judul']);
    $isi = trim($_POST['isi']); // Menggunakan 'isi' sesuai schema database
    $kategori_id = isset($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : 0; // Ambil ID kategori tunggal
    $gambar_nama_file_temp = null; // Nama file sementara saat upload
    $file_extension = null; // Ekstensi file asli

    if (empty($judul) || empty($isi)) {
        $error = 'Judul dan isi artikel harus diisi!';
    } elseif ($kategori_id === 0) { // Validasi kategori tidak dipilih
        $error = 'Pilih kategori artikel!';
    } else {
        try {
            // Mulai transaction
            $pdo->beginTransaction();
            
            // --- Proses Upload Gambar (Tahap 1: Simpan dengan nama sementara) ---
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == UPLOAD_ERR_OK) {
                $target_dir = "assets/image/"; // <-- Pastikan ini 'assets/image/'
                $file_extension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                $gambar_nama_file_temp = uniqid('temp_gambar_artikel_', true) . '.' . $file_extension; // Nama sementara
                $target_file_temp = $target_dir . $gambar_nama_file_temp;
                $uploadOk = 1;
                $imageFileType = strtolower($file_extension);

                // Check if image file is a actual image or fake image
                $check = getimagesize($_FILES['gambar']['tmp_name']);
                if($check !== false) {
                    $uploadOk = 1;
                } else {
                    $error = "File bukan gambar.";
                    $uploadOk = 0;
                }

                // Check file size
                if ($_FILES['gambar']['size'] > 5000000) { // 5MB
                    $error = "Maaf, ukuran file gambar terlalu besar.";
                    $uploadOk = 0;
                }

                // Allow certain file formats
                if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
                    $error = "Maaf, hanya file JPG, JPEG, PNG & GIF yang diizinkan.";
                    $uploadOk = 0;
                }

                if ($uploadOk == 1) {
                    if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $target_file_temp)) {
                        $error = "Maaf, ada kesalahan saat mengupload gambar Anda.";
                    }
                }
            }

            // Jika ada error upload atau validasi lain, batalkan operasi database
            if ($error) {
                $pdo->rollBack();
            } else {
                // Insert artikel dengan nama gambar SEMENTARA (jika ada)
                // Gunakan kolom 'isi' dan 'tanggal' sesuai schema DB
                $stmt = $pdo->prepare("INSERT INTO artikel (judul, isi, gambar, tanggal) VALUES (?, ?, ?, ?)");
                $stmt->execute([$judul, $isi, $gambar_nama_file_temp, date('Y-m-d')]); // Simpan nama sementara
                
                $artikel_id = $pdo->lastInsertId();
                
                // --- Proses Update Gambar (Tahap 2: Rename dan Update DB) ---
                if ($gambar_nama_file_temp) {
                    $final_gambar_nama_file = 'gambar_artikel_' . $artikel_id . '.' . $file_extension;
                    $final_target_file = $target_dir . $final_gambar_nama_file;
                    
                    if (rename($target_file_temp, $final_target_file)) {
                        // Update nama gambar di database dengan nama final
                        $stmt_update_gambar = $pdo->prepare("UPDATE artikel SET gambar = ? WHERE id = ?");
                        $stmt_update_gambar->execute([$final_gambar_nama_file, $artikel_id]);
                    } else {
                        // Jika rename gagal, log error dan mungkin batalkan transaksi
                        $error = "Gagal mengganti nama file gambar.";
                        $pdo->rollBack(); // Rollback karena rename gagal
                        // error_log("Failed to rename uploaded file: " . $target_file_temp . " to " . $final_target_file);
                    }
                }

                if (!$error) { // Lanjutkan hanya jika tidak ada error rename
                    // Insert ke artikel_penulis
                    // Gunakan kolom 'id_artikel' dan 'id_penulis' sesuai schema DB
                    $stmt = $pdo->prepare("INSERT INTO artikel_penulis (id_artikel, id_penulis) VALUES (?, ?)");
                    $stmt->execute([$artikel_id, $penulis_id]);
                    
                    // Insert ke artikel_kategori (hanya satu kategori)
                    $stmt_insert_kategori = $pdo->prepare("INSERT INTO artikel_kategori (id_artikel, id_kategori) VALUES (?, ?)");
                    $stmt_insert_kategori->execute([$artikel_id, $kategori_id]);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $success = 'Artikel berhasil ditambahkan!';
                    
                    // Redirect ke index setelah 2 detik
                    header("refresh:2;url=index.php");
                }
            }
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan saat menyimpan artikel: ' . $e->getMessage();
            // error_log("Error adding article: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Artikel - Kota Madura</title>
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
                <a href="tambah_artikel.php" class="text-yellow-200 font-medium border-b-2 border-yellow-200">Tambah Artikel</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-yellow-800 mb-2">Tambah Artikel Baru</h2>
                <p class="text-yellow-600">Tulis artikel menarik tentang Kota Madura</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($success); ?>
                    <br><small>Anda akan diarahkan ke dashboard dalam beberapa detik...</small>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data"> <!-- Tambahkan enctype -->
                <div class="mb-6">
                    <label for="judul" class="block text-yellow-800 text-sm font-bold mb-2">Judul Artikel</label>
                    <input type="text" id="judul" name="judul" required
                           class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                           placeholder="Masukkan judul artikel yang menarik..."
                           value="<?php echo isset($_POST['judul']) ? htmlspecialchars($_POST['judul']) : ''; ?>">
                </div>

                <div class="mb-6">
                    <label for="isi" class="block text-yellow-800 text-sm font-bold mb-2">Isi Artikel</label>
                    <textarea id="isi" name="isi" rows="12" required
                              class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent resize-vertical"
                              placeholder="Tulis konten artikel di sini..."><?php echo isset($_POST['isi']) ? htmlspecialchars($_POST['isi']) : ''; ?></textarea>
                </div>

                <div class="mb-6">
                    <label for="gambar" class="block text-yellow-800 text-sm font-bold mb-2">Gambar Artikel (Opsional)</label>
                    <input type="file" id="gambar" name="gambar" 
                           class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                    <p class="text-gray-500 text-sm mt-1">Ukuran maksimal 5MB. Format: JPG, JPEG, PNG, GIF.</p>
                </div>

                <div class="mb-6">
                    <label for="kategori_id" class="block text-yellow-800 text-sm font-bold mb-2">Pilih Kategori</label>
                    <select id="kategori_id" name="kategori_id" required
                            class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                        <option value="">-- Pilih Kategori --</option>
                        <?php 
                        // Simpan kategori yang terakhir dipilih jika ada error
                        $prev_selected_category_id = isset($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : 0;
                        ?>
                        <?php foreach ($all_categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"
                                <?php echo ($category['id'] == $prev_selected_category_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['nama']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($all_categories)): ?>
                        <p class="text-gray-500 text-sm mt-1">Tidak ada kategori tersedia. Harap tambahkan kategori di database.</p>
                    <?php endif; ?>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" 
                            class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-6 rounded-md transition duration-300">
                        Simpan Artikel
                    </button>
                    <a href="index.php" 
                       class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-md transition duration-300 inline-block">
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