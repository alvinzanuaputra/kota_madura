<?php
require_once 'koneksi.php';

// Cek apakah user sudah login
requireLogin();

$error = '';
$success = '';
$artikel = null;
$kategori_terpilih_id = 0; // Untuk menyimpan ID kategori yang sedang terpilih
$all_categories = []; // Untuk menyimpan semua kategori yang tersedia

// Ambil ID penulis yang login dari sesi
$penulis_id = $_SESSION['penulis_id'];

// Ambil ID artikel dari parameter URL
$artikel_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- Ambil semua kategori untuk dropdown form ---
try {
    $stmt_kategori = $pdo->prepare("SELECT id, nama FROM kategori ORDER BY nama ASC");
    $stmt_kategori->execute();
    $all_categories = $stmt_kategori->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Terjadi kesalahan saat mengambil daftar kategori!';
    // error_log("Error fetching categories for edit article: " . $e->getMessage());
}

// --- Fetch data artikel yang akan diedit ---
if ($artikel_id > 0) {
    try {
        // Ambil artikel, pastikan penulis yang login adalah pemiliknya, dan ambil kategori terkait
        $stmt = $pdo->prepare("
            SELECT 
                a.*, 
                ap.id_penulis,
                ak.id_kategori
            FROM artikel a
            JOIN artikel_penulis ap ON a.id = ap.id_artikel  -- Koreksi nama kolom
            LEFT JOIN artikel_kategori ak ON a.id = ak.id_artikel -- Ambil kategori terkait (LEFT JOIN karena mungkin belum ada kategori)
            WHERE a.id = :artikel_id AND ap.id_penulis = :penulis_id
        ");
        $stmt->bindParam(':artikel_id', $artikel_id, PDO::PARAM_INT);
        $stmt->bindParam(':penulis_id', $penulis_id, PDO::PARAM_INT);
        $stmt->execute();
        $artikel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$artikel) {
            // Artikel tidak ditemukan atau bukan milik penulis yang login
            header('Location: index.php');
            exit("Artikel tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.");
        }
        
        // Simpan ID kategori yang terpilih jika ada
        if (isset($artikel['id_kategori'])) {
            $kategori_terpilih_id = (int)$artikel['id_kategori'];
        }

    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan saat mengambil data artikel!';
        // error_log("Error fetching article for edit: " . $e->getMessage());
        $artikel = null;
    }
} else {
    header('Location: index.php');
    exit("ID artikel tidak valid.");
}

