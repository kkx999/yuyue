<?php
// 核心逻辑：如果 config.php 不存在或内容太少，跳转安装
if (!file_exists('config.php') || filesize('config.php') < 10) {
    header("Location: install.php");
    exit;
}

// 尝试引入配置
require_once 'config.php';
if (!isset($conn)) {
    echo "数据库连接配置错误，<a href='install.php'>点此重新安装</a>";
    exit;
}

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = strip_tags($_POST['name']);
    $contact = strip_tags($_POST['contact']); // 这里存微信号/电报号
    
    // 因为去掉了前端的时间选择，这里默认使用"当前提交时间"作为记录存入数据库
    $current_time = date('Y-m-d H:i:s');

    try {
        // 注意：数据库字段我们还是复用原来的 phone 和 book_time，避免需要修改数据库结构
        // phone 字段现在存 微信号/电报号
        // book_time 字段现在存 提交时间
        $stmt = $conn->prepare("INSERT INTO appointments (name, phone, book_time) VALUES (?, ?, ?)");
        $stmt->execute([$name, $contact, $current_time]);
        $msg = "✅ 提交成功！我们会通过微信或电报联系您。";
        $msg_type = "success";
    } catch (Exception $e) {
        $msg = "❌ 提交失败，请检查填写信息后重试。";
        $msg_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>在线预约服务</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <style>
        :root {
            --primary-color: #4a90e2; 
            --primary-hover: #357abd;
            --bg-color: #f0f2f5;
            --card-bg: #ffffff;
            --text-main: #333333;
            --text-sub: #666666;
            --border-color: #e1e4e8;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background: var(--card-bg);
            width: 100%;
            max-width: 420px;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 24px; font-weight: 700; color: #1a1a1a; margin-bottom: 8px; }
        .header p { color: var(--text-sub); font-size: 14px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-sub); margin-bottom: 8px; }
        
        input {
            width: 100%;
            padding: 12px 15px;
            font-size: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #f9f9f9;
            transition: all 0.3s;
            color: #333;
        }

        input:focus {
            border-color: var(--primary-color);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        button {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }

        button:active { transform: scale(0.98); }
        button:hover { box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3); }

        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease;
        }
        .alert.success { background: #e6fffa; color: #2c7a7b; border: 1px solid #b2f5ea; }
        .alert.error { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; }

        .footer { text-align: center; margin-top: 25px; font-size: 12px; color: #aaa; }
        .footer a { color: #aaa; text-decoration: none; transition: color 0.3s; }
        .footer a:hover { color: var(--primary-color); }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* 移动端优化 */
        @media (max-width: 480px) {
            body { padding: 0; background: #fff; align-items: flex-start; }
            .container { box-shadow: none; border-radius: 0; padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <h1>欢迎使用</h1>
            <p>请留下您的联系方式，我们将尽快联系您</p>
        </div>

        <?php if($msg): ?>
            <div class="alert <?= $msg_type ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>您的微信名或者电报名</label>
                <input type="text" name="name" required placeholder="请输入您的昵称" autocomplete="off">
            </div>
            
            <div class="form-group">
                <label>微信号或者电报号</label>
                <input type="text" name="contact" required placeholder="请输入ID" autocomplete="off">
            </div>
            
            <button type="submit">立即提交</button>
        </form>

        <div class="footer">
            <p>© 2024 在线系统 | <a href="admin/">管理员登录</a></p>
        </div>
    </div>

</body>
</html>
