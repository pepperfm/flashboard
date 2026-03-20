import { resolve } from 'node:path';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import ui from '@nuxt/ui/vite';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/flashboard.ts'],
      refresh: true,
    }),
    vue(),
    ui(),
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/js'),
    },
  },
});
