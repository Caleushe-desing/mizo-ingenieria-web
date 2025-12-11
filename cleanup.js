// cleanup.js

import { readdir, readFile, writeFile } from 'fs/promises';
import { resolve, join } from 'path';

const targetDir = resolve('src');
const fileExtensions = ['.astro', '.js', '.mjs'];
// Expresión regular para encontrar comentarios de paso, ej: // 129. 
// Opcionalmente, puedes buscar la línea completa: /^\s*\/\/\s*\d+\.\s*.*$/gm
const regex = /^\s*\/\/\s*\d+\.\s*.*$/gm; 
let filesCleaned = 0;

/**
 * Busca archivos coincidentes y aplica la limpieza de comentarios.
 * @param {string} dir El directorio a escanear.
 */
async function cleanFiles(dir) {
    try {
        const files = await readdir(dir, { withFileTypes: true });

        for (const file of files) {
            const fullPath = join(dir, file.name);

            if (file.isDirectory()) {
                // Si es un directorio, recursividad
                await cleanFiles(fullPath);
            } else if (fileExtensions.includes(file.name.toLowerCase().slice(file.name.lastIndexOf('.')))) {
                // Si es un archivo de código relevante
                const content = await readFile(fullPath, 'utf8');
                
                // Reemplazar los comentarios por una cadena vacía
                const newContent = content.replace(regex, '');

                if (newContent !== content) {
                    await writeFile(fullPath, newContent, 'utf8');
                    filesCleaned++;
                    console.log(`✅ Limpiado: ${file.name}`);
                }
            }
        }
    } catch (error) {
        console.error(`Error procesando directorio ${dir}:`, error);
    }
}

async function runCleanup() {
    console.log('--- Iniciando limpieza de comentarios de pasos ---');
    await cleanFiles(targetDir);
    console.log(`\n🎉 Limpieza completada. Archivos procesados: ${filesCleaned}.`);
    console.log('Recuerda revisar manualmente los archivos importantes antes de la producción.');
}

runCleanup();