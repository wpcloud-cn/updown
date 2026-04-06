<?php
// admin.php 后台  初始密码admin123
session_start();
require_once __DIR__ . '/config.php';

// 检查是否登录
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $password = $_POST['password'] ?? '';
    global $admin_password;
    if ($password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $login_error = '密码错误';
    }
}

// 登出
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// 未登录时登录
if (!isLoggedIn()) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>updown管理后台登录</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                background: #f5f7fa;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-card {
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
                padding: 32px;
                width: 360px;
            }
            h1 {
                font-size: 24px;
                font-weight: 500;
                margin-bottom: 24px;
                color: #1e293b;
            }
            .form-group {
                margin-bottom: 20px;
            }
            label {
                display: block;
                margin-bottom: 8px;
                font-size: 14px;
                color: #475569;
            }
            input {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                font-size: 14px;
                transition: border-color 0.2s;
            }
            input:focus {
                outline: none;
                border-color: #2c7da0;
            }
            button {
                width: 100%;
                padding: 10px;
                background: #2c7da0;
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                transition: background 0.2s;
            }
            button:hover {
                background: #1f5e7a;
            }
            .error {
                background: #fee2e2;
                color: #b91c1c;
                padding: 10px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="login-card">
            <h1>updown管理后台登录</h1>
            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" required autofocus>
                </div>
                <button type="submit" name="login">登录</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 已登录，显示管理界面
// 读取数据
function readDataAdmin() {
    global $data_file, $default_settings, $default_urls;
    if (!file_exists($data_file)) {
        $data = [
            'settings' => $default_settings,
            'urls' => $default_urls
        ];
        file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $data;
    }
    $content = file_get_contents($data_file);
    return json_decode($content, true);
}

function writeDataAdmin($data) {
    global $data_file;
    file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// 处理增删改操作
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $data = readDataAdmin();
    
    if ($action === 'add') {
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
            $new_id = 1;
            if (!empty($data['urls'])) {
                $new_id = max(array_column($data['urls'], 'id')) + 1;
            }
            $data['urls'][] = [
                'id' => $new_id,
                'url' => $url,
                'description' => $description,
                'last_status' => null,
                'last_response_time' => null,
                'last_checked' => null,
                'success' => null
            ];
            writeDataAdmin($data);
            $message = '添加成功';
            $messageType = 'success';
        } else {
            $message = '无效的URL地址';
            $messageType = 'error';
        }
    }
    elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($id && $url && filter_var($url, FILTER_VALIDATE_URL)) {
            foreach ($data['urls'] as $key => $item) {
                if ($item['id'] === $id) {
                    $data['urls'][$key]['url'] = $url;
                    $data['urls'][$key]['description'] = $description;
                    break;
                }
            }
            writeDataAdmin($data);
            $message = '编辑成功';
            $messageType = 'success';
        } else {
            $message = '无效的URL地址';
            $messageType = 'error';
        }
    }
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        foreach ($data['urls'] as $key => $item) {
            if ($item['id'] === $id) {
                unset($data['urls'][$key]);
                break;
            }
        }
        $data['urls'] = array_values($data['urls']);
        writeDataAdmin($data);
        $message = '删除成功';
        $messageType = 'success';
    }
    elseif ($action === 'save_settings') {
        $timeout = intval($_POST['timeout'] ?? 5);
        $auto_refresh_interval = intval($_POST['auto_refresh_interval'] ?? 300);
        $auto_refresh_enabled = isset($_POST['auto_refresh_enabled']) ? true : false;
        
        if ($timeout < 1) $timeout = 1;
        if ($auto_refresh_interval < 0) $auto_refresh_interval = 0;
        
        $data['settings']['timeout'] = $timeout;
        $data['settings']['auto_refresh_interval'] = $auto_refresh_interval;
        $data['settings']['auto_refresh_enabled'] = $auto_refresh_enabled;
        writeDataAdmin($data);
        $message = '设置已保存';
        $messageType = 'success';
    }
    
    // 刷新页面避免重复提交
    header('Location: admin.php?msg=' . urlencode($message) . '&type=' . $messageType);
    exit;
}

