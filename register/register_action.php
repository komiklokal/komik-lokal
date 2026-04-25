<?php
include "config.php";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $pass2 = $_POST['password2'];

    $checkEmailQuery = "SELECT user_email FROM user WHERE user_email = ?";
    $emailStmt = mysqli_prepare($conn, $checkEmailQuery);
    mysqli_stmt_bind_param($emailStmt, "s", $email);
    mysqli_stmt_execute($emailStmt);
    $resultEmail = mysqli_stmt_get_result($emailStmt);
    if (mysqli_num_rows($resultEmail) > 0) {
      header('Location: register.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode('Email sudah digunakan. Gunakan email lain.'));
      exit;
    }
    $checkUsernameQuery = "SELECT user_nama FROM user WHERE user_nama = ?";
    $usernameStmt = mysqli_prepare($conn, $checkUsernameQuery);
    mysqli_stmt_bind_param($usernameStmt, "s", $username);
    mysqli_stmt_execute($usernameStmt);
    $resultUsername = mysqli_stmt_get_result($usernameStmt);
    if (mysqli_num_rows($resultUsername) > 0) {
      header('Location: register.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode('Username sudah digunakan. Gunakan username lain.'));
      exit;
    }
    if ($password !== $pass2) {
      header('Location: register.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode('Password dan Konfirmasi Password tidak sama.'));
      exit;
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $sql = "INSERT INTO user (user_nama, user_email, user_password)
          VALUES (?, ?, ?)";
      $stmt = mysqli_prepare($conn, $sql);
      mysqli_stmt_bind_param($stmt, "sss", $username, $email, $hashedPassword);
      if (mysqli_stmt_execute($stmt)) {
        header('Location: register.php?status=success&title=' . urlencode('Berhasil') . '&message=' . urlencode('Registrasi berhasil. Silakan login.') . '&redirect=' . urlencode('../login/login.php'));
        exit;
    } else {
        header('Location: register.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode('Terjadi kesalahan. Coba lagi.'));
        exit;
    }
}
header('Location: register.php');
exit;
?>
