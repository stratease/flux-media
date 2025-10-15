const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');

module.exports = {
  entry: {
    admin: './assets/js/src/admin/index.js',
    attachment: './assets/js/src/admin/attachment.js',
  },
  output: {
    path: path.resolve(__dirname, 'assets/js/dist'),
    filename: '[name].bundle.js',
    clean: true,
  },
  resolve: {
    extensions: ['.js'],
    alias: {
      '@flux-media': path.resolve(__dirname, 'assets/js/src'),
    },
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: [
          /node_modules/,
          path.resolve(__dirname, 'assets/js/src/admin/attachment.js'),
        ],
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react'],
            plugins: [
              [
                'module-resolver',
                {
                  root: ['./assets/js/src'],
                  alias: {
                    '@flux-media': './assets/js/src',
                  },
                },
              ],
            ],
          },
        },
      },
      {
        test: /\.js$/,
        exclude: /node_modules/,
        include: [
          path.resolve(__dirname, 'assets/js/src/admin/attachment.js'),
        ],
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env'],
            plugins: [
              [
                'module-resolver',
                {
                  root: ['./assets/js/src'],
                  alias: {
                    '@flux-media': './assets/js/src',
                  },
                },
              ],
            ],
          },
        },
      },
      {
        test: /\.css$/i,
        use: ['style-loader', 'css-loader'],
      },
    ],
  },
  plugins: [
    new HtmlWebpackPlugin({
      template: './assets/js/src/admin/index.html',
      filename: 'admin.html',
      chunks: ['admin'],
    }),
  ],
  devServer: {
    static: {
      directory: path.join(__dirname, 'assets/js/dist'),
    },
    compress: true,
    port: 3000,
    hot: true,
  },
  externals: {
    // WordPress globals
    'wp': 'wp',
    'jquery': 'jQuery',
    '@wordpress/components': 'wp.components',
    '@wordpress/data': 'wp.data',
    '@wordpress/element': 'wp.element',
    '@wordpress/hooks': 'wp.hooks',
    '@wordpress/i18n': 'wp.i18n',
    '@wordpress/notices': 'wp.notices',
  },
};
