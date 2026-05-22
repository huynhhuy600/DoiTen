<?php
require_once __DIR__ . '/config/settings.php';
$pdfPath = __DIR__ . '/test.pdf';
if (file_exists($pdfPath)) {
    $cmd = sprintf('"%s" -q -dNODISPLAY -c "(%s) (r) file runpdfbegin pdfpagecount = quit"', GS_PATH, str_replace('\\', '/', $pdfPath));
    $out = shell_exec($cmd);
    echo "PAGES: " . trim($out);
} else {
    echo "NO TEST PDF";
}
