import { defineConfig } from "vitest/config";

export default defineConfig({
  resolve: {
    alias: {
      "@": "/resources/js",
    },
  },
  test: {
    environment: "node",
  },
});
