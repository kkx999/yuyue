<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. 如果已安装，拦截
if (file_exists('config.php') && filesize('config.php') > 50) {
    die("<!DOCTYPE html><html><body style='background:#f3f4f6; display:flex; justify-content:center; align-items:center; height:100vh; font-family:system-ui;'><div style='background:white; padding:30px; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.1); text-align:center;'><h2>⚠️ 系统已安装</h2><p>如需重装，请手动删除根目录下的 <b>config.php</b> 文件。</p><a href='index.php' style='display:inline-block; margin-top:10px; text-decoration:none; color:#4f46e5; font-weight:bold;'>返回首页 &rarr;</a></div></body></html>");
}

$msg = '';
$step = 1; // 用于控制显示的步骤（虽然是单页，但逻辑上区分）

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $host = trim($_POST['host']);
    $db_name = trim($_POST['db_name']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    $admin_user = trim($_POST['admin_user']);
    $admin_pass = trim($_POST['admin_pass']);

    try {
        // 直接连接指定数据库
        $dsn = "mysql:host=$host;dbname=$db_name;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        
        // 建表逻辑
        $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL, phone VARCHAR(50) NOT NULL, book_time DATETIME NOT NULL, message VARCHAR(255) DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL, password VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS daily_limits (date DATE PRIMARY KEY, max_num INT NOT NULL DEFAULT 20) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (name VARCHAR(50) PRIMARY KEY, value TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 写入管理员
        $pdo->exec("TRUNCATE TABLE admins");
        $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
        $stmt->execute([$admin_user, password_hash($admin_pass, PASSWORD_DEFAULT)]);

        // 初始化设置
        $pdo->exec("INSERT IGNORE INTO settings (name, value) VALUES ('notice_status', '0')");
        $pdo->exec("INSERT IGNORE INTO settings (name, value) VALUES ('notice_content', '欢迎使用在线预约系统')");

        // 生成配置
        $config_content = "<?php\n\$host = '$host';\n\$db_name = '$db_name';\n\$db_user = '$db_user';\n\$db_pass = '$db_pass';\n\ntry {\n    \$conn = new PDO(\"mysql:host=\$host;dbname=\$db_name;charset=utf8mb4\", \$db_user, \$db_pass);\n    \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n} catch(PDOException \$e) {\n    die('数据库连接中断');\n}\n?>";
        
        if (file_put_contents('config.php', $config_content)) {
            // 安装成功 UI
            $msg = "<div class='success-box'>
                        <div class='icon-circle'>✅</div>
                        <h2>安装成功！</h2>
                        <div class='info-grid'>
                            <div class='info-item'><span>管理员账号</span><strong>$admin_user</strong></div>
                            <div class='info-item'><span>管理员密码</span><strong>$admin_pass</strong></div>
                        </div>
                        
                        <div class='danger-alert'>
                            <div class='alert-icon'>⚠️</div>
                            <div class='alert-content'>
                                <strong>严重安全警告</strong>
                                <p>请务必立即进入服务器文件管理器，<strong>删除 install.php 文件</strong>，否则站点可能被他人重置！</p>
                            </div>
                        </div>

                        <div class='action-buttons'>
                            <a href='index.php' class='btn btn-primary'>进入前台</a>
                            <a href='admin/' class='btn btn-dark'>进入后台</a>
                        </div>
                    </div>";
            $step = 2; // 切换到成功状态
        } else {
            $msg = "<div class='error-box'>❌ 数据库连接成功，但无法写入 config.php，请检查目录权限 (需 777)。</div>";
        }

    } catch (PDOException $e) {
        $err = $e->getMessage();
        $reason = "请检查配置";
        if (strpos($err, 'Access denied') !== false) $reason = "账号或密码错误";
        if (strpos($err, 'Unknown database') !== false) $reason = "数据库 '$db_name' 不存在 (请先在宝塔创建)";
        
        $msg = "<div class='error-box'>
                    <strong>❌ 安装失败：$reason</strong>
                    <p style='font-size:12px; margin-top:5px; opacity:0.8'>$err</p>
                </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>系统安装向导</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-gradient: linear-gradient(135deg, #e0e7ff 0%, #f3f4f6 100%);
            --card-bg: #ffffff;
            --text: #1f2937;
            --border: #e5e7eb;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-gradient);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: var(--card-bg);
            width: 100%;
            max-width: 480px;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header { text-align: center; margin-bottom: 30px; }
        .logo-icon { font-size: 48px; color: var(--primary); margin-bottom: 10px; display: block; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
        .header p { color: #6b7280; font-size: 14px; margin-top: 5px; }

        .section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .form-group { margin-bottom: 15px; }
        .input-wrapper { position: relative; }
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 20px;
            pointer-events: none;
        }

        input {
            width: 100%;
            padding: 12px 12px 12px 40px; /* Space for icon */
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            box-sizing: border-box;
            transition: 0.2s;
            background: #f9fafb;
        }
        input:focus {
            border-color: var(--primary);
            background: #fff;
            outline: none;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        button {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: 0.2s;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }
        button:hover { background: var(--primary-hover); transform: translateY(-1px); }

        /* 成功/错误状态样式 */
        .error-box {
            background: #fee2e2; border: 1px solid #fecaca; color: #991b1b;
            padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px;
        }
        
        .success-box { text-align: center; }
        .icon-circle {
            width: 60px; height: 60px; background: #dcfce7; color: #166534;
            border-radius: 50%; font-size: 30px; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 15px auto;
        }
        .info-grid {
            background: #f3f4f6; border-radius: 10px; padding: 15px; margin: 20px 0;
            text-align: left;
        }
        .info-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #d1d5db; }
        .info-item:last-child { border-bottom: none; }
        .info-item span { color: #6b7280; font-size: 13px; }
        .info-item strong { color: #111827; font-size: 14px; }

        .danger-alert {
            background: #fff7ed; border: 2px solid #fdba74; color: #9a3412;
            padding: 15px; border-radius: 10px; margin-bottom: 20px;
            display: flex; gap: 12px; align-items: start; text-align: left;
        }
        .alert-icon { font-size: 24px; }
        .alert-content p { margin: 4px 0 0 0; font-size: 13px; line-height: 1.4; }

        .action-buttons { display: flex; gap: 10px; }
        .btn { text-decoration: none; padding: 12px; border-radius: 8px; flex: 1; text-align: center; font-size: 14px; font-weight: 600; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-dark { background: #1f2937; color: white; }
        
        .hint { font-size: 11px; color: #ef4444; margin-top: 4px; display: block; }
    </style>
</head>
<body>

<div class="container">
    <?php if ($step == 1): ?>
    <div class="header">
        <span class="material-symbols-outlined logo-icon">rocket_launch</span>
        <h1>系统安装向导</h1>
        <p>只需几步，快速搭建您的预约系统</p>
    </div>
    
    <?= $msg ?>

    <form method="post">
        <div class="section-title">
            <span class="material-symbols-outlined" style="font-size:16px">database</span>
            MySQL 数据库配置
        </div>
        
        <div class="form-group">
            <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">dns</span>
                <input type="text" name="host" value="localhost" placeholder="数据库地址 (Host)" required>
            </div>
        </div>

        <div class="form-group">
            <div class="input-wrapper">
                <span class="material-symbols-outlined input-icon">folder_open</span>
                <input type="text" name="db_name" placeholder="数据库名 (DB Name)" required>
            </div>
            <span class="hint">* 请务必先在宝塔面板创建此空数据库</span>
        </div>

        <div style="display:flex; gap:10px;">
            <div class="form-group" style="flex:1">
                <div class="input-wrapper">
                    <span class="material-symbols-outlined input-icon">person</span>
                    <input type="text" name="db_user" placeholder="数据库用户" required>
                </div>
            </div>
            <div class="form-group" style="flex:1">
                <div class="input-wrapper">
                    <span class="material-symbols-outlined input-icon">key</span>
                    <input type="text" name="db_pass" placeholder="数据库密码" required>
                </div>
            </div>
        </div>

        <div class="section-title" style="margin-top:20px;">
            <span class="material-symbols-outlined" style="font-size:16px">admin_panel_settings</span>
            后台管理员设置
        </div>

        <div style="display:flex; gap:10px;">
            <div class="form-group" style="flex:1">
                <div class="input-wrapper">
                    <span class="material-symbols-outlined input-icon">account_circle</span>
                    <input type="text" name="admin_user" value="admin" placeholder="后台账号" required>
                </div>
            </div>
            <div class="form-group" style="flex:1">
                <div class="input-wrapper">
                    <span class="material-symbols-outlined input-icon">lock</span>
                    <input type="text" name="admin_pass" value="123456" placeholder="后台密码" required>
                </div>
            </div>
        </div>

        <button type="submit">立即安装系统</button>
    </form>
    
    <?php else: ?>
        <?= $msg ?>
    <?php endif; ?>
</div>

</body>
</html>
