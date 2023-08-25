
export default {
  root: "vite-ts",

  build: {
    outDir: "public/vite_js",
    rollupOptions: {
      input: "vite-ts/hello_world.ts"
    },

    publicDir: 'public/vite-public'
  },
};