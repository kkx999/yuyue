<?php
session_start();
// 1. 登录鉴权
if (!isset($_SESSION['is_admin'])) { header("Location: login.php"); exit; }
require '../config.php';

// ==================================================
// 2. 数据库自动维护 (建表/加字段)
// ==================================================
try {
    // 确保限额表存在
    $conn->exec("CREATE TABLE IF NOT EXISTS daily_limits (date DATE PRIMARY KEY, max_num INT NOT NULL DEFAULT 20)");
    // 确保留言字段存在
    $conn->query("SELECT message FROM appointments LIMIT 1");
} catch (Exception $e) {
    try { $conn->exec("ALTER TABLE appointments ADD COLUMN message VARCHAR(255) DEFAULT ''"); } catch(Exception $ex){}
}

$sys_msg = '';

// ==================================================
// 3. 核心逻辑处理 (编辑/删除/设置)
// ==================================================

// A. 处理【编辑】保存
if (isset($_POST['update_appointment'])) {
    $id = (int)$_POST['edit_id'];
    $name = strip_tags($_POST['edit_name']);
    $phone = strip_tags($_POST['edit_phone']); // 对应数据库 phone 字段
    $date = $_POST['edit_date'];
    $message = strip_tags($_POST['edit_message']);
    
    // 保持时间格式 (默认加上 09:00:00，或者你可以保留原有时分秒，这里简化处理)
    $book_time = $date . " 09:00:00";

    try {
        $stmt = $conn->prepare("UPDATE appointments SET name=?, phone=?, book_time=?, message=? WHERE id=?");
        $stmt->execute([$name, $phone, $book_time, $message, $id]);
        $sys_msg = "<div class='alert success'>✅ 预约 #{$id} 信息已更新</div>";
    } catch (Exception $e) {
        $sys_msg = "<div class='alert error'>❌ 更新失败：" . $e->getMessage() . "</div>";
    }
}

// B. 处理【删除】
if (isset($_GET['del'])) {
    $conn->prepare("DELETE FROM appointments WHERE id = ?")->execute([(int)$_GET['del']]);
    header("Location: index.php"); exit;
}

// C. 处理【限额设置】
if (isset($_POST['batch_update'])) {
    $month = $_POST['month']; $limit = (int)$_POST['limit'];
    $days = date('t', strtotime($month . "-01"));
    $stmt = $conn->prepare("INSERT INTO daily_limits (date, max_num) VALUES (?, ?) ON DUPLICATE KEY UPDATE max_num = ?");
    for ($d=1; $d<=$days; $d++) $stmt->execute([$month.'-'.str_pad($d,2,'0',STR_PAD_LEFT), $limit, $limit]);
    $sys_msg = "<div class='alert success'>✅ {$month} 全月限额已设置为 {$limit}</div>";
}
if (isset($_POST['single_update'])) {
    $stmt = $conn->prepare("INSERT INTO daily_limits (date, max_num) VALUES (?, ?) ON DUPLICATE KEY UPDATE max_num = ?");
    $stmt->execute([$_POST['date'], $_POST['limit'], $_POST['limit']]);
    $sys_msg = "<div class='alert success'>✅ {$_POST['date']} 限额已更新</div>";
}

