<?php
// api.php 这里可以伪cron
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config.php';

// 读取数据
function readData() {
    global $data_file, $default_settings, $default_urls;
    if (!file_exists($data_file)) {
        $data = [
            'settings' => $default_settings,
            'urls' => $default_urls
        ];
        writeData($data);
        return $data;
    }
    $content = file_get_contents($data_file);
    $data = json_decode($content, true);
    if (!isset($data['settings'])) $data['settings'] = $default_settings;
    if (!isset($data['urls'])) $data['urls'] = [];
    return $data;
}

function writeData($data) {
    global $data_file;
    file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// 检测链接到状态
function checkUrls($urls, $timeout) {
    if (empty($urls)) return [];
    
    $mh = curl_multi_init();
    $handles = [];
    $results = [];
    
    foreach ($urls as $index => $item) {
        $url = $item['url'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);  // 只要获取头部
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Uptime-Monitor/1.0');
        curl_multi_add_handle($mh, $ch);
        $handles[$index] = $ch;
    }
    
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);
    
    foreach ($handles as $index => $ch) {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000; // 转化毫秒
        $error = curl_error($ch);
        
        if ($error || $http_code == 0) {
            $results[$index] = [
                'status' => null,
                'response_time' => null,
                'success' => false
            ];
        } else {
            $results[$index] = [
                'status' => $http_code,
                'response_time' => round($total_time, 2),
                'success' => ($http_code >= 200 && $http_code < 400)
            ];
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    
    return $results;
}

// 执行完整检测然后就更新数据
function performFullCheck() {
    $data = readData();
    $urls = $data['urls'];
    $timeout = $data['settings']['timeout'];
    
    if (empty($urls)) return false;
    
    // 准备检测列表
    $check_list = [];
    foreach ($urls as $index => $url_item) {
        $check_list[$index] = $url_item;
    }
    
    $results = checkUrls($check_list, $timeout);
    
    $now = date('Y-m-d H:i:s');
    foreach ($results as $index => $result) {
        $data['urls'][$index]['last_status'] = $result['status'];
        $data['urls'][$index]['last_response_time'] = $result['response_time'];
        $data['urls'][$index]['last_checked'] = $now;
        $data['urls'][$index]['success'] = $result['success'];
    }
    
    $data['settings']['last_full_check'] = $now;
    writeData($data);
    return true;
}

// 检查自动刷新
function autoRefreshIfNeeded() {
    $data = readData();
    $settings = $data['settings'];
    
    if (!$settings['auto_refresh_enabled']) return false;
    $interval = $settings['auto_refresh_interval'];
    if ($interval <= 0) return false;
    
    $last_check = $settings['last_full_check'];
    if (!$last_check) {
        performFullCheck();
        return true;
    }
    
    $last_time = strtotime($last_check);
    $now = time();
    if (($now - $last_time) >= $interval) {
        // 加锁防止并发
        $lock_file = __DIR__ . '/check.lock';
        $fp = fopen($lock_file, 'c');
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            performFullCheck();
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return true;
    }
    return false;
}

// 获取所有数据前端的
if ($_GET['action'] === 'getData') {
    autoRefreshIfNeeded();  // 自动刷新的检测
    $data = readData();
    
    // 删去其他信息，只返回必要内容
    $response = [
        'settings' => [
            'last_full_check' => $data['settings']['last_full_check'],
            'auto_refresh_enabled' => $data['settings']['auto_refresh_enabled'],
            'auto_refresh_interval' => $data['settings']['auto_refresh_interval']
        ],
        'urls' => $data['urls']
    ];
    echo json_encode($response);
    exit;
}

// 手动刷新
if ($_GET['action'] === 'refresh') {
    performFullCheck();
    $data = readData();
    $response = [
        'settings' => [
            'last_full_check' => $data['settings']['last_full_check']
        ],
        'urls' => $data['urls']
    ];
    echo json_encode($response);
    exit;
}

// 其他未知action
http_response_code(400);
echo json_encode(['error' => 'Invalid action']);