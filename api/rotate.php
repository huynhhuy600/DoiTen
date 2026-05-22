<?php
// api/rotate.php - Rotate a rendered page image
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/settings.php';

$sid   = preg_replace('/[^a-zA-Z0-9_.]/', '', $_GET['sid'] ?? '');
$page  = max(1, intval($_GET['page'] ?? 1));
$angle = floatval($_GET['angle'] ?? 90); // default rotate 90 degrees

if (!$sid) {
    jsonResponse(['success'=>false, 'error'=>'Thiếu session_id']);
}

$imgFile = TEMP_DIR . $sid . DIRECTORY_SEPARATOR . sprintf('page_%04d.png', $page);
if (!file_exists($imgFile)) {
    jsonResponse(['success'=>false, 'error'=>'Không tìm thấy ảnh của trang này']);
}

// Ensure GD extension is loaded
if (!function_exists('imagecreatefrompng') || !function_exists('imagerotate')) {
    jsonResponse(['success'=>false, 'error'=>'Thiếu extension GD trong PHP (không thể xoay ảnh)']);
}

$img = imagecreatefrompng($imgFile);
if (!$img) {
    jsonResponse(['success'=>false, 'error'=>'Lỗi đọc file PNG']);
}

// Xoay ảnh
// imagerotate in PHP: angle is given in degrees counterclockwise.
// So angle 90 = rotate left (counterclockwise).
$rotated = imagerotate($img, $angle, 0);
if (!$rotated) {
    imagedestroy($img);
    jsonResponse(['success'=>false, 'error'=>'Lỗi xoay ảnh']);
}

// Lưu đè lại file cũ
imagepng($rotated, $imgFile);

imagedestroy($img);
imagedestroy($rotated);

jsonResponse(['success'=>true, 'page'=>$page, 'angle'=>$angle]);
