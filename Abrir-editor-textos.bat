@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title Mizo - Editor de textos

echo ========================================
echo   Mizo - Editor de textos local
echo ========================================
echo.
echo Carpeta: %CD%
echo.

rem --- Buscar Node.js (sin pasar por npm.ps1 de PowerShell) ---
set "NODE_EXE="

where node >nul 2>&1
if not errorlevel 1 (
  for /f "delims=" %%I in ('where node 2^>nul') do (
    set "NODE_EXE=%%I"
    goto :node_found
  )
)

if exist "%ProgramFiles%\nodejs\node.exe" (
  set "NODE_EXE=%ProgramFiles%\nodejs\node.exe"
  goto :node_found
)
if exist "%ProgramFiles(x86)%\nodejs\node.exe" (
  set "NODE_EXE=%ProgramFiles(x86)%\nodejs\node.exe"
  goto :node_found
)
if exist "%LocalAppData%\Programs\node\node.exe" (
  set "NODE_EXE=%LocalAppData%\Programs\node\node.exe"
  goto :node_found
)

echo [ERROR] No se encontro Node.js en este PC.
echo.
echo El editor solo necesita Node.js ^(no usa npm ni paquetes extra^).
echo 1^) Instala la version LTS desde https://nodejs.org
echo 2^) Cierra y vuelve a abrir esta ventana ^(o reinicia el PC^).
echo 3^) Haz doble clic otra vez en Abrir-editor-textos.bat
echo.
echo Presione una tecla para cerrar...
pause >nul
exit /b 1

:node_found
echo Node encontrado:
"%NODE_EXE%" -v
if errorlevel 1 (
  echo.
  echo [ERROR] Node.exe no responde. Reinstala Node.js LTS.
  echo.
  echo Presione una tecla para cerrar...
  pause >nul
  exit /b 1
)
echo.

rem --- Archivos del editor ---
if not exist "tools\content-editor\server.mjs" (
  echo [ERROR] Falta tools\content-editor\server.mjs
  echo Abre este .bat desde la raiz del proyecto mizo-ingenieria-web.
  echo.
  echo Presione una tecla para cerrar...
  pause >nul
  exit /b 1
)

if not exist "tools\content-editor\public\index.html" (
  echo [ERROR] Falta tools\content-editor\public\index.html
  echo.
  echo Presione una tecla para cerrar...
  pause >nul
  exit /b 1
)

if not exist "src\data\siteContent.json" (
  echo [ERROR] Falta src\data\siteContent.json
  echo.
  echo Presione una tecla para cerrar...
  pause >nul
  exit /b 1
)

rem El editor usa solo modulos nativos de Node: no hace falta npm install.
echo Comprobacion OK. No se requieren paquetes npm adicionales.
echo.
echo Deja esta ventana abierta mientras editas.
echo Se abrira el navegador en http://127.0.0.1:4789/
echo.
echo Para detener el editor: cierra esta ventana o pulsa Ctrl+C.
echo.
echo ----------------------------------------

"%NODE_EXE%" "tools\content-editor\server.mjs"
set "ERR=%ERRORLEVEL%"

echo.
echo ----------------------------------------
if not "%ERR%"=="0" (
  echo [ERROR] El editor se detuvo con codigo %ERR%.
  echo Revisa el mensaje de arriba. Si el puerto 4789 esta ocupado,
  echo cierra la otra ventana del editor o reinicia el PC.
) else (
  echo El editor se cerro correctamente.
)
echo.
echo Presione una tecla para cerrar...
pause >nul
exit /b %ERR%
