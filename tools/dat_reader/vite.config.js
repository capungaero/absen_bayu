import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Dev: set VITE_API_BASE di .env.local atau pakai proxy di bawah.
// Prod: API same-origin di /absen/dat_reader; aset di /absen/tools/dat_reader/.
export default defineConfig(({ mode }) => ({
  plugins: [react()],
  base: mode === 'production' ? '/absen/tools/dat_reader/' : '/',
  server: {
    port: 5176,
    proxy: {
      '/absen/dat_reader': {
        target: process.env.VITE_PROXY_TARGET || 'https://tiffany.my.id',
        changeOrigin: true,
        secure: true,
      },
    },
  },
}));
