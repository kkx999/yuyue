<?php
// ==================================================
// 1. 初始化与配置检测
// ==================================================
session_start(); // 开启 Session 用于存储提示消息
date_default_timezone_set('Asia/Shanghai');

// 检查配置文件是否存在
if (!file_exists('config.php') || filesize('config.php') < 10) { 
    header("Location: install.php"); 
    exit; 
}

// 引入数据库配置
require_once 'config.php';

// 检查数据库连接
if (!isset($conn)) { 
    die("Error: Database not connected. Please check config.php"); 
}

// ==================================================
// 2. 读取系统配置 (公告、TG设置)
// ==================================================
$settings = [];
try {
    $stmt = $conn->query("SELECT * FROM settings WHERE name IN ('notice_status', 'notice_content', 'tg_bot_token', 'tg_chat_id')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['name']] = $row['value'];
    }
} catch (Exception $e) {
    // 容错：防止表不存在报错
}

// ==================================================
// 3. 处理表单提交 (PRG 模式 - 修复重复提交 Bug)
// ==================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = strip_tags(trim($_POST['name']));
    $contact = strip_tags(trim($_POST['contact']));
    $date = $_POST['date'];
    $message = strip_tags(trim($_POST['message']));
    
    // 简单的后端校验
    if (empty($name) || empty($contact) || empty($date)) {
        $_SESSION['flash_msg'] = ['type' => 'error', 'content' => '❌ 请填写完整信息'];
    } else {
        try {
            // A. 检查每日限额
            $limit = 20; // 默认限额
            $stmt = $conn->prepare("SELECT max_num FROM daily_limits WHERE date = ?");
            $stmt->execute([$date]);
            if ($row = $stmt->fetch()) $limit = $row['max_num'];
            
            // B. 检查当天已预约数量
            $cnt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(book_time) = ?");
            $cnt->execute([$date]);
            
            if ($cnt->fetchColumn() >= $limit) {
                $_SESSION['flash_msg'] = ['type' => 'error', 'content' => "⚠️ 该日期 ({$date}) 名额已满，请更换其他日期。"];
            } else {
                // C. 写入数据库
                $conn->prepare("INSERT INTO appointments (name, phone, book_time, message) VALUES (?, ?, ?, ?)")
                     ->execute([$name, $contact, $date . " 09:00:00", $message]);

                // D. 发送 Telegram 通知 (带超时防止卡顿)
                $tg_token = $settings['tg_bot_token'] ?? '';
                $tg_chat = $settings['tg_chat_id'] ?? '';

                if (!empty($tg_token) && !empty($tg_chat)) {
                    $txt = "🔔 *新预约提醒*\n\n" .
                           "👤 *用户*: " . $name . "\n" .
                           "📱 *联系*: `" . $contact . "`\n" .
                           "📅 *日期*: " . $date . "\n" .
                           "📝 *备注*: " . ($message ?: '无');

                    $url = "https://api.telegram.org/bot{$tg_token}/sendMessage?chat_id={$tg_chat}&parse_mode=Markdown&text=" . urlencode($txt);
                    $ctx = stream_context_create(['http' => ['timeout' => 2]]); // 设置2秒超时
                    @file_get_contents($url, false, $ctx);
                }
                
                // E. 设置成功消息
                $_SESSION['flash_msg'] = ['type' => 'success', 'content' => "✅ 预约提交成功！请等待管理员联系。"];
            }
        } catch (Exception $e) {
            $_SESSION['flash_msg'] = ['type' => 'error', 'content' => "提交失败，请稍后再试。"];
        }
    }

    // [关键步骤] 跳转回当前页面，清除 POST 状态，解决刷新重复提交问题
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ==================================================
// 4. 读取并清除 Session 消息 (显示弹窗)
// ==================================================
$msg_html = '';
if (isset($_SESSION['flash_msg'])) {
    $m = $_SESSION['flash_msg'];
    $icon = $m['type'] == 'success' ? 'check_circle' : 'error';
    $msg_html = "<div class='alert {$m['type']}'>
                    <span class='material-symbols-outlined' style='font-size:20px'>{$icon}</span>
                    {$m['content']}
                 </div>";
    // 显示完后立即销毁，防止刷新页面再次显示
    unset($_SESSION['flash_msg']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>在线预约</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        /* === 核心配色变量 (保持原有 UI 不变) === */
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg: #f3f4f6;
            --card: #ffffff;
            --text-main: #111827;
            --text-sub: #4b5563;
            --border: #d1d5db;
            --input-bg: #f9fafb;
            --notice-bg: #fff7ed;
            --notice-border: #ffedd5;
            --notice-text: #c2410c;
            --shadow: rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] {
            --primary: #6366f1; 
            --primary-hover: #818cf8;
            --bg: #111827;      
            --card: #1f2937;    
            --text-main: #f9fafb; 
            --text-sub: #9ca3af;  
            --border: #374151;    
            --input-bg: #111827;  
            --notice-bg: #431407; 
            --notice-border: #78350f;
            --notice-text: #fdba74; 
            --shadow: rgba(0, 0, 0, 0.5);
        }

        body, .container, input, textarea, .notice-box, button, .footer {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .container {
            background: var(--card);
            width: 100%;
            max-width: 440px;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px var(--shadow), 0 8px 10px -6px var(--shadow);
            border: 1px solid var(--border);
        }
        
        .theme-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text-main);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 6px var(--shadow);
            z-index: 100;
        }
        .theme-toggle:hover { background: var(--input-bg); }
        .theme-toggle span { font-size: 24px; }

        .header { text-align: center; margin-bottom: 30px; }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 26px;
            color: var(--text-main);
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .header p {
            margin: 0;
            color: var(--text-sub);
            font-size: 15px;
            font-weight: 500;
        }
        
        .notice-box {
            background: var(--notice-bg);
            border: 2px solid var(--notice-border);
            color: var(--notice-text);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            gap: 10px;
            line-height: 1.5;
            align-items: start;
        }
        .notice-icon { font-weight: normal; font-size: 20px; margin-top: 1px; flex-shrink: 0; }

        label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            margin-top: 20px;
            margin-bottom: 8px;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--input-bg);
            box-sizing: border-box;
            font-size: 16px;
            font-family: inherit;
            color: var(--text-main);
            font-weight: 500;
        }
        
        ::-webkit-calendar-picker-indicator {
            filter: invert(var(--dark-mode-invert, 0));
        }
        [data-theme="dark"] { --dark-mode-invert: 1; }

        input:focus, textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2);
        }
        
        input::placeholder, textarea::placeholder {
            color: var(--text-sub);
            font-weight: 400;
            opacity: 0.7;
        }
        
        button.submit-btn {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 30px;
            letter-spacing: 0.5px;
        }
        
        button.submit-btn:hover { background: var(--primary-hover); }
        button.submit-btn:active { transform: scale(0.98); }
        button.submit-btn:disabled { opacity: 0.7; cursor: not-allowed; } /* 禁用样式 */
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 25px;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            animation: fadeIn 0.5s ease; /* 增加淡入动画 */
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; } 
        [data-theme="dark"] .alert.success { background: #064e3b; color: #a7f3d0; border-color: #065f46; }

        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        [data-theme="dark"] .alert.error { background: #7f1d1d; color: #fecaca; border-color: #991b1b; }
        
        .word-count { text-align: right; font-size: 13px; font-weight: 500; color: var(--text-sub); margin-top: 6px; }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-sub);
            border-top: 2px dashed var(--border);
            padding-top: 20px;
        }
        .footer a { color: inherit; text-decoration: none; font-weight: 600; }
        .footer a:hover { color: var(--primary); }
    </style>
