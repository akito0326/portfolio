import { defineConfig } from "vite";
import { fileURLToPath } from "node:url";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";

// scripts/dev.mjs が API_PORT を指定した場合、proxy 先も合わせる（既定は 8080）
const apiPort = process.env.API_PORT ?? "8080";

// 本番公開では管理画面(admin.html)をビルドに含めない。
// ローカル開発では vite dev が admin.html を直接配信するため、この設定の影響を受けない。
// 管理画面込みでビルドしたい場合のみ VITE_INCLUDE_ADMIN=1 を付けて実行する。
const includeAdmin = process.env.VITE_INCLUDE_ADMIN === "1";

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      // 開発時は /api を PHP API へ転送する。
      // ブラウザからは同一オリジン扱いになるため CORS 設定は不要。
      "/api": {
        target: `http://127.0.0.1:${apiPort}`,
        changeOrigin: true,
      },
    },
  },
  build: {
    rollupOptions: {
      input: {
        main: fileURLToPath(new URL("./index.html", import.meta.url)),
        ...(includeAdmin
          ? { admin: fileURLToPath(new URL("./admin.html", import.meta.url)) }
          : {}),
      },
    },
  },
});