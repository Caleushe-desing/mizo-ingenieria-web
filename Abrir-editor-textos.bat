@echo off
setlocal
cd /d "%~dp0"
echo Abriendo el editor de textos de Mizo...
echo.
echo Deja esta ventana abierta mientras editas.
echo Cierra la ventana para detener el editor.
echo.
node tools\content-editor\server.mjs
if errorlevel 1 (
  echo.
  echo No se pudo iniciar. Verifica que Node.js este instalado.
  pause
)
