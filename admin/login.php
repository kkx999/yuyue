<?php
session_start();
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $u = $_POST['user'];
    $p = $_POST['pass'];
    
    // 查询数据库
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$u]);
    $admin = $stmt->fetch();

    // 验证密码
    if ($admin && password_verify($p, $admin['password'])) {
        // =========== 核心修改在这里 ===========
        // 原来是 admin_logged，必须改为 is_admin 以匹配 index.php 的检查逻辑
        $_SESSION['is_admin'] = true; 
        // ====================================
        
        header("Location: index.php");
        exit;
    } else {
        $err = "账号或密码错误";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>后台登录</title>
</head>
<body style="text-align:center; padding-top:100px; font-family:sans-serif; background:#f3f4f6;">
    <div style="background:white; max-width:320px; margin:0 auto; padding:30px; border-radius:10px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0; color:#333;">管理员登录</h2>
        
        <?php if(isset($err)) echo "<p style='color:red; background:#fee2e2; padding:8px; border-radius:4px; font-size:14px;'>$err</p>"; ?>
        
        <form method="post">
            <div style="margin-bottom:15px; text-align:left;">
                <label style="font-size:12px; font-weight:bold; color:#555;">用户名</label>
                <input type="text" name="user" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px; box-sizing:border-box; margin-top:5px;">
            </div>
            
            <div style="margin-bottom:20px; text-align:left;">
                <label style="font-size:12px; font-weight:bold; color:#555;">密码</label>
                <input type="password" name="pass" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px; box-sizing:border-box; margin-top:5px;">
            </div>
            
            <button type="submit" style="width:100%; padding:12px; background:#4f46e5; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold; font-size:16px;">立即登录</button>
        </form>
        
        <div style="margin-top:20px; font-size:12px; color:#999;">
            <a href="../index.php" style="color:#666; text-decoration:none;">← 返回前台首页</a>
        </div>
    </div>
</body>
</html>