// ==================================================
// 4. 数据读取 (列表 & 图表)
// ==================================================
$list = $conn->query("SELECT * FROM appointments ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$chart_sql = "SELECT DATE_FORMAT(book_time, '%d') as day, COUNT(*) as count FROM appointments WHERE DATE_FORMAT(book_time, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') GROUP BY DATE(book_time)";
$chart_data = $conn->query($chart_sql)->fetchAll(PDO::FETCH_ASSOC);

$chart_json = []; foreach($chart_data as $r) $chart_json[intval($r['day'])] = $r['count'];
$final_labels = []; $final_counts = [];
for($i=1; $i<=date('t'); $i++){ $final_labels[]=$i."日"; $final_counts[]=isset($chart_json[$i])?$chart_json[$i]:0; }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>预约管理控制台</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        :root {
            --primary: #4f46e5; /* 更高级的靛蓝色 */
            --primary-hover: #4338ca;
            --bg: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --border: #e5e7eb;
            --danger: #ef4444;
            --success: #10b981;
        }
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text-main); }
        
        /* 布局 */
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        /* 顶部导航 */
        .navbar { background: var(--card-bg); padding: 15px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-radius: 8px; }
        .brand { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px; color: var(--primary); }
        .nav-links a { text-decoration: none; color: var(--text-sub); margin-left: 20px; font-size: 14px; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .nav-links a:hover { color: var(--primary); }
        .nav-links a.logout { color: var(--danger); }

        /* 卡片通用 */
        .card { background: var(--card-bg); border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px; border: 1px solid var(--border); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-title { font-size: 18px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 8px; }

        /* 状态提示 */
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 8px; animation: slideIn 0.3s ease; }
        .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        @keyframes slideIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* 表单控件 */
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-sub); margin-bottom: 5px; text-transform: uppercase; }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; }
        input:focus, textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        .btn { padding: 10px 16px; border-radius: 6px; border: none; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-danger { background: #fee2e2; color: #dc2626; }
        .btn-danger:hover { background: #fecaca; }
        .btn-edit { background: #e0e7ff; color: #4338ca; }
        .btn-edit:hover { background: #c7d2fe; }

        /* 表格样式 */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; padding: 12px 16px; color: var(--text-sub); font-weight: 600; background: #f9fafb; border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: 12px 16px; border-bottom: 1px solid var(--border); color: var(--text-main); vertical-align: middle; }
        tbody tr:hover { background: #f9fafb; }
        .user-avatar { width: 32px; height: 32px; background: #e0e7ff; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; margin-right: 10px; }
        .user-info { display: flex; align-items: center; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500; background: #f3f4f6; color: #4b5563; }

        /* 弹窗 Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-box { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); animation: modalPop 0.3s ease; }
        @keyframes modalPop { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .modal-close { cursor: pointer; color: #999; }
        .modal-close:hover { color: #333; }
        
        /* 谷歌图标微调 */
        .material-symbols-outlined { font-size: 20px; }
    </style>
</head>
<body>

<div class="container">
    <div class="navbar">
        <div class="brand">
            <span class="material-symbols-outlined">calendar_month</span>
            管理控制台
        </div>
        <div class="nav-links">
            <a href="../index.php" target="_blank">
                <span class="material-symbols-outlined">visibility</span> 预览前台
            </a>
            <a href="login.php" class="logout">
                <span class="material-symbols-outlined">logout</span> 退出
            </a>
        </div>
    </div>

    <?= $sys_msg ?>

    <div class="form-row">
        <div class="form-group" style="flex: 2;">
            <div class="card" style="height: 100%;">
                <div class="card-header">
                    <h3 class="card-title"><span class="material-symbols-outlined">bar_chart</span> 本月预约趋势</h3>
                    <span class="status-badge">总计: <?= array_sum($final_counts) ?> 人</span>
                </div>
                <div style="height: 250px;">
                    <canvas id="adminChart"></canvas>
                </div>
            </div>
        </div>

        <div class="form-group" style="flex: 1;">
            <div class="card" style="height: 100%;">
                <div class="card-header">
                    <h3 class="card-title"><span class="material-symbols-outlined">tune</span> 名额设置</h3>
                </div>
                
                <form method="post" style="margin-bottom: 20px;">
                    <label class="form-label">整月批量设置</label>
                    <div style="display:flex; gap:10px;">
                        <input type="month" name="month" value="<?= date('Y-m') ?>" required>
                        <input type="number" name="limit" placeholder="50" style="width:70px" required>
                    </div>
                    <button type="submit" name="batch_update" class="btn btn-primary" style="margin-top:10px; width:100%">应用设置</button>
                </form>

                <hr style="border:0; border-top:1px dashed var(--border); margin: 20px 0;">

                <form method="post">
                    <label class="form-label">单日单独调整</label>
                    <div style="display:flex; gap:10px;">
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                        <input type="number" name="limit" placeholder="20" style="width:70px" required>
                    </div>
                    <button type="submit" name="single_update" class="btn btn-primary" style="margin-top:10px; width:100%; background:#4b5563;">修改单日</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><span class="material-symbols-outlined">list_alt</span> 预约列表</h3>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户信息 (昵称/账号)</th>
                        <th>预约日期</th>
                        <th>留言备注</th>
                        <th style="text-align:right">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($list as $item): ?>
                    <tr>
                        <td>#<?= $item['id'] ?></td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar"><?= mb_substr($item['name'], 0, 1) ?></div>
                                <div>
                                    <div style="font-weight:600"><?= htmlspecialchars($item['name']) ?></div>
                                    <div style="font-size:12px; color:#6b7280"><?= htmlspecialchars($item['phone']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-family:monospace; font-weight:600; background:#f3f4f6; padding:4px 8px; border-radius:4px;">
                                <?= date('Y-m-d', strtotime($item['book_time'])) ?>
                            </span>
                        </td>
                        <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#6b7280;">
                            <?= htmlspecialchars($item['message']) ?: '-' ?>
                        </td>
                        <td style="text-align:right;">
                            <button class="btn btn-sm btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">
                                <span class="material-symbols-outlined" style="font-size:16px;">edit</span> 编辑
                            </button>
                            <a href="?del=<?= $item['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定要删除该条预约吗？')">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span> 删除
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <span>✏️ 编辑预约信息</span>
            <span class="modal-close" onclick="closeModal()">✕</span>
        </div>
        <form method="post">
            <input type="hidden" name="edit_id" id="modal_id">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="form-label">微信名 / 电报名</label>
                <input type="text" name="edit_name" id="modal_name" required>
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="form-label">微信号 / 电报号</label>
                <input type="text" name="edit_phone" id="modal_phone" required>
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="form-label">预约日期</label>
                <input type="date" name="edit_date" id="modal_date" required>
            </div>
            
            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label">留言备注</label>
                <textarea name="edit_message" id="modal_message" rows="3"></textarea>
            </div>
            
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn" onclick="closeModal()" style="background:#f3f4f6; color:#333;">取消</button>
                <button type="submit" name="update_appointment" class="btn btn-primary">保存修改</button>
            </div>
        </form>
    </div>
</div>

<script>
    // 1. 图表初始化
    new Chart(document.getElementById('adminChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($final_labels) ?>,
            datasets: [{
                label: '每日预约',
                data: <?= json_encode($final_counts) ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { borderDash: [5, 5] } } }
        }
    });

    // 2. 弹窗控制逻辑
    const modal = document.getElementById('editModal');
    
    function openEditModal(data) {
        // 填充表单数据
        document.getElementById('modal_id').value = data.id;
        document.getElementById('modal_name').value = data.name;
        document.getElementById('modal_phone').value = data.phone; // phone字段存的是账号
        document.getElementById('modal_message').value = data.message;
        
        // 处理日期格式 (截取前10位 YYYY-MM-DD)
        let dateVal = data.book_time.split(' ')[0];
        document.getElementById('modal_date').value = dateVal;
        
        // 显示弹窗
        modal.style.display = 'flex';
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }

    // 点击遮罩关闭
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
</script>

</body>
</html>
