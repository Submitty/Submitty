export default {
  build: {
    outDir: "public/vite-public/vite-js",

    rollupOptions: {
      input: "vite-ts/hello_world.ts"
    },
  },
  publicDir: 'public/vite-public/'
};