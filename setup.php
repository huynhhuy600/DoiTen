<?php
// setup.php - Kiểm tra môi trường và tạo thư mục
require_once __DIR__ . '/config/settings.php';

$checks = [];

// 1. Tạo thư mục cần thiết
foreach ([UPLOAD_DIR, OUTPUT_DIR, TEMP_DIR] as $dir) {
    if (!is_dir($dir)) {
        $ok = mkdir($dir, 0755, true);
        $checks[] = ['name'=>'Tạo thư mục: '.$dir, 'ok'=>$ok, 'msg'=>$ok?'OK':'FAILED - Kiểm tra quyền ghi'];
    } else {
        $checks[] = ['name'=>'Thư mục: '.$dir, 'ok'=>true, 'msg'=>'Tồn tại'];
    }
}

// 2. Kiểm tra quyền ghi
foreach ([UPLOAD_DIR, OUTPUT_DIR, TEMP_DIR] as $dir) {
    $w = is_writable($dir);
    $checks[] = ['name'=>'Ghi được: '.$dir, 'ok'=>$w, 'msg'=>$w?'OK':'FAILED - chmod 755 hoặc chạy với quyền admin'];
}

// 3. Kiểm tra Ghostscript
$gsTest = shell_exec('"' . GS_PATH . '" --version 2>&1');
$gsOk   = $gsTest && preg_match('/^\d+\.\d+/', trim($gsTest));
$checks[] = ['name'=>'Ghostscript', 'ok'=>$gsOk, 'msg'=>$gsOk?'Version: '.trim($gsTest):'KHÔNG TÌM THẤY - Tải tại: https://www.ghostscript.com/releases/gsdnld.html<br>Sau khi cài, sửa GS_PATH trong config/settings.php'];

// 4. Kiểm tra Tesseract
$tsTest = shell_exec('"' . TESSERACT_PATH . '" --version 2>&1');
$tsOk   = $tsTest && stripos($tsTest, 'tesseract') !== false;
$checks[] = ['name'=>'Tesseract OCR', 'ok'=>$tsOk, 'msg'=>$tsOk?nl2br(htmlspecialchars(trim($tsTest))):'KHÔNG TÌM THẤY - Tải tại: https://github.com/UB-Mannheim/tesseract/wiki<br>Sau khi cài, sửa TESSERACT_PATH trong config/settings.php'];

// 5. Kiểm tra gói tiếng Việt Tesseract
if ($tsOk) {
    $langs = shell_exec('"' . TESSERACT_PATH . '" --list-langs 2>&1');
    $hasVie = $langs && strpos($langs, 'vie') !== false;
    $checks[] = ['name'=>'Tesseract - gói tiếng Việt (vie)', 'ok'=>$hasVie, 'msg'=>$hasVie?'OK':'THIẾU - Tải vie.traineddata tại: https://github.com/tesseract-ocr/tessdata'];
}

// 6. Kiểm tra pdftk
$ptkTest = shell_exec('pdftk --version 2>&1');
$ptkOk   = $ptkTest && stripos($ptkTest, 'pdftk') !== false;
$checks[] = ['name'=>'pdftk (cắt PDF)', 'ok'=>$ptkOk, 'msg'=>$ptkOk?'OK':'KHÔNG TÌM THẤY - Tải tại: https://www.pdflabs.com/tools/pdftk-the-pdf-toolkit/'];

// 7. PHP upload settings
$maxPost = ini_get('post_max_size');
$maxFile = ini_get('upload_max_filesize');
$checks[] = ['name'=>'PHP post_max_size', 'ok'=>true, 'msg'=>$maxPost . ' (khuyến nghị: 256M)'];
$checks[] = ['name'=>'PHP upload_max_filesize', 'ok'=>true, 'msg'=>$maxFile . ' (khuyến nghị: 256M)'];
$checks[] = ['name'=>'PHP GD extension', 'ok'=>extension_loaded('gd'), 'msg'=>extension_loaded('gd')?'OK':'THIẾU - Bật gd trong php.ini'];
$checks[] = ['name'=>'PHP ZipArchive', 'ok'=>class_exists('ZipArchive'), 'msg'=>class_exists('ZipArchive')?'OK':'THIẾU - Bật zip trong php.ini'];
$checks[] = ['name'=>'PHP exec() enabled', 'ok'=>function_exists('exec'), 'msg'=>function_exists('exec')?'OK':'DISABLED - Xóa exec khỏi disable_functions trong php.ini'];

$allOk = !in_array(false, array_column($checks, 'ok'));
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Kiểm tra hệ thống - OCR DoiTen</title>
<link rel="stylesheet" href="css/style.css">
<style>
body{padding:24px}
.check-row{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-radius:10px;margin-bottom:8px;border:1px solid var(--border)}
.check-row.ok{background:rgba(16,185,129,.07);border-color:rgba(16,185,129,.2)}
.check-row.fail{background:rgba(239,68,68,.07);border-color:rgba(239,68,68,.25)}
.icon{font-size:18px;flex-shrink:0;margin-top:2px}
.cn{font-size:13px;font-weight:600;color:var(--text);min-width:280px}
.cm{font-size:12px;color:var(--text3);flex:1;line-height:1.7}
</style>
</head>
<body>
<h1 style="margin-bottom:24px;font-size:22px">🔧 Kiểm tra môi trường hệ thống</h1>
<?php foreach ($checks as $c): ?>
<div class="check-row <?= $c['ok']?'ok':'fail' ?>">
  <span class="icon"><?= $c['ok']?'✅':'❌' ?></span>
  <span class="cn"><?= htmlspecialchars($c['name']) ?></span>
  <span class="cm"><?= $c['msg'] ?></span>
</div>
<?php endforeach; ?>
<div style="margin-top:24px;padding:16px;background:var(--glass);border:1px solid var(--border);border-radius:12px">
<?php if ($allOk): ?>
  <p style="color:var(--green);font-weight:700;font-size:16px">✅ Tất cả OK! Hệ thống sẵn sàng sử dụng.</p>
  <a href="index.php" class="btn btn-primary" style="margin-top:12px;display:inline-flex">→ Vào hệ thống</a>
<?php else: ?>
  <p style="color:var(--yellow);font-weight:700;font-size:16px">⚠️ Có vấn đề cần khắc phục - xem các mục ❌ ở trên</p>
  <p style="font-size:13px;color:var(--text3);margin-top:8px">Sau khi cài đặt phần mềm, tải lại trang này để kiểm tra lại.</p>
  <a href="setup.php" class="btn btn-secondary" style="margin-top:12px;display:inline-flex">🔄 Kiểm tra lại</a>
<?php endif; ?>
</div>
</body>
</html>
