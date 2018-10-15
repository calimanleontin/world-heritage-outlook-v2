const path = require('path');
const webpack = require('webpack');

module.exports = {
  entry: './src/js/diff-prototype.js',
  output: {
    filename: 'diff-prototype.js',
    path: path.resolve(__dirname, 'js')
  },
  module: {
    rules: [{
      test: /\.(scss)$/,
      use: [{
        // Adds CSS to the DOM by injecting a `<style>` tag
        loader: 'style-loader'
      }, {
        // Interprets `@import` and `url()` like `import/require()` and will resolve them
        loader: 'css-loader'
      }, {
        // Loader for webpack to process CSS with PostCSS
        loader: 'postcss-loader',
        options: {
          plugins: function() {
            return [
              require('autoprefixer')
            ];
          }
        }
      }, {
        // Loads a SASS/SCSS file and compiles it to CSS
        loader: 'sass-loader',
        options: {
          includePaths: [
            path.resolve(__dirname, "../scss/includePath")
          ]
        }
      }]
    }, {
      test: /\.woff2?$|\.ttf$|\.eot$|\.svg$/,
      use: [{
        loader: "file-loader",
        options: {
          emitFile: false
        }
      }]
    }]
  }
};
