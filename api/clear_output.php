<?php
// api/clear_output.php - Xóa toàn bộ file ZIP/PDF trong thư mục output
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/settings.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Chỉ cho phép POST để tránh xóa nhầm
if ($method !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Phương thức không hợp lệ, dùng POST'], 405);
}

$mode = $_POST['mode'] ?? 'all'; // 'all' hoặc 'old' (xóa file cũ hơn X giờ)
$hoursOld = max(1, intval($_POST['hours'] ?? 24));

$outputDir = OUTPUT_DIR;

if (!is_dir($outputDir)) {
    jsonResponse(['success' => true, 'deleted' => 0, 'message' => 'Thư mục output trống']);
}

$deleted = 0;
$totalSize = 0;
$errors = [];
$now = time();

// Lấy tất cả file trong output (bao gồm thư mục con)
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($outputDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

$filesToDelete = [];
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $ext = strtolower($file->getExtension());
        // Chỉ xóa file PDF, ZIP, PNG, JPG trong output
        if (in_array($ext, ['pdf', 'zip', 'png', 'jpg', 'jpeg'])) {
            if ($mode === 'old') {
                // Chỉ xóa file cũ hơn X giờ
                $fileAge = ($now - $file->getMTime()) / 3600;
                if ($fileAge >= $hoursOld) {
                    $filesToDelete[] = $file->getRealPath();
                    $totalSize += $file->getSize();
                }
            } else {
                // Xóa tất cả
                $filesToDelete[] = $file->getRealPath();
                $totalSize += $file->getSize();
            }
        }
    }
}

// Xóa file
foreach ($filesToDelete as $path) {
    if (@unlink($path)) {
        $deleted++;
    } else {
        $errors[] = basename($path);
    }
}

// Xóa thư mục con rỗng (nếu có)
foreach ($iterator as $file) {
    if ($file->isDir()) {
        @rmdir($file->getRealPath());
    }
}

$sizeMB = round($totalSize / 1024 / 1024, 2);

jsonResponse([
    'success'  => true,
    'deleted'  => $deleted,
    'size_mb'  => $sizeMB,
    'errors'   => $errors,
    'message'  => "Đã xóa $deleted file (tiết kiệm {$sizeMB} MB)"
]);