$message = $_GET['msg'] ?? '';
$messageType = $_GET['type'] ?? '';
$data = readDataAdmin();
$urls = $data['urls'];
$settings = $data['settings'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>updown监控管理后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f5f7fa;
            padding: 24px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        h1 {
            font-size: 28px;
            font-weight: 500;
            color: #1e293b;
        }
        .logout {
            color: #64748b;
            text-decoration: none;
            padding: 8px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .logout:hover {
            background: #f1f5f9;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 500;
            font-size: 18px;
            color: #1e293b;
        }
        .card-body {
            padding: 24px;
        }
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #e0f2e9;
            color: #2c6e3f;
        }
        .message.error {
            background: #fee2e2;
            color: #b91c1c;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }
        .url-cell {
            font-family: monospace;
            font-size: 13px;
            word-break: break-all;
        }
        .actions {
            display: flex;
            gap: 8px;
        }
        .btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
        }
        .btn-sm {
            font-size: 12px;
            padding: 4px 10px;
        }
        .btn-primary {
            background: #2c7da0;
            color: white;
        }
        .btn-primary:hover {
            background: #1f5e7a;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        .btn-danger {
            background: #e53e3e;
            color: white;
        }
        .btn-danger:hover {
            background: #c53030;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: #475569;
        }
        input, textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #2c7da0;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .inline-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .inline-checkbox input {
            width: auto;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #e2e8f0;
        }
        .edit-form, .add-form {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
        }
        .edit-form {
            margin-top: 12px;
        }
        [data-edit-form] {
            display: none;
        }
        [data-edit-form].active {
            display: block;
        }
        @media (max-width: 768px) {
            body { padding: 16px; }
            .form-row { grid-template-columns: 1fr; }
            table { font-size: 13px; }
            .actions { flex-direction: column; gap: 4px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>updown服务监控控制面板</h1>
        <a href="?logout=1" class="logout">登出</a>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- 这里是URL列表 -->
    <div class="card">
        <div class="card-header">监测目标列表</div>
        <div class="card-body">
            <?php if (empty($urls)): ?>
                <p style="color: #64748b;">暂无监测目标，请添加</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>ID</th><th>URL</th><th>描述</th><th>最后状态</th><th>响应时间</th><th>最后检测</th><th>操作</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($urls as $item): ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td class="url-cell"><?php echo htmlspecialchars($item['url']); ?></td>
                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                            <td>
                                <?php if ($item['last_status']): ?>
                                    <span style="color: <?php echo ($item['success'] ? '#2c6e3f' : '#b91c1c'); ?>">
                                        <?php echo $item['last_status']; ?>
                                    </span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?php echo $item['last_response_time'] ? $item['last_response_time'] . ' ms' : '—'; ?></td>
                            <td><?php echo $item['last_checked'] ?: '—'; ?></td>
                            <td class="actions">
                                <button class="btn btn-secondary btn-sm" onclick="showEditForm(<?php echo $item['id']; ?>)">编辑</button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确定删除？')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">删除</button>
                                </form>
                            </td>
                        </tr>
                        <tr id="edit-row-<?php echo $item['id']; ?>" style="display: none;">
                            <td colspan="7" style="padding: 0;">
                                <div class="edit-form">
                                    <form method="post">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>URL</label>
                                                <input type="url" name="url" value="<?php echo htmlspecialchars($item['url']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>描述</label>
                                                <input type="text" name="description" value="<?php echo htmlspecialchars($item['description']); ?>">
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="hideEditForm(<?php echo $item['id']; ?>)">取消</button>
                                            <button type="submit" class="btn btn-primary btn-sm">保存</button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- 添加新的URL -->
    <div class="card">
        <div class="card-header">添加新目标</div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label>URL *</label>
                        <input type="url" name="url" required placeholder="https://example.com">
                    </div>
                    <div class="form-group">
                        <label>描述</label>
                        <input type="text" name="description" placeholder="简短描述">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">添加</button>
            </form>
        </div>
    </div>

    <!-- 系统设置 -->
    <div class="card">
        <div class="card-header">系统设置</div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="save_settings">
                <div class="form-row">
                    <div class="form-group">
                        <label>检测超时时间（秒）</label>
                        <input type="number" name="timeout" value="<?php echo $settings['timeout']; ?>" min="1" step="1">
                    </div>
                    <div class="form-group">
                        <label>自动刷新间隔（秒，0表示禁用）</label>
                        <input type="number" name="auto_refresh_interval" value="<?php echo $settings['auto_refresh_interval']; ?>" min="0" step="1">
                    </div>
                </div>
                <div class="form-group inline-checkbox">
                    <input type="checkbox" name="auto_refresh_enabled" id="auto_enabled" <?php echo $settings['auto_refresh_enabled'] ? 'checked' : ''; ?>>
                    <label for="auto_enabled" style="margin-bottom: 0;">启用自动刷新（访问前端页面时自动检测）</label>
                </div>
                <button type="submit" class="btn btn-primary">保存设置</button>
            </form>
        </div>
    </div>

    <div style="margin-top: 16px; text-align: center; color: #94a3b8; font-size: 13px;">
        <a href="index.php" style="color: #2c7da0;">← 返回监控面板</a>
    </div>
</div>

<script>
function showEditForm(id) {
    const row = document.getElementById('edit-row-' + id);
    if (row) {
        row.style.display = 'table-row';
    }
}
function hideEditForm(id) {
    const row = document.getElementById('edit-row-' + id);
    if (row) {
        row.style.display = 'none';
    }
}
</script>
</body>
</html>