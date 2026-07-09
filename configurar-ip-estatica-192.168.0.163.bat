@echo off
setlocal

net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Solicitando permisos de administrador...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

echo Configurando IP estatica para Wi-Fi...
netsh interface ipv4 set address name="Wi-Fi" static 192.168.0.163 255.255.255.0 192.168.0.1
netsh interface ipv4 set dns name="Wi-Fi" static 190.104.12.42 primary
netsh interface ipv4 add dns name="Wi-Fi" 200.73.96.146 index=2

echo.
echo Listo. La URL de la aplicacion sera:
echo http://192.168.0.163:8011
echo.
pause
