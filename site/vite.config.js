// vite.config.js
export default {
  build: {
    outdir: path.join(__dirname, 'public', 'mjs'),
    minify: true,
    sourcemap: true,
    rollupOptions: {
      // Any additional Rollup options you might need
    },
  },
};