import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Dev: proxy API ke server produksi (atau set VITE_PROXY_TARGET ke lokal).
// Prod: API same-origin di /absen/temuan + /absen/api; aset di /absen/tools/temuan/.
export default defineConfig(({ mode }) => ({
  plugins: [react()],
  base: mode === 'production' ? '/absen/tools/temuan/' : '/',
  server: {
    port: 5177,
    proxy: {
      '/absen/temuan': {
        target: process.env.VITE_PROXY_TARGET || 'https://tiffany.my.id',
        changeOrigin: true,
        secure: true,
      },
      '/absen/api': {
        target: process.env.VITE_PROXY_TARGET || 'https://tiffany.my.id',
        changeOrigin: true,
        secure: true,
      },
    },
  },
}));
