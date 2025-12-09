<?php
session_start();
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $u = $_POST['user'];
    $p = $_POST['pass'];
    
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$u]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($p, $admin['password'])) {
        $_SESSION['admin_logged'] = true;
        header("Location: index.php");
        exit;
    } else {
        $err = "账号或密码错误";
    }
}
?>
<!DOCTYPE html>
<html>
<body style="text-align:center; padding-top:100px; font-family:sans-serif;">
    <h2>后台登录</h2>
    <?php if(isset($err)) echo "<p style='color:red'>$err</p>"; ?>
    <form method="post" style="max-width:300px; margin:0 auto;">
        <input type="text" name="user" placeholder="用户名" required style="width:100%; padding:10px; margin-bottom:10px;"><br>
        <input type="password" name="pass" placeholder="密码" required style="width:100%; padding:10px; margin-bottom:10px;"><br>
        <button type="submit" style="width:100%; padding:10px;">登录</button>
    </form>
</body>
</html>
