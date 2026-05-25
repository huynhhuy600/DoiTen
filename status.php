<?php
// status.php - Trang theo dõi trạng thái hệ thống
require_once __DIR__ . '/config/settings.php';

// Tự động xóa các file info.json quá cũ (sau 3 tiếng)
function cleanOldSessions() {
    $dirs = glob(TEMP_DIR . '*', GLOB_ONLYDIR);
    $now = time();
    foreach ($dirs as $dir) {
        $infoFile = $dir . DIRECTORY_SEPARATOR . 'info.json';
        if (file_exists($infoFile)) {
            $mtime = filemtime($infoFile);
            if ($now - $mtime > 3600 * 3) {
                @unlink($infoFile); // Xóa file info
                // Không xóa cả thư mục vì có thể là phiên cũ chưa xử lý xong, chỉ xóa info.
            }
        }
    }
}

function getActiveUsers() {
    $dirs = glob(TEMP_DIR . '*', GLOB_ONLYDIR);
    $users = [];
    $now = time();
    
    foreach ($dirs as $dir) {
        $infoFile = $dir . DIRECTORY_SEPARATOR . 'info.json';
        if (file_exists($infoFile)) {
            $data = json_decode(file_get_contents($infoFile), true);
            if ($data && isset($data['ip'])) {
                $lastActive = $data['last_active'] ?? filemtime($infoFile);
                // Giới hạn active trong vòng 30 phút
                if ($now - $lastActive <= 1800) {
                    $ip = $data['ip'];
                    if (!isset($users[$ip])) {
                        $users[$ip] = ['last_active' => $lastActive, 'sessions' => 1];
                    } else {
                        $users[$ip]['sessions']++;
                        $users[$ip]['last_active'] = max($users[$ip]['last_active'], $lastActive);
                    }
                }
            }
        }
    }
    
    // Sắp xếp giảm dần theo thời gian active
    uasort($users, function($a, $b) {
        return $b['last_active'] <=> $a['last_active'];
    });
    
    return $users;
}

function getSystemStatus() {
    $os = strtoupper(substr(PHP_OS, 0, 3));
    if ($os === 'WIN') {
        // Windows
        $cpu = shell_exec('wmic cpu get loadpercentage /value 2>nul');
        $cpuLoad = preg_match('/LoadPercentage=(\d+)/i', (string)$cpu, $m) ? $m[1] . '%' : 'N/A';
        
        $ram = shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /value 2>nul');
        $freeRam = preg_match('/FreePhysicalMemory=(\d+)/i', (string)$ram, $m) ? round($m[1] / 1024) : 0;
        $totalRam = preg_match('/TotalVisibleMemorySize=(\d+)/i', (string)$ram, $m) ? round($m[1] / 1024) : 0;
        $usedRam = $totalRam - $freeRam;
        $ramStr = $totalRam > 0 ? "{$usedRam}MB / {$totalRam}MB" : 'N/A';
        
        return ['cpu' => $cpuLoad, 'ram' => $ramStr];
    } else {
        // Linux (Docker)
        $cpu = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}' 2>/dev/null");
        $cpuLoad = $cpu ? trim($cpu) . '%' : 'N/A';
        
        $ram = shell_exec("free -m | awk 'NR==2{printf \"%sMB / %sMB\", $3,$2 }' 2>/dev/null");
        $ramStr = $ram ? trim($ram) : 'N/A';
        
        return ['cpu' => $cpuLoad, 'ram' => $ramStr];
    }
}

// Xử lý request AJAX
if (isset($_GET['ajax'])) {
    cleanOldSessions();
    jsonResponse([
        'users' => getActiveUsers(),
        'system' => getSystemStatus()
    ]);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - OCR System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --bg-card: #1e293b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .card h2 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .metric {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        th { color: var(--text-muted); font-weight: 500; }
        .badge {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .last-update {
            color: var(--text-muted);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🖥️ Theo dõi Hệ thống OCR</h1>
            <div class="last-update" id="lastUpdate">Đang tải...</div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Tải CPU (Server)</h2>
                <div class="metric" id="cpuLoad">--%</div>
            </div>
            <div class="card">
                <h2>RAM (Đã dùng / Tổng)</h2>
                <div class="metric" id="ramLoad">--MB / --MB</div>
                <div style="margin-top: 10px; font-size: 13px; color: var(--warning);">Lưu ý: Nếu RAM đầy sẽ gây chậm/lag hệ thống</div>
            </div>
        </div>

        <div class="card">
            <h2>👥 Người dùng đang hoạt động (30 phút qua)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Địa chỉ IP</th>
                        <th>Số phiên làm việc</th>
                        <th>Hoạt động cuối</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="userTable">
                    <tr><td colspan="4" style="text-align:center; color:#94a3b8">Đang lấy dữ liệu...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function timeAgo(ts) {
            const diff = Math.floor(Date.now() / 1000) - ts;
            if (diff < 60) return diff + ' giây trước';
            if (diff < 3600) return Math.floor(diff/60) + ' phút trước';
            return Math.floor(diff/3600) + ' giờ trước';
        }

        async function fetchStatus() {
            try {
                const r = await fetch('?ajax=1');
                const d = await r.json();
                
                document.getElementById('cpuLoad').textContent = d.system.cpu;
                document.getElementById('ramLoad').textContent = d.system.ram;
                
                const now = new Date();
                document.getElementById('lastUpdate').textContent = 'Cập nhật lúc: ' + now.toLocaleTimeString();

                const tbody = document.getElementById('userTable');
                if (Object.keys(d.users).length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#94a3b8">Không có người dùng nào đang hoạt động.</td></tr>';
                } else {
                    let html = '';
                    for (const [ip, info] of Object.entries(d.users)) {
                        const status = (Math.floor(Date.now()/1000) - info.last_active < 120) 
                            ? '<span class="badge">Đang chạy OCR</span>' 
                            : '<span class="badge" style="background:rgba(148,163,184,0.2); color:#94a3b8">Đang xem</span>';
                        
                        html += `
                            <tr>
                                <td style="font-weight:600; font-family:monospace">${ip}</td>
                                <td>${info.sessions} phiên</td>
                                <td>${timeAgo(info.last_active)}</td>
                                <td>${status}</td>
                            </tr>
                        `;
                    }
                    tbody.innerHTML = html;
                }
            } catch (e) {
                console.error("Lỗi cập nhật", e);
            }
        }

        // Cập nhật mỗi 5 giây
        fetchStatus();
        setInterval(fetchStatus, 5000);
    </script>
</body>
</html>