</head>
<body>

    <button class="theme-toggle" id="themeBtn" title="切换深色模式">
        <span class="material-symbols-outlined" id="themeIcon">dark_mode</span>
    </button>

    <div class="container">
        <div class="header">
            <h1>预约登记服务</h1>
            <p>请填写下方信息，名额有限，先到先得</p>
        </div>

        <?php if (!empty($settings['notice_status']) && $settings['notice_status'] == '1'): ?>
        <div class="notice-box">
            <span class="material-symbols-outlined notice-icon">campaign</span>
            <span><?= nl2br(htmlspecialchars($settings['notice_content'])) ?></span>
        </div>
        <?php endif; ?>

        <?= $msg_html ?>

        <form method="post" id="appointForm">
            <label>您的微信名 / 电报名</label>
            <input type="text" name="name" required placeholder="请输入您的昵称" autocomplete="off">
            
            <label>微信号 / 电报号</label>
            <input type="text" name="contact" required placeholder="请输入您的账号ID" autocomplete="off">

            <label>预约日期</label>
            <input type="date" name="date" required id="datePicker">
            
            <label>留言备注 (选填)</label>
            <textarea name="message" id="msgInput" rows="3" maxlength="100" placeholder="如有特殊需求请告知..."></textarea>
            <div class="word-count"><span id="charCount">0</span>/100</div>
            
            <button type="submit" class="submit-btn" id="submitBtn">立即提交预约</button>
        </form>
        
        <div class="footer">
            &copy; <?= date('Y') ?> 在线预约系统
        </div>
    </div>

<script>
    // 1. 设置默认日期为今天
    const dateInput = document.getElementById('datePicker');
    if (!dateInput.value) {
        dateInput.valueAsDate = new Date();
    }
    
    // 2. 留言字数统计
    const msgInput = document.getElementById('msgInput');
    const charCount = document.getElementById('charCount');
    msgInput.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });

    // 3. [防重复提交] 点击提交后禁用按钮，防止手抖
    document.getElementById('appointForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerText = '提交中...';
    });

    // 4. 深色模式逻辑
    const themeBtn = document.getElementById('themeBtn');
    const themeIcon = document.getElementById('themeIcon');
    const htmlEl = document.documentElement;

    // 检查本地存储或系统偏好
    const savedTheme = localStorage.getItem('theme');
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    // 初始化主题
    if (savedTheme === 'dark' || (!savedTheme && systemDark)) {
        enableDark();
    }

    themeBtn.addEventListener('click', () => {
        if (htmlEl.getAttribute('data-theme') === 'dark') {
            enableLight();
        } else {
            enableDark();
        }
    });

    function enableDark() {
        htmlEl.setAttribute('data-theme', 'dark');
        themeIcon.textContent = 'light_mode'; // 切换图标为太阳
        localStorage.setItem('theme', 'dark');
    }

    function enableLight() {
        htmlEl.removeAttribute('data-theme');
        themeIcon.textContent = 'dark_mode'; // 切换图标为月亮
        localStorage.setItem('theme', 'light');
    }
</script>

</body>
</html>
