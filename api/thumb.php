<?php
// api/thumb.php - Trả về ảnh thumbnail của trang PDF
require_once dirname(__DIR__) . '/config/settings.php';

$sid  = preg_replace('/[^a-zA-Z0-9_.]/', '', $_GET['sid']  ?? '');
$page = max(1, intval($_GET['page'] ?? 1));

// Đọc thẳng file thumb do Ghostscript r30 sinh ra
$imgFile = TEMP_DIR . $sid . DIRECTORY_SEPARATOR . sprintf('thumb_%04d.png', $page);
if (!file_exists($imgFile)) {
    // Dự phòng nếu là thư mục cũ (DPI 150)
    $imgFile = TEMP_DIR . $sid . DIRECTORY_SEPARATOR . sprintf('page_%04d.png', $page);
    if (!file_exists($imgFile)) {
        http_response_code(404);
        exit;
    }
}

header('Content-Type: image/png');
// Cache 1 ngày
header('Cache-Control: max-age=86400');
readfile($imgFile);
exit;
