export default {
  build: {
    outDir: "public/vite-js",
    minify: true,
    sourcemap: true,
    rollupOptions: {
      input: "vite-ts/hello_world.ts"
    },
  },
  publicDir: 'public/vite-public/'
};