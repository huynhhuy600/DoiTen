<?php
// api/upload_template.php - Upload file mẫu cho loại hồ sơ, OCR và lưu văn bản
ob_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/settings.php';

if (!is_dir(TEMPLATE_DIR)) mkdir(TEMPLATE_DIR, 0755, true);
if (!is_dir(TEMP_DIR))     mkdir(TEMP_DIR,     0755, true);

$code = preg_replace('/[^A-Za-z0-9_\-]/', '', $_POST['code'] ?? '');
$file = $_FILES['pdf'] ?? null;

if (!$code) jsonResponse(['success'=>false,'error'=>'Thiếu mã loại hồ sơ']);
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success'=>false,'error'=>'Lỗi upload: ' . ($file['error'] ?? 'không có file')]);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if ($mime !== 'application/pdf') {
    jsonResponse(['success'=>false,'error'=>'Chỉ chấp nhận file PDF']);
}

// Lưu PDF tạm
$tmpPdf = TEMP_DIR . 'tpl_' . $code . '_' . time() . '.pdf';
move_uploaded_file($file['tmp_name'], $tmpPdf);

// Render trang đầu tiên bằng Ghostscript
$tmpImg = TEMP_DIR . 'tpl_' . $code . '_' . time() . '.png';
$gsCmd  = sprintf(
    '"%s" -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r200 -dFirstPage=1 -dLastPage=1 -sOutputFile="%s" "%s" 2>&1',
    GS_PATH, $tmpImg, $tmpPdf
);
exec($gsCmd, $gsOut, $gsRet);
@unlink($tmpPdf);

if ($gsRet !== 0 || !file_exists($tmpImg)) {
    jsonResponse(['success'=>false,'error'=>'Ghostscript lỗi: ' . implode(' ', $gsOut)]);
}

// OCR trang đó
$txtBase = TEMP_DIR . 'tpl_ocr_' . $code . '_' . time();
$tsCmd   = sprintf('"%s" "%s" "%s" -l vie+eng 2>&1', TESSERACT_PATH, $tmpImg, $txtBase);
exec($tsCmd, $tsOut, $tsRet);
@unlink($tmpImg);

$ocrText = file_exists($txtBase . '.txt') ? file_get_contents($txtBase . '.txt') : '';
@unlink($txtBase . '.txt');

if (empty(trim($ocrText))) {
    jsonResponse(['success'=>false,'error'=>'OCR không trích xuất được văn bản từ file này. Thử file rõ hơn.']);
}

// Lưu văn bản mẫu — thêm vào file có sẵn (nếu đã upload mẫu trước)
$tplFile    = TEMPLATE_DIR . $code . '.txt';
$separator  = "\n\n=== MẪU " . date('d/m/Y H:i') . " ===\n\n";
$existing   = file_exists($tplFile) ? file_get_contents($tplFile) : '';
$newContent = $existing . $separator . trim($ocrText);
file_put_contents($tplFile, $newContent);

jsonResponse([
    'success'    => true,
    'code'       => $code,
    'chars'      => mb_strlen($ocrText),
    'preview'    => mb_substr($ocrText, 0, 300),
    'total_size' => mb_strlen($newContent),
]);
