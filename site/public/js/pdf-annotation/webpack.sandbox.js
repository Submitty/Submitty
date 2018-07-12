var fs = require('fs');
var path = require('path');
var webpack = require('webpack');
var SANDBOX_DIR = path.resolve(process.cwd(), 'sandbox');

function buildEntries() {
  return fs.readdirSync(SANDBOX_DIR).reduce(function (entries, dir) {
    if (dir === 'build' || dir === 'shared') {
      return entries;
    }

    var isDraft = dir.charAt(0) === '_';
    var isDirectory = fs.lstatSync(path.join(SANDBOX_DIR, dir)).isDirectory();

    if (!isDraft && isDirectory) {
      entries[dir] = path.join(SANDBOX_DIR, dir, 'index.js');
    }

    return entries;
  }, {});
}

module.exports = {

  entry: buildEntries(),

  output: {
    filename: '[name].js',
    chunkFilename: '[id].chunk.js',
    path: 'sandbox/__build__',
    publicPath: '/__build__/'
  },

  module: {
    loaders: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        loader: 'babel-loader',
        query: {
          presets: ['es2015']
        }
      }
    ]
  },
  
  plugins: [
    new webpack.optimize.CommonsChunkPlugin('shared.js')
  ]

};
