import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import checker from 'vite-plugin-checker'

export default defineConfig({
  plugins: [
    laravel({
      // input: ['resources/css/app.css', 'resources/js/app.js'],
      input: ['resources/css/app.css', 'resources/js/app.ts'],
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    checker({
      vueTsc: true,
      // オーバーレイを表示しない場合はfalse
      overlay: true,
      
    })
  ],
  server: {
    host: true,
    hmr: {
      host: 'localhost'
    },
    // ホットリロード設定
    watch: {
        usePolling: true
    }
  },
  resolve: {
    alias: {
      '@': '/resources/js',
    },
  },
});
