<?php
// api/download.php - Tải ZIP kết quả
require_once dirname(__DIR__) . '/config/settings.php';
$sid  = preg_replace('/[^a-zA-Z0-9_.]/', '', $_GET['sid'] ?? '');
$zip  = basename($_GET['zip'] ?? '');
$file = OUTPUT_DIR . $zip;
if (!$sid || !$zip || !file_exists($file)) { 
    http_response_code(200); 
    echo '<h1>Lỗi Tải File</h1>';
    echo '<p>Không tìm thấy file: <strong>' . htmlspecialchars($zip) . '</strong></p>';
    echo '<p>Nguyên nhân phổ biến nhất khi chạy trên IIS là <strong>Thư mục output chưa được cấp quyền Ghi (Write)</strong> cho tài khoản IIS_IUSRS.</p>';
    exit; 
}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip . '"');
header('Content-Length: ' . filesize($file));

// Tắt hoàn toàn output buffering để xả dữ liệu thẳng xuống client
if (ob_get_level()) {
    ob_end_clean();
}

// Đọc file theo chunk (8MB) để tránh tràn RAM và tăng tốc mạng
$handle = fopen($file, 'rb');
$chunkSize = 1024 * 1024 * 8; // 8MB
while (!feof($handle)) {
    echo fread($handle, $chunkSize);
    flush(); // Bắt buộc PHP gửi ngay lượng dữ liệu này xuống IIS
}
fclose($handle);

// Xóa các file temp và thư mục upload cũ để giải phóng ổ cứng
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $path = $dir . DIRECTORY_SEPARATOR . $object;
                if (is_dir($path) && !is_link($path)) {
                    rrmdir($path);
                } else {
                    @unlink($path);
                }
            }
        }
        @rmdir($dir);
    }
}

// Xóa thư mục tạm (ảnh PNG, file OCR)
$tempDir = TEMP_DIR . $sid;
rrmdir($tempDir);

// Xóa thư mục chứa file gốc
$uploadDir = UPLOAD_DIR . $sid;
rrmdir($uploadDir);

exit;
