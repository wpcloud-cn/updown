<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>updown服务状态检测</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>updown服务状态检测</h1>
                <div class="last-check-info">
                    最后检测: <span id="last-check-time">—</span>
                </div>
            </div>
            <button id="refresh-btn" class="refresh-btn">立即刷新</button>
        </div>
        
        <div class="card">
            <div class="card-header">
                监测目标列表
                <span style="font-size: 13px; font-weight: normal; margin-left: 12px; color: #64748b;">
                    点击表头可排序
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th>目标</th>
                                <th>状态</th>
                                <th>响应时间</th>
                                <th>最后检测</th>
                            </tr>
                        </thead>
                        <tbody id="monitor-tbody">
                            <tr><td colspan="4" class="empty-state">加载中，稍等</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <a href="admin.php">管理后台</a>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>