import resolve from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import babel from '@rollup/plugin-babel';

export default {
  input: [
    'node_modules/pdfjs-dist/build/pdf.mjs',
    'node_modules/pdfjs-dist/build/pdf.worker.mjs',
    'node_modules/pdfjs-dist/build/pdf_viewer.mjs',
  ],
  output: {
    dir: 'public/vendor/pdfjs',
    format: 'es',
    sourcemap: true
  },
  plugins: [
    resolve(),
    commonjs(),
    babel({
      babelHelpers: 'bundled',
      presets: [['@babel/preset-env']]
    })
  ]
};
