# Editor de textos Mizo (Windows)

Panel local offline para editar los textos fijos del sitio. Lee y escribe `src/data/siteContent.json`.

## Cómo abrirlo (recomendado)

1. Ten **Node.js LTS** instalado desde https://nodejs.org
2. Haz **doble clic** en `Abrir-editor-textos.bat` en la raíz del proyecto.
3. Se abre el navegador en `http://127.0.0.1:4789/`.
4. Edita y pulsa **Guardar cambios**.
5. Deja abierta la ventana negra mientras editas. Si algo falla, la ventana **no se cierra sola** y muestra el error.

El `.bat` llama a `node.exe` directamente (no usa `npm.ps1`), así evita el error de PowerShell *“la ejecución de scripts está deshabilitada”*.

Alternativa desde **CMD** (no PowerShell):

```bat
node tools\content-editor\server.mjs
```

## Flujo con el sitio

- Con `npm run dev` activo, Astro recarga al guardar el JSON.
- El archivo queda en el repo: Cursor lo ve sin gastar tokens de IA en reescribir textos.
- Para publicar en mizo.cl: commit + push como siempre.
