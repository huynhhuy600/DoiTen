<?php
// api/delete_template.php - Xóa file mẫu
ob_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/settings.php';
$code = preg_replace('/[^A-Za-z0-9_\-]/', '', $_POST['code'] ?? '');
if (!$code) jsonResponse(['success'=>false,'error'=>'Thiếu code']);
$tplFile = TEMPLATE_DIR . $code . '.txt';
if (file_exists($tplFile)) { unlink($tplFile); jsonResponse(['success'=>true]); }
jsonResponse(['success'=>false,'error'=>'File không tồn tại']);
