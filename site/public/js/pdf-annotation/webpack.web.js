var webpack = require('webpack');

module.exports = {
  entry: './web/index.js',

  output: {
    filename: 'index.js',
    path: 'web/__build__',
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
  }
};

