<?php
// api/download.php - Tải ZIP kết quả
require_once dirname(__DIR__) . '/config/settings.php';
$sid  = preg_replace('/[^a-zA-Z0-9_.]/', '', $_GET['sid'] ?? '');
$zip  = preg_replace('/[^a-zA-Z0-9_.\-\s]/', '', $_GET['zip'] ?? '');
$file = OUTPUT_DIR . $zip;
if (!$sid || !$zip || !file_exists($file)) { http_response_code(404); echo 'File không tồn tại'; exit; }
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip . '"');
header('Content-Length: ' . filesize($file));
readfile($file);

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
