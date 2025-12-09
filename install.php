<?php
error_reporting(0);
// 如果已配置且能连接，禁止访问安装页
if (file_exists('config.php')) {
    include 'config.php';
    if (isset($conn)) {
        die("系统已安装。如需重装，请删除 config.php 文件。<a href='index.php'>返回首页</a>");
    }
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $host = $_POST['host'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $db_name = $_POST['db_name'];
    $admin_user = $_POST['admin_user'];
    $admin_pass = $_POST['admin_pass'];

    try {
        // 1. 测试连接
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 2. 创建数据库
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");

        // 3. 建表：预约表
        $sql_book = "CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            book_time DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql_book);

        // 4. 建表：管理员表
        $sql_admin = "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            password VARCHAR(255) NOT NULL
        )";
        $pdo->exec($sql_admin);

        // 5. 写入管理员账号
        $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
        $stmt->execute([$admin_user, $hash]);

        // 6. 生成配置文件 config.php
        $config_content = "<?php
\$host = '$host';
\$db_name = '$db_name';
\$db_user = '$db_user';
\$db_pass = '$db_pass';

try {
    \$conn = new PDO(\"mysql:host=\$host;dbname=\$db_name;charset=utf8mb4\", \$db_user, \$db_pass);
    \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException \$e) {
    die('数据库连接失败，请检查 config.php 配置');
}
?>";
        if (file_put_contents('config.php', $config_content)) {
            $msg = "<h3 style='color:green'>安装成功！<a href='index.php'>点击进入首页</a></h3><p>为了安全，建议手动删除 install.php</p>";
        } else {
            $msg = "<h3 style='color:red'>安装失败：无法写入 config.php，请检查目录权限 (需 777)</h3>";
        }

    } catch (PDOException $e) {
        $msg = "<h3 style='color:red'>数据库错误：" . $e->getMessage() . "</h3>";
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>系统安装</title><meta charset="utf-8"></head>
<body style="font-family:sans-serif; padding:50px; max-width:400px; margin:0 auto;">
    <h2>网站快速安装</h2>
    <?= $msg ?>
    <form method="post">
        <fieldset>
            <legend>数据库配置</legend>
            <p><input type="text" name="host" placeholder="数据库地址 (localhost)" value="localhost" required style="width:100%"></p>
            <p><input type="text" name="db_name" placeholder="数据库名称" required style="width:100%"></p>
            <p><input type="text" name="db_user" placeholder="数据库用户名" required style="width:100%"></p>
            <p><input type="password" name="db_pass" placeholder="数据库密码" style="width:100%"></p>
        </fieldset>
        <fieldset>
            <legend>后台管理员设置</legend>
            <p><input type="text" name="admin_user" placeholder="管理员用户名" required style="width:100%"></p>
            <p><input type="password" name="admin_pass" placeholder="管理员密码" required style="width:100%"></p>
        </fieldset>
        <button type="submit" style="width:100%; padding:10px; cursor:pointer; background:#007bff; color:white; border:none;">开始安装</button>
    </form>
</body>
</html>
