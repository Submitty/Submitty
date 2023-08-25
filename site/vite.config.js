
export default {
  root: "vite-ts",
  outDir: "public/vite_js",


  build: {    
    rollupOptions: {
      input: "vite-ts/hello_world.ts"
    },
  },

  publicDir: 'vite-public'
};