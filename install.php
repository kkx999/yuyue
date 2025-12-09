<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. 如果已安装，拦截
if (file_exists('config.php') && filesize('config.php') > 50) {
    die("系统已安装。如需重装，请删除 config.php 文件。<a href='index.php'>返回首页</a>");
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $host = trim($_POST['host']);
    $db_name = trim($_POST['db_name']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    $admin_user = trim($_POST['admin_user']);
    $admin_pass = trim($_POST['admin_pass']);

    try {
        // =======================================================
        // 核心修改：直接连接指定数据库，而不是连接服务器再去建库
        // 这样可以避免权限不足的问题
        // =======================================================
        $dsn = "mysql:host=$host;dbname=$db_name;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        
        // 2. 只有连接成功了，才开始建表
        
        // 建表：预约表
        $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            book_time DATETIME NOT NULL,
            message VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 建表：管理员表
        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            password VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 建表：限额表
        $pdo->exec("CREATE TABLE IF NOT EXISTS daily_limits (
            date DATE PRIMARY KEY,
            max_num INT NOT NULL DEFAULT 20
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 建表：设置表
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            name VARCHAR(50) PRIMARY KEY,
            value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 3. 写入管理员账号 (先清空防止重复)
        $pdo->exec("TRUNCATE TABLE admins");
        $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
        $stmt->execute([$admin_user, password_hash($admin_pass, PASSWORD_DEFAULT)]);

        // 4. 初始化设置
        $pdo->exec("INSERT IGNORE INTO settings (name, value) VALUES ('notice_status', '0')");
        $pdo->exec("INSERT IGNORE INTO settings (name, value) VALUES ('notice_content', '欢迎使用在线预约系统')");

        // 5. 生成配置文件
        $config_content = "<?php
\$host = '$host';
\$db_name = '$db_name';
\$db_user = '$db_user';
\$db_pass = '$db_pass';

try {
    \$conn = new PDO(\"mysql:host=\$host;dbname=\$db_name;charset=utf8mb4\", \$db_user, \$db_pass);
    \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException \$e) {
    die('数据库连接中断');
}
?>";
        
        if (file_put_contents('config.php', $config_content)) {
            $msg = "<div style='color:green; padding:20px; border:1px solid green;'>
                    <h2>✅ 安装成功！</h2>
                    <p>管理员账号：$admin_user</p>
                    <p>管理员密码：$admin_pass</p>
                    <p><a href='index.php'>进入前台</a> | <a href='admin/'>进入后台</a></p>
                    </div>";
        } else {
            $msg = "<div style='color:red'>数据库连接成功，但无法写入 config.php 文件，请检查目录权限 (chmod 777)。</div>";
        }

    } catch (PDOException $e) {
        $error = $e->getMessage();
        $msg = "<div style='color:red; padding:15px; border:1px solid red; background:#fff0f0;'>";
        $msg .= "<h3>❌ 安装失败</h3>";
        
        if (strpos($error, 'Access denied') !== false) {
            $msg .= "<p>原因：<strong>账号或密码错误</strong>。请检查用户名和密码是否填反了。</p>";
        } elseif (strpos($error, 'Unknown database') !== false) {
            $msg .= "<p>原因：<strong>数据库 '$db_name' 不存在</strong>。</p>";
            $msg .= "<p>解决办法：请先去宝塔面板/phpMyAdmin创建一个名为 <strong>$db_name</strong> 的空数据库。</p>";
        } else {
            $msg .= "<p>具体错误信息：" . $error . "</p>";
        }
        $msg .= "</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>系统安装</title><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="background:#f4f6f8; font-family:sans-serif; display:flex; justify-content:center; padding-top:50px;">
    <div style="background:white; padding:30px; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); width:100%; max-width:400px;">
        <h2 style="text-align:center; margin-top:0;">安装向导</h2>
        
        <?php if($msg): ?>
            <?= $msg ?>
            <?php if(strpos($msg, '成功') === false): ?>
                <p><a href="install.php">返回重试</a></p>
            <?php endif; ?>
        <?php else: ?>
        
        <form method="post">
            <h4 style="margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:5px;">1. 数据库配置</h4>
            <div style="margin-bottom:10px;">
                <label style="font-size:12px; font-weight:bold;">数据库地址</label>
                <input type="text" name="host" value="localhost" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:10px;">
                <label style="font-size:12px; font-weight:bold;">数据库名 (必须已存在)</label>
                <input type="text" name="db_name" placeholder="例如: yuyue_db" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
                <div style="font-size:11px; color:red;">* 请先在宝塔面板创建此空数据库</div>
            </div>
            <div style="margin-bottom:10px;">
                <label style="font-size:12px; font-weight:bold;">数据库用户名</label>
                <input type="text" name="db_user" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:bold;">数据库密码</label>
                <input type="text" name="db_pass" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
            </div>

            <h4 style="margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:5px;">2. 设置后台管理员</h4>
            <div style="margin-bottom:10px;">
                <input type="text" name="admin_user" value="admin" placeholder="账号" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:20px;">
                <input type="text" name="admin_pass" value="123456" placeholder="密码" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
            </div>

            <button type="submit" style="width:100%; padding:12px; background:#4f46e5; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">立即安装</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
