<?php
require_once 'koneksi.php';

// Redirect jika sudah login
requireLogout();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_identifier = isset($_POST['login_identifier']) ? trim($_POST['login_identifier']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($login_identifier) || empty($password)) {
        $error = 'Username/Email dan password harus diisi!';
    } else {
        try {
            // Cari pengguna berdasarkan Username atau email
            $stmt = $pdo->prepare("SELECT * FROM penulis WHERE email = ? OR nama = ?");
            $stmt->execute([$login_identifier, $login_identifier]);
            $user = $stmt->fetch();

            if ($user && !empty($user['sandi']) && password_verify($password, $user['sandi'])) {
                // Perbarui hash password jika diperlukan (menggunakan algoritma terbaru)
                if (password_needs_rehash($user['sandi'], PASSWORD_DEFAULT)) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE penulis SET sandi = ? WHERE id = ?");
                    $update_stmt->execute([$new_hash, $user['id']]);
                }

                $_SESSION['penulis_id'] = $user['id'];
                $_SESSION['penulis_nama'] = $user['nama'];
                $_SESSION['penulis_email'] = $user['email'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'Username/Email atau password salah!';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kota Madura</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body class="bg-gradient-to-br from-yellow-100 to-yellow-200 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-yellow-800 mb-2">Kota Madura</h1>
            <p class="text-yellow-600">Masuk ke akun Anda</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label for="login_identifier" class="block text-yellow-800 text-sm font-bold mb-2">Username atau Email</label>
                <input type="text" id="login_identifier" name="login_identifier" required
                    class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                    value="<?php echo isset($_POST['login_identifier']) ? htmlspecialchars($_POST['login_identifier']) : ''; ?>">
            </div>

            <div class="mb-6">
                <label for="password" class="block text-yellow-800 text-sm font-bold mb-2">Password</label>
                <div class="relative">
                    <input type="password" id="password" name="password" required
                        class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent pr-10">
                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-600 hover:text-yellow-800 focus:outline-none">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                Masuk
            </button>
        </form>

        <div class="text-center mt-6">
            <p class="text-yellow-700">Belum punya akun?
                <a href="register.php" class="text-yellow-800 font-bold hover:underline">Daftar di sini</a>
            </p>
        </div>
    </div>
</body>
<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    const toggleIcon = togglePassword.querySelector('i');

    togglePassword.addEventListener('click', function(e) {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);

        if (type === 'password') {
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    });
</script>

</html>