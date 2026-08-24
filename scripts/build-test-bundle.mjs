/**
 * Buendelt die pruefbaren Teile des Kerns zu einer Datei, die Node laden kann.
 *
 * Node 26 entfernt Typen selbst, scheitert aber an erweiterungslosen Importen
 * (`from './defaults'`) — der Quelltext benutzt sie ueberall, und ihm dafuer
 * Endungen anzuhaengen hiesse, die Codebasis nach dem Testlaeufer zu formen.
 * Also gebuendelt, so wie der Pruefstand im Wiki es macht.
 *
 * Bewusst NICHT `src/core/index.ts`: das zoege GrapesJS mit hinein, eine
 * Browser-Bibliothek, die in Node ueber `window` stolpert. Geprueft wird die
 * Geometrie, und die kennt keinen Browser.
 */
import { build } from 'esbuild';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const wurzel = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const ziel = resolve(wurzel, 'tests/js/.bundle');

mkdirSync(ziel, { recursive: true });

// Ein Eintrittspunkt, der genau das oeffnet, was die Tests brauchen.
const eintritt = resolve(ziel, 'eintritt.ts');
writeFileSync(
    eintritt,
    "export * from '../../../src/core/defaults';\nexport * from '../../../src/core/presets';\nexport * from '../../../src/core/theme';\n",
);

await build({
    entryPoints: [eintritt],
    outfile: resolve(ziel, 'kern.mjs'),
    bundle: true,
    format: 'esm',
    platform: 'node',
    target: 'node22',
    logLevel: 'warning',
});
