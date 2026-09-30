# Editor de textos Mizo (Windows)

Panel local offline para editar los textos fijos del sitio. Lee y escribe `src/data/siteContent.json`.

## Cómo abrirlo

1. Asegúrate de tener **Node.js** instalado (`node -v` en una terminal).
2. Doble clic en `Abrir-editor-textos.bat` en la raíz del proyecto  
   **o** ejecuta:

```bash
npm run content-editor
```

3. Se abre el navegador en `http://127.0.0.1:4789/`.
4. Edita los campos y pulsa **Guardar cambios**.
5. Deja la ventana de la terminal/BAT abierta mientras editas. Ciérrala para detener el editor.

## Flujo con el sitio

- Con `npm run dev` activo, Astro recarga al guardar el JSON.
- El archivo queda en el repo: Cursor lo ve sin gastar tokens de IA en reescribir textos.
- Para publicar en mizo.cl: commit + push como siempre.
