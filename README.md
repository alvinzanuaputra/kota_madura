
## Cara Instalasi dan Menjalankan Proyek

Ikuti langkah-langkah di bawah ini untuk mengatur dan menjalankan proyek di lingkungan lokal Anda.

### 1. Persyaratan

*   **Web Server**: Apache atau Nginx dengan PHP 7.4+ terinstal. (Misalnya, gunakan XAMPP, Laragon, atau MAMP untuk lingkungan pengembangan yang mudah).
*   **Database Server**: MySQL atau MariaDB.

### 2. Setup Database

1.  **Buat Database**: Buka aplikasi manajemen database Anda (misalnya phpMyAdmin, MySQL Workbench, atau HeidiSQL). Buat database baru dengan nama `kota_madura`.
2.  **Impor Skema**: Impor file `kota_madura.sql` yang terletak di direktori `assets/database/` ke database `kota_madura` yang baru Anda buat. File ini akan membuat tabel yang diperlukan (`artikel`, `penulis`, `kategori`, `artikel_penulis`, `artikel_kategori`) dan mengisi beberapa data awal.

### 3. Penempatan Proyek

1.  **Kloning/Unduh**: Unduh atau kloning seluruh repository proyek ini.
2.  **Tempatkan di Web Server**: Pindahkan semua file proyek ke direktori `htdocs` (untuk XAMPP), `www` (untuk Laragon), atau direktori root dokumen web server Anda yang sesuai. Misalnya, jika Anda menggunakan Laragon, letakkan di `C:\laragon\www\kota_madura`.

### 4. Konfigurasi Koneksi Database

1.  **Buka `koneksi.php`**: Navigasikan ke file `koneksi.php` di root proyek Anda.
2.  **Sesuaikan Kredensial**: Ubah nilai variabel `$host`, `$username`, `$password`, `$database`, dan `$port` agar sesuai dengan konfigurasi database MySQL/MariaDB lokal Anda.

    ```php
    // koneksi.php
    $host = 'localhost';
    $username = 'root'; // Ganti dengan username database Anda
    $password = '';     // Ganti dengan password database Anda
    $database = 'kota_madura'; // Pastikan ini sesuai dengan nama database yang Anda buat
    $port = 3306; // Sesuaikan jika port MySQL Anda berbeda
    ```

### 5. Akses Aplikasi

1.  **Buka Browser**: Buka browser web Anda.
2.  **Navigasi ke URL**: Akses proyek melalui URL berikut (sesuaikan dengan nama folder proyek Anda):
    `http://localhost/nama_folder_proyek_anda/`
    Misalnya, jika Anda menempatkan proyek di `C:\laragon\www\kota_madura`, URL-nya adalah `http://localhost/kota_madura/`.

## Cara Menggunakan Aplikasi

1.  **Daftar Akun Baru**:
    *   Jika Anda belum memiliki akun, klik tautan "Daftar di sini" di halaman login atau langsung navigasikan ke `register.php`.
    *   Isi formulir pendaftaran dan buat akun penulis baru.

2.  **Login**:
    *   Setelah mendaftar, kembali ke halaman login (`login.php`).
    *   Masukkan username/email dan password Anda untuk masuk.

3.  **Dashboard Artikel**:
    *   Setelah login, Anda akan diarahkan ke `index.php`, yang merupakan dashboard Anda. Di sini, Anda akan melihat semua artikel yang Anda publikasikan.

4.  **Tambah Artikel Baru**:
    *   Klik tombol "+ Tambah Artikel Baru" atau navigasikan ke `tambah_artikel.php`.
    *   Isi judul, konten, pilih kategori, dan unggah gambar (opsional). Klik "Simpan Artikel".

5.  **Edit Artikel**:
    *   Di dashboard, temukan artikel yang ingin Anda edit.
    *   Klik tombol "Edit" pada kartu artikel tersebut. Anda akan diarahkan ke `edit_artikel.php` dengan data artikel yang sudah terisi.
    *   Lakukan perubahan dan klik "Perbarui Artikel".

6.  **Hapus Artikel**:
    *   Di dashboard, temukan artikel yang ingin Anda hapus.
    *   Klik tombol "Hapus" pada kartu artikel. Sebuah popup konfirmasi akan muncul.
    *   Konfirmasi penghapusan untuk menghapus artikel secara permanen.

7.  **Logout**:
    *   Untuk keluar dari sesi Anda, klik tombol "Logout" di header halaman.
?># kota_madura
