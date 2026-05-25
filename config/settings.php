<?php
// ====================================================
// CẤU HÌNH HỆ THỐNG
// ====================================================

// Tự động tìm Ghostscript (không cần sửa tay sau khi cài)
function findGhostscript(): string {
    // Tìm trong Program Files (64-bit)
    $dirs64 = glob('C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe');
    if (!empty($dirs64)) return end($dirs64);
    // Tìm trong Program Files (x86 - 32-bit)
    $dirs32 = glob('C:\\Program Files (x86)\\gs\\gs*\\bin\\gswin32c.exe');
    if (!empty($dirs32)) return end($dirs32);
    // Thử gswin64c trực tiếp (nếu đã thêm vào PATH)
    $test = shell_exec('gswin64c --version 2>&1');
    if ($test && preg_match('/^\d/', trim($test))) return 'gswin64c';
    $test2 = shell_exec('gs --version 2>&1');
    if ($test2 && preg_match('/^\d/', trim($test2))) return 'gs';
    // Fallback: đường dẫn mặc định (sửa lại nếu cài version khác)
    return 'C:\\Program Files\\gs\\gs10.04.0\\bin\\gswin64c.exe';
}

define('GS_PATH', findGhostscript());

// Đường dẫn Tesseract OCR (đã tìm thấy tự động)
function findTesseract(): string {
    $candidates = [
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Tesseract-OCR\\tesseract.exe',
    ];
    foreach ($candidates as $c) {
        if (file_exists($c)) return $c;
    }
    $test = shell_exec('tesseract --version 2>&1');
    if ($test && stripos($test, 'tesseract') !== false) return 'tesseract';
    return 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
}

define('TESSERACT_PATH', findTesseract());

// Thư mục lưu trữ
define('BASE_DIR',      dirname(__DIR__));
define('UPLOAD_DIR',    BASE_DIR . DIRECTORY_SEPARATOR . 'uploads'   . DIRECTORY_SEPARATOR);
define('OUTPUT_DIR',    BASE_DIR . DIRECTORY_SEPARATOR . 'output'    . DIRECTORY_SEPARATOR);
define('TEMP_DIR',      BASE_DIR . DIRECTORY_SEPARATOR . 'temp'      . DIRECTORY_SEPARATOR);
define('TEMPLATE_DIR',  BASE_DIR . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($scriptDir === '/') $scriptDir = '';

// Sử dụng đường dẫn tương đối để tránh mọi lỗi liên quan đến Proxy/HTTPS/Port
define('BASE_URL', preg_replace('#/api$#', '', $scriptDir));

// Độ phân giải render PDF (DPI): 100=rất nhanh, 150=nhanh, 200=tốt, 300=chậm
define('OCR_DPI', 150);

// Số luồng CPU tối đa cho Tesseract (0 = dùng tất cả CPU)
// Đặt = số lõi CPU của máy để tốc độ cao nhất
define('TESSERACT_THREADS', 0); // 0 = auto (dùng tất cả)

// Ghostscript: Số luồng render song song
define('GS_THREADS', 8); // Tăng nếu máy có nhiều core

// Kích thước file tối đa (200MB)
define('MAX_FILE_SIZE', 200 * 1024 * 1024);

// Header JSON
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hàm ghi log trạng thái active của user cho trang Dashboard
function logActiveUser(string $sid): void {
    if (!$sid) return;
    $infoFile = TEMP_DIR . $sid . DIRECTORY_SEPARATOR . 'info.json';
    $dir = dirname($infoFile);
    if (is_dir($dir)) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $data = [
            'ip' => $ip,
            'last_active' => time()
        ];
        file_put_contents($infoFile, json_encode($data));
    }
}
