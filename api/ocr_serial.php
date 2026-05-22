<?php
// api/ocr_serial.php - Cắt phần đáy trang (15%) và binarize để OCR số seri siêu chuẩn
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/settings.php';

$sid  = preg_replace('/[^a-zA-Z0-9_.]/', '', $_GET['sid']  ?? '');
$page = max(1, intval($_GET['page'] ?? 1));

if (!$sid) {
    jsonResponse(['success'=>false,'error'=>'Thiếu session_id']);
}

$imgFile = TEMP_DIR . $sid . DIRECTORY_SEPARATOR . sprintf('page_%04d.png', $page);

// Đảm bảo ảnh độ phân giải cao tồn tại
if (!file_exists($imgFile)) {
    $pdfPath = UPLOAD_DIR . $sid . DIRECTORY_SEPARATOR . 'original.pdf';
    if (!file_exists($pdfPath)) {
        jsonResponse(['success'=>false,'error'=>'Không tìm thấy file PDF gốc để tạo ảnh']);
    }
    $cmd = sprintf(
        '"%s" -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r%d -dUseCropBox -dFirstPage=%d -dLastPage=%d -sOutputFile="%s" "%s" 2>&1',
        GS_PATH, OCR_DPI, $page, $page, $imgFile, $pdfPath
    );
    exec($cmd, $output, $ret);
    if ($ret !== 0 || !file_exists($imgFile)) {
        jsonResponse(['success'=>false,'error'=>'Không thể tạo ảnh gốc.']);
    }
}

// Mở ảnh và cắt (Crop)
$src = @imagecreatefrompng($imgFile);
if (!$src) {
    jsonResponse(['success'=>false,'error'=>'Không thể đọc file ảnh PNG']);
}

$w = imagesx($src);
$h = imagesy($src);

$cx = isset($_GET['cx']) ? floatval($_GET['cx']) : null;
$cy = isset($_GET['cy']) ? floatval($_GET['cy']) : null;
$cw = isset($_GET['cw']) ? floatval($_GET['cw']) : null;
$ch = isset($_GET['ch']) ? floatval($_GET['ch']) : null;

if ($cx !== null && $cy !== null && $cw !== null && $ch !== null) {
    // Chế độ Kéo chọn: tọa độ được tính theo phần trăm (0-100)
    $cropX = intval($w * $cx / 100);
    $cropY = intval($h * $cy / 100);
    $cropW = intval($w * $cw / 100);
    $cropH = intval($h * $ch / 100);
} else {
    // Mặc định: Lấy 15% dưới cùng của trang
    $cropW = $w;
    $cropH = intval($h * 0.15);
    $cropX = 0;
    $cropY = $h - $cropH;
}

$cropImg = imagecrop($src, ['x'=>$cropX, 'y'=>$cropY, 'width'=>$cropW, 'height'=>$cropH]);
imagedestroy($src);

if ($cropImg !== false) {
    // Biến thành trắng đen dựa trên Kênh Đỏ (Red Channel Extraction)
    // Nền đỏ sẽ thành trắng, nền trắng thành trắng, chữ đen thành đen!
    $cw_crop = imagesx($cropImg);
    $ch_crop = imagesy($cropImg);

    for ($y = 0; $y < $ch_crop; $y++) {
        for ($x = 0; $x < $cw_crop; $x++) {
            $rgb = imagecolorat($cropImg, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            
            // Binarization: Nếu kênh Red > 130 thì thành Trắng, ngược lại là Đen
            if ($r > 130) {
                $val = 255;
            } else {
                $val = 0;
            }
            $color = imagecolorallocate($cropImg, $val, $val, $val);
            imagesetpixel($cropImg, $x, $y, $color);
        }
    }

    $cropFile = TEMP_DIR . $sid . DIRECTORY_SEPARATOR . sprintf('crop_serial_%04d.png', $page);
    imagepng($cropImg, $cropFile);
    imagedestroy($cropImg);
} else {
    $cropFile = $imgFile; // Fallback
}

// Chạy OCR trên ảnh vừa cắt (Sử dụng PSM 6: Uniform block of text)
$txtBase = TEMP_DIR . $sid . DIRECTORY_SEPARATOR . sprintf('ocr_serial_%04d', $page);
$cmd = sprintf('"%s" "%s" "%s" -l vie+eng --psm 6 2>&1', TESSERACT_PATH, $cropFile, $txtBase);
exec($cmd, $out, $ret);

$rawText = '';
if (file_exists($txtBase . '.txt')) {
    $rawText = file_get_contents($txtBase . '.txt');
}

// Trích xuất regex
function detectSerialLocal(string $text): string {
    $text = mb_strtoupper($text, 'UTF-8');
    // Thay thế các lỗi OCR hay gặp với chữ S và L
    $text = str_replace(['SỔ', 'SO'], ['S', 'S'], $text);
    
    // Tìm mẫu chuẩn: 1-3 chữ cái + số
    if (preg_match('/([A-ZĐ]{1,3})[\s\-\.]*(\d{5,8})\b/u', $text, $m)) {
        return $m[1] . ' ' . $m[2];
    }
    // Tìm mẫu chữ cách chữ: Vd: A B 123456
    if (preg_match('/([A-ZĐ])[\s\-\.]*([A-ZĐ])[\s\-\.]*(\d{5,8})\b/u', $text, $m)) {
        return $m[1] . $m[2] . ' ' . $m[3];
    }
    
    // Nếu vẫn không tìm được, thử lấy một chuỗi số bất kỳ dài 6-8 ký tự
    if (preg_match('/(\d{6,8})\b/', $text, $m)) {
        return 'UNKNOWN ' . $m[1];
    }
    
    return '';
}

$serial = detectSerialLocal($rawText);

jsonResponse([
    'success'  => true,
    'serial'   => $serial,
    'raw_text' => trim($rawText)
]);
