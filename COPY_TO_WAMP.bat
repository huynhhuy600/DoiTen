@echo off
chcp 65001 > nul
echo ========================================
echo  Copy DoiTen len WAMP www folder
echo ========================================
echo.

set SRC=C:\Users\huynh\Desktop\2959\DoiTen
set DST=C:\wamp64\www\DoiTen

if not exist "%DST%" mkdir "%DST%"
if not exist "%DST%\uploads" mkdir "%DST%\uploads"
if not exist "%DST%\temp" mkdir "%DST%\temp"
if not exist "%DST%\output" mkdir "%DST%\output"
if not exist "%DST%\api" mkdir "%DST%\api"
if not exist "%DST%\config" mkdir "%DST%\config"
if not exist "%DST%\css" mkdir "%DST%\css"
if not exist "%DST%\js" mkdir "%DST%\js"
if not exist "%DST%\training_data" mkdir "%DST%\training_data"

xcopy "%SRC%\*.php" "%DST%\" /Y /Q
xcopy "%SRC%\*.json" "%DST%\" /Y /Q
xcopy "%SRC%\*.bat" "%DST%\" /Y /Q
xcopy "%SRC%\.htaccess" "%DST%\" /Y /Q 2>nul
xcopy "%SRC%\api\*.*" "%DST%\api\" /Y /Q
xcopy "%SRC%\config\*.*" "%DST%\config\" /Y /Q
xcopy "%SRC%\css\*.*" "%DST%\css\" /Y /Q
xcopy "%SRC%\js\*.*" "%DST%\js\" /Y /Q 2>nul
xcopy "%SRC%\training_data\*.*" "%DST%\training_data\" /Y /Q /E /I 2>nul


echo.
echo [XONG] Mo trinh duyet kiem tra...
echo.
start "" "http://localhost/DoiTen/setup.php"
pause
