import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => ({
  plugins: [react()],
  base: mode === 'production' ? '/absen/tools/payroll_sim/' : '/',
  server: {
    port: 5174,
    proxy: {
      '/api': {
        target: 'http://localhost:3011',
        changeOrigin: true,
      },
    },
  },
}));
