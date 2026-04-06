// script.js 前端部分
(function() {
    let currentData = null;
    let currentSort = { column: 'status', order: 'asc' };
    
    // DOM元素
    const tbody = document.getElementById('monitor-tbody');
    const refreshBtn = document.getElementById('refresh-btn');
    const lastCheckSpan = document.getElementById('last-check-time');
    
    // 显示加载状态
    function showLoading() {
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.textContent = '检测中';
        }
    }
    
    function hideLoading() {
        if (refreshBtn) {
            refreshBtn.disabled = false;
            refreshBtn.textContent = '立即刷新';
        }
    }
    
    // 获取数据（自动触发自动刷新检测）
    async function fetchData() {
        try {
            const response = await fetch('api.php?action=getData');
            const data = await response.json();
            currentData = data;
            updateLastCheckTime(data.settings.last_full_check);
            renderTable(data.urls);
            return data;
        } catch (error) {
            console.error('获取数据失败:', error);
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state">加载失败，请检查网络</td></tr>';
            }
            return null;
        }
    }
    
    // 手动刷新部分
    async function manualRefresh() {
        showLoading();
        try {
            const response = await fetch('api.php?action=refresh');
            const data = await response.json();
            currentData = data;
            updateLastCheckTime(data.settings.last_full_check);
            renderTable(data.urls);
        } catch (error) {
            console.error('刷新失败:', error);
            alert('刷新失败，请稍后重试');
        } finally {
            hideLoading();
        }
    }
    
    // 更新最后检测时间显示
    function updateLastCheckTime(time) {
        if (lastCheckSpan && time) {
            lastCheckSpan.textContent = time;
        } else if (lastCheckSpan) {
            lastCheckSpan.textContent = '未检测';
        }
    }
    
    // 格式化响应时间
    function formatResponseTime(ms) {
        if (ms === null || ms === undefined) return '—';
        return ms + ' ms';
    }
    
    // 排序函数
    function sortUrls(urls, column, order) {
        const sorted = [...urls];
        sorted.sort((a, b) => {
            let valA, valB;
            switch (column) {
                case 'status':
                    valA = a.success === null ? 2 : (a.success ? 0 : 1);
                    valB = b.success === null ? 2 : (b.success ? 0 : 1);
                    if (valA === valB) {
                        valA = a.last_status || 0;
                        valB = b.last_status || 0;
                    }
                    break;
                case 'response_time':
                    valA = a.last_response_time !== null ? a.last_response_time : Infinity;
                    valB = b.last_response_time !== null ? b.last_response_time : Infinity;
                    break;
                case 'last_checked':
                    valA = a.last_checked || '';
                    valB = b.last_checked || '';
                    break;
                case 'description':
                    valA = (a.description || '').toLowerCase();
                    valB = (b.description || '').toLowerCase();
                    break;
                default:
                    valA = a.id;
                    valB = b.id;
            }
            if (valA < valB) return order === 'asc' ? -1 : 1;
            if (valA > valB) return order === 'asc' ? 1 : -1;
            return 0;
        });
        return sorted;
    }
    
    // 渲染表格
    function renderTable(urls) {
        if (!tbody) return;
        
        if (!urls || urls.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state">暂无监测目标，请前往后台管理添加</td></tr>';
            return;
        }
        
        const sortedUrls = sortUrls(urls, currentSort.column, currentSort.order);
        
        tbody.innerHTML = sortedUrls.map(item => {
            const isSuccess = item.success === true;
            const isFailed = item.success === false;
            const statusCode = item.last_status;
            const responseTime = item.last_response_time;
            const lastChecked = item.last_checked || '未检测';
            const description = item.description || '';
            const url = item.url;
            
            let statusHtml = '';
            if (statusCode === null) {
                statusHtml = '<span class="text-muted">未检测</span>';
            } else if (isSuccess) {
                statusHtml = `<span class="status-text success"><span class="status-dot success"></span> ${statusCode}</span>`;
            } else {
                statusHtml = `<span class="status-text failed"><span class="status-dot failed"></span> ${statusCode}</span>`;
            }
            
            const timeHtml = responseTime !== null 
                ? `<span class="response-time">${responseTime} ms</span>`
                : '<span class="text-muted">—</span>';
            
            return `
                <tr>
                    <td>
                        <div class="url-description">${escapeHtml(description || '未命名')}</div>
                        <div class="url-link">${escapeHtml(url)}</div>
                    </td>
                    <td class="status-badge">${statusHtml}</td>
                    <td>${timeHtml}</td>
                    <td>${escapeHtml(lastChecked)}</td>
                </tr>
            `;
        }).join('');
    }
    
    // 简单的XSS防护
    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    
    // 排序处理
    function handleSort(column) {
        if (currentSort.column === column) {
            currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = column;
            currentSort.order = 'asc';
        }
        
        if (currentData && currentData.urls) {
            renderTable(currentData.urls);
        }
        
        // 更新排序指示器
        updateSortIndicators();
    }
    
    function updateSortIndicators() {
        const headers = document.querySelectorAll('.status-table th');
        headers.forEach(th => {
            const indicator = th.querySelector('.sort-indicator');
            if (indicator) indicator.remove();
        });
        
        const columnMap = {
            '目标': 'description',
            '状态': 'status',
            '响应时间': 'response_time',
            '最后检测': 'last_checked'
        };
        
        for (const [display, col] of Object.entries(columnMap)) {
            if (currentSort.column === col) {
                const th = Array.from(document.querySelectorAll('.status-table th')).find(
                    th => th.textContent.includes(display)
                );
                if (th) {
                    const indicator = document.createElement('span');
                    indicator.className = 'sort-indicator';
                    indicator.textContent = currentSort.order === 'asc' ? ' ↑' : ' ↓';
                    th.appendChild(indicator);
                }
            }
        }
    }
    
    // 自动刷新（前端可选轮询，但主要依赖后端伪cron，这里可选每60秒刷新一次数据但不强制检测）
    // 为了更好的用户体验，每30秒静默刷新数据（不触发检测，只获取最新结果）
    let autoPollInterval = null;
    
    function startAutoPoll() {
        if (autoPollInterval) clearInterval(autoPollInterval);
        autoPollInterval = setInterval(() => {
            fetchData();
        }, 30000); // 30秒刷新一次UI
    }
    
    // 初始化
    async function init() {
        await fetchData();
        
        if (refreshBtn) {
            refreshBtn.addEventListener('click', manualRefresh);
        }
        
        // 绑定排序事件
        const headers = document.querySelectorAll('.status-table th');
        const columnActions = {
            '目标': 'description',
            '状态': 'status',
            '响应时间': 'response_time',
            '最后检测': 'last_checked'
        };
        
        headers.forEach(th => {
            const text = th.textContent.replace('↓', '').replace('↑', '').trim();
            if (columnActions[text]) {
                th.style.cursor = 'pointer';
                th.addEventListener('click', () => handleSort(columnActions[text]));
            }
        });
        
        startAutoPoll();
        updateSortIndicators();
    }
    
    // 页面加载完成后执行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();