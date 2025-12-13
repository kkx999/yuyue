<?php
// ==================================================
// 1. 初始化与配置检测
// ==================================================
session_start();
date_default_timezone_set('Asia/Shanghai');

// CSRF Token 生成 (安全防护)
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

if (!file_exists('config.php') || filesize('config.php') < 10) { 
    header("Location: install.php"); 
    exit; 
}
require_once 'config.php';

if (!isset($conn)) { die("Error: Database not connected."); }

// ==================================================
// 2. 读取系统配置
// ==================================================
$settings = [];
try {
    $stmt = $conn->query("SELECT * FROM settings WHERE name IN ('notice_status', 'notice_content', 'tg_bot_token', 'tg_chat_id')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['name']] = $row['value'];
    }
} catch (Exception $e) {}

// ==================================================
// 3. 处理表单提交
// ==================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. 安全令牌校验
    if (!isset($_POST['token']) || !hash_equals($_SESSION['token'], $_POST['token'])) {
        die("无效的请求令牌，请刷新页面重试。");
    }

    $name = strip_tags(trim($_POST['name']));
    $contact = strip_tags(trim($_POST['contact']));
    $date = $_POST['date'];
    $message = strip_tags(trim($_POST['message']));
    
    // 2. 基础校验
    if (empty($name) || empty($contact) || empty($date)) {
        $_SESSION['flash_msg'] = ['type' => 'error', 'content' => '❌ 请填写完整信息'];
    } elseif ($date < date('Y-m-d')) {
        $_SESSION['flash_msg'] = ['type' => 'error', 'content' => '❌ 不能预约过去的日期'];
    } else {
        try {
            // 3. [核心修改] 原子化写入 (防并发超卖)
            // SQL逻辑：尝试插入数据，但前提是 (当天已约数 < (当天限额 OR 默认20))
            $sql = "INSERT INTO appointments (name, phone, book_time, message)
                    SELECT ?, ?, ?, ?
                    FROM DUAL
                    WHERE (SELECT COUNT(*) FROM appointments WHERE DATE(book_time) = ?) < 
                          (SELECT IFNULL((SELECT max_num FROM daily_limits WHERE date = ?), 20))";
            
            $stmt = $conn->prepare($sql);
            // 参数顺序: name, phone, full_time, message, date_check, limit_date_check
            $stmt->execute([$name, $contact, $date . " 09:00:00", $message, $date, $date]);

            if ($stmt->rowCount() > 0) {
                // --- 写入成功，发送 TG 通知 ---
                $tg_token = $settings['tg_bot_token'] ?? '';
                $tg_chat = $settings['tg_chat_id'] ?? '';

                if (!empty($tg_token) && !empty($tg_chat)) {
                    $txt = "🔔 *新预约提醒*\n\n👤 *用户*: $name\n📱 *联系*: `$contact`\n📅 *日期*: $date\n📝 *备注*: " . ($message ?: '无');
                    $url = "https://api.telegram.org/bot{$tg_token}/sendMessage?chat_id={$tg_chat}&parse_mode=Markdown&text=" . urlencode($txt);
                    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                    @file_get_contents($url, false, $ctx);
                }
                
                $_SESSION['flash_msg'] = ['type' => 'success', 'content' => "✅ 预约提交成功！请等待管理员联系。"];
            } else {
                // --- 写入失败（受影响行数为0），说明满了 ---
                $_SESSION['flash_msg'] = ['type' => 'error', 'content' => "⚠️ 手慢了，该日期 ({$date}) 名额刚被抢完！"];
            }
        } catch (Exception $e) {
            $_SESSION['flash_msg'] = ['type' => 'error', 'content' => "提交失败，请稍后再试。"];
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 读取消息
$msg_html = '';
if (isset($_SESSION['flash_msg'])) {
    $m = $_SESSION['flash_msg'];
    $icon = $m['type'] == 'success' ? 'check_circle' : 'error';
    $msg_html = "<div class='alert {$m['type']}'><span class='material-symbols-outlined' style='font-size:20px'>{$icon}</span>{$m['content']}</div>";
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
        /* 保持原有样式不变，此处省略以节省篇幅，请保留原文件 CSS */
        :root { --primary: #4f46e5; --primary-hover: #4338ca; --bg: #f3f4f6; --card: #ffffff; --text-main: #111827; --text-sub: #4b5563; --border: #d1d5db; --input-bg: #f9fafb; --notice-bg: #fff7ed; --notice-border: #ffedd5; --notice-text: #c2410c; --shadow: rgba(0, 0, 0, 0.1); }
        [data-theme="dark"] { --primary: #6366f1; --primary-hover: #818cf8; --bg: #111827; --card: #1f2937; --text-main: #f9fafb; --text-sub: #9ca3af; --border: #374151; --input-bg: #111827; --notice-bg: #431407; --notice-border: #78350f; --notice-text: #fdba74; --shadow: rgba(0, 0, 0, 0.5); }
        body, .container, input, textarea, .notice-box, button, .footer { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: var(--bg); color: var(--text-main); margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; }
        .container { background: var(--card); width: 100%; max-width: 440px; padding: 35px; border-radius: 20px; box-shadow: 0 10px 25px -5px var(--shadow), 0 8px 10px -6px var(--shadow); border: 1px solid var(--border); }
        .theme-toggle { position: absolute; top: 20px; right: 20px; background: var(--card); border: 1px solid var(--border); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 6px var(--shadow); z-index: 100; }
        .header { text-align: center; margin-bottom: 30px; } .header h1 { margin: 0 0 10px 0; font-size: 26px; font-weight: 800; } .header p { margin: 0; color: var(--text-sub); font-size: 15px; }
        .notice-box { background: var(--notice-bg); border: 2px solid var(--notice-border); color: var(--notice-text); padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 14px; font-weight: 600; display: flex; gap: 10px; align-items: start; }
        label { display: block; font-size: 14px; font-weight: 700; margin-top: 20px; margin-bottom: 8px; }
        input, textarea { width: 100%; padding: 14px; border: 2px solid var(--border); border-radius: 10px; background: var(--input-bg); box-sizing: border-box; font-size: 16px; color: var(--text-main); }
        input:focus, textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2); }
        button.submit-btn { width: 100%; padding: 16px; background: var(--primary); color: white; border: none; border-radius: 10px; font-size: 17px; font-weight: 700; cursor: pointer; margin-top: 30px; }
        button.submit-btn:disabled { opacity: 0.7; cursor: not-allowed; }
        .alert { padding: 15px; border-radius: 10px; text-align: center; margin-bottom: 25px; font-size: 15px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; animation: fadeIn 0.5s ease; }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; } [data-theme="dark"] .alert.success { background: #064e3b; color: #a7f3d0; border-color: #065f46; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; } [data-theme="dark"] .alert.error { background: #7f1d1d; color: #fecaca; border-color: #991b1b; }
        .word-count { text-align: right; font-size: 13px; color: var(--text-sub); margin-top: 6px; }
        .footer { text-align: center; margin-top: 30px; font-size: 13px; color: var(--text-sub); border-top: 2px dashed var(--border); padding-top: 20px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        ::-webkit-calendar-picker-indicator { filter: invert(var(--dark-mode-invert, 0)); } [data-theme="dark"] { --dark-mode-invert: 1; }
    </style>
</head>
<body>
    <button class="theme-toggle" id="themeBtn"><span class="material-symbols-outlined" id="themeIcon">dark_mode</span></button>
    <div class="container">
        <div class="header"><h1>预约登记服务</h1><p>请填写下方信息，名额有限，先到先得</p></div>
        <?php if (!empty($settings['notice_status']) && $settings['notice_status'] == '1'): ?>
        <div class="notice-box"><span class="material-symbols-outlined notice-icon">campaign</span><span><?= nl2br(htmlspecialchars($settings['notice_content'])) ?></span></div>
        <?php endif; ?>
        <?= $msg_html ?>
        <form method="post" id="appointForm">
            <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
            
            <label>您的微信名 / 电报名</label>
            <input type="text" name="name" required placeholder="请输入您的昵称" autocomplete="off">
            <label>微信号 / 电报号</label>
            <input type="text" name="contact" required placeholder="请输入您的账号ID" autocomplete="off">
            <label>预约日期</label>
            <input type="date" name="date" required id="datePicker" min="<?= date('Y-m-d') ?>">
            <label>留言备注 (选填)</label>
            <textarea name="message" id="msgInput" rows="3" maxlength="100" placeholder="如有特殊需求请告知..."></textarea>
            <div class="word-count"><span id="charCount">0</span>/100</div>
            <button type="submit" class="submit-btn" id="submitBtn">立即提交预约</button>
        </form>
        <div class="footer">&copy; <?= date('Y') ?> 在线预约系统</div>
    </div>
<script>
    const dateInput = document.getElementById('datePicker');
    if (!dateInput.value) dateInput.valueAsDate = new Date(); // 默认今天

    const msgInput = document.getElementById('msgInput');
    const charCount = document.getElementById('charCount');
    msgInput.addEventListener('input', function() { charCount.textContent = this.value.length; });

    document.getElementById('appointForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn'); btn.disabled = true; btn.innerText = '提交中...';
    });

    // 主题切换逻辑
    const themeBtn = document.getElementById('themeBtn');
    const htmlEl = document.documentElement;
    const savedTheme = localStorage.getItem('theme');
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (savedTheme === 'dark' || (!savedTheme && systemDark)) enableDark();

    themeBtn.addEventListener('click', () => {
        htmlEl.getAttribute('data-theme') === 'dark' ? enableLight() : enableDark();
    });
    function enableDark() { htmlEl.setAttribute('data-theme', 'dark'); document.getElementById('themeIcon').textContent = 'light_mode'; localStorage.setItem('theme', 'dark'); }
    function enableLight() { htmlEl.removeAttribute('data-theme'); document.getElementById('themeIcon').textContent = 'dark_mode'; localStorage.setItem('theme', 'light'); }
</script>
</body>
</html>
