import { defineConfig } from 'tsup';

export default defineConfig({
    entry: {
        'core/index': 'src/core/index.ts',
    },
    format: ['esm', 'cjs'],
    dts: true,
    clean: true,
    sourcemap: true,
    external: ['react', 'react-dom', 'vue', 'grapesjs'],
});