// --- Proses Form Submit ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $artikel) {
    $judul = trim($_POST['judul']);
    $isi = trim($_POST['isi']); // Menggunakan 'isi' sesuai schema database
    $kategori_id_form = isset($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : 0; // Ambil ID kategori dari form

    // Validasi input
    if (empty($judul) || empty($isi)) {
        $error = 'Judul dan isi artikel harus diisi!';
    } elseif ($kategori_id_form === 0) {
        $error = 'Pilih kategori artikel!';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Update data artikel utama
            // Gunakan kolom 'isi' dan 'tanggal' sesuai schema DB
            $stmt_update_artikel = $pdo->prepare("UPDATE artikel SET judul = ?, isi = ?, tanggal = ? WHERE id = ?");
            $stmt_update_artikel->execute([$judul, $isi, date('Y-m-d'), $artikel_id]); // Menggunakan 'tanggal' saat ini

            // 2. Handle upload gambar (jika ada file baru diupload)
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == UPLOAD_ERR_OK) {
                $target_dir = "assets/image/"; // Lokasi penyimpanan gambar
                $file_extension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                $new_file_name = 'gambar_artikel_' . $artikel_id . '.' . $file_extension; // Nama file final
                $target_file = $target_dir . $new_file_name;
                $uploadOk = 1;
                $imageFileType = strtolower($file_extension);

                // Validasi gambar
                $check = getimagesize($_FILES['gambar']['tmp_name']);
                if($check === false) { $error = "File bukan gambar."; $uploadOk = 0; }
                if ($_FILES['gambar']['size'] > 5000000) { $error = "Maaf, ukuran file gambar terlalu besar."; $uploadOk = 0; } // 5MB
                if(!in_array($imageFileType, ["jpg", "png", "jpeg", "gif"])) { $error = "Maaf, hanya JPG, JPEG, PNG & GIF yang diizinkan."; $uploadOk = 0; }

                if ($uploadOk == 0) {
                    $error = "Gagal mengupload gambar: " . $error; // Gabungkan error
                } else {
                    // Hapus gambar lama jika ada dan namanya mengikuti pola kita
                    if (!empty($artikel['gambar']) && file_exists($target_dir . $artikel['gambar']) && strpos($artikel['gambar'], 'gambar_artikel_') === 0) {
                         unlink($target_dir . $artikel['gambar']);
                    }
                    
                    if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target_file)) {
                        // Update nama gambar di database
                        $stmt_update_gambar = $pdo->prepare("UPDATE artikel SET gambar = ? WHERE id = ?");
                        $stmt_update_gambar->execute([$new_file_name, $artikel_id]);
                        $artikel['gambar'] = $new_file_name; // Update variabel artikel untuk tampilan di form
                    } else {
                        $error = "Maaf, ada kesalahan saat mengupload gambar baru.";
                    }
                }
            }

            // Jika ada error dari validasi upload gambar, batalkan transaksi
            if ($error) {
                $pdo->rollBack();
            } else {
                // 3. Update kategori artikel (hapus yang lama, sisipkan yang baru)
                // Hapus entri kategori yang ada untuk artikel ini
                $stmt_delete_kategori = $pdo->prepare("DELETE FROM artikel_kategori WHERE id_artikel = ?");
                $stmt_delete_kategori->execute([$artikel_id]);

                // Sisipkan kategori baru
                $stmt_insert_kategori = $pdo->prepare("INSERT INTO artikel_kategori (id_artikel, id_kategori) VALUES (?, ?)");
                $stmt_insert_kategori->execute([$artikel_id, $kategori_id_form]);
                
                // Commit transaction
                $pdo->commit();
                
                $success = 'Artikel berhasil diperbarui!';
                // Perbarui kategori terpilih untuk ditampilkan setelah update
                $kategori_terpilih_id = $kategori_id_form;
                
                // Redirect ke index setelah 2 detik
                header("refresh:2;url=index.php");
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan saat memperbarui artikel: ' . $e->getMessage();
            // error_log("Error updating article: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Artikel - Kota Madura</title>
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
                <span class="text-yellow-200 font-medium border-b-2 border-yellow-200">Edit Artikel</span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-yellow-800 mb-2">Edit Artikel</h2>
                <p class="text-yellow-600">Perbarui konten artikel Anda</p>
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

            <?php if ($artikel): ?>
                <form method="POST" action="" enctype="multipart/form-data"> <!-- Tambahkan enctype -->
                    <div class="mb-6">
                        <label for="judul" class="block text-yellow-800 text-sm font-bold mb-2">Judul Artikel</label>
                        <input type="text" id="judul" name="judul" required
                               class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                               placeholder="Masukkan judul artikel yang menarik..."
                               value="<?php echo htmlspecialchars($artikel['judul']); ?>">
                    </div>

                    <div class="mb-6">
                        <label for="isi" class="block text-yellow-800 text-sm font-bold mb-2">Isi Artikel</label>
                        <textarea id="isi" name="isi" rows="12" required
                                  class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent resize-vertical"
                                  placeholder="Tulis konten artikel di sini..."><?php echo htmlspecialchars($artikel['isi']); ?></textarea>
                    </div>

                    <div class="mb-6">
                        <label for="gambar" class="block text-yellow-800 text-sm font-bold mb-2">Gambar Artikel (Opsional)</label>
                        <?php if (!empty($artikel['gambar'])): ?>
                            <div class="mb-3">
                                <p class="text-gray-600 text-sm">Gambar saat ini:</p>
                                <img src="assets/image/<?php echo htmlspecialchars($artikel['gambar']); ?>" alt="Gambar Artikel" class="max-w-xs h-auto rounded-md shadow-sm">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="gambar" name="gambar" 
                               class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                        <p class="text-gray-500 text-sm mt-1">Biarkan kosong jika tidak ingin mengubah gambar. Ukuran maksimal 5MB. Format: JPG, JPEG, PNG, GIF.</p>
                    </div>

                    <div class="mb-6">
                        <label for="kategori_id" class="block text-yellow-800 text-sm font-bold mb-2">Pilih Kategori</label>
                        <select id="kategori_id" name="kategori_id" required
                                class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                            <option value="">-- Pilih Kategori --</option>
                            <?php 
                            // Pastikan kategori yang terakhir dipilih jika ada POST, agar form tidak kosong setelah submit error
                            $current_selected_category_id = isset($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : $kategori_terpilih_id;
                            ?>
                            <?php foreach ($all_categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"
                                    <?php echo ($category['id'] == $current_selected_category_id) ? 'selected' : ''; ?>>
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
                            Perbarui Artikel
                        </button>
                        <a href="index.php" 
                           class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-md transition duration-300 inline-block">
                            Batal
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-red-700">Gagal memuat artikel untuk diedit. Pastikan ID artikel valid dan Anda memiliki izin untuk mengeditnya.</p>
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
</body>
</html>