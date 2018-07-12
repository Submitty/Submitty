var webpack = require('webpack');
var fileName = 'pdf-annotate';
var plugins = [];

if (process.env.MINIFY) {
  fileName += '.min'
  plugins.push(
    new webpack.optimize.UglifyJsPlugin()
  );
}

module.exports = {
  devtool: 'source-map',
  plugins: plugins,
  entry: './index.js',
  output: {
    filename: 'dist/' + fileName + '.js',
    library: 'PDFAnnotate',
    libraryTarget: 'umd'
  },
  module: {
    loaders: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        loader: 'babel-loader',
        query: {
          presets: ['es2015'],
          plugins: ['add-module-exports']
        }
      }
    ]
  }
};
