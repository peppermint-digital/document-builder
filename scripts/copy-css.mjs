/**
 * Bundles the GrapesJS base theme and our overrides into a single
 * `dist/styles.css`, so consumers only ever import one stylesheet.
 *
 * Copying only the overrides is what broke the editor in production: without
 * the base theme GrapesJS renders as a handful of unstyled icons and a canvas
 * collapsed to a few pixels. The host imports one file and must get everything.
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const grapesCss = require.resolve('grapesjs/dist/css/grapes.min.css');
const overridesCss = resolve(root, 'src/styles.css');
const target = resolve(root, 'dist/styles.css');

mkdirSync(dirname(target), { recursive: true });

writeFileSync(
    target,
    [
        '/* GrapesJS base theme (BSD-3-Clause, https://github.com/GrapesJS/grapesjs) */',
        readFileSync(grapesCss, 'utf8'),
        '',
        '/* @peppermint-digital/document-builder overrides */',
        readFileSync(overridesCss, 'utf8'),
    ].join('\n'),
    'utf8',
);

console.log('dist/styles.css written (GrapesJS base theme + overrides)');
