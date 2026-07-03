import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Dev: set VITE_API_BASE in .env.local (mis. ke situs live yg di-tunnel) atau
// pakai proxy di bawah. Prod: API same-origin di /absen/payroll_import.
export default defineConfig(({ mode }) => ({
  plugins: [react()],
  base: mode === 'production' ? '/absen/tools/payroll_importer/' : '/',
  server: {
    port: 5175,
    proxy: {
      '/absen/payroll_import': {
        target: process.env.VITE_PROXY_TARGET || 'https://tiffany.my.id',
        changeOrigin: true,
        secure: true,
      },
    },
  },
}));
