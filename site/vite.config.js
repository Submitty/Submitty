console.log("hisss");
export default {
  build: {
    outDir: "public/js",
    

    rollupOptions: {
      input: "vite-ts/hello_world.ts"
    },
  }
};