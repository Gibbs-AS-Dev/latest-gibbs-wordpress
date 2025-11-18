const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';
  
  return {
    entry: {
      components: './src/js/components.js',
      wallet: './src/js/Wallet/index.js',
      email_template: './src/js/EmailTemplates/index.js',
      react_modules: './src/js/ReactModules/index.js',
      styles: './src/css/style.scss'
    },
    output: {
      path: path.resolve(__dirname, 'assets'),
      filename: 'js/[name].js',
      clean: true
    },
    module: {
      rules: [
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                ['@babel/preset-env', {
                  targets: {
                    browsers: ['> 1%', 'last 2 versions', 'not dead']
                  }
                }],
                ['@babel/preset-react', {
                  runtime: 'automatic'
                }]
              ]
            }
          }
        },
        {
          test: /\.s[ac]ss$/i,
          use: [
            MiniCssExtractPlugin.loader,
            {
              loader: 'css-loader',
              options: {
                sourceMap: !isProduction,
                modules: {
                  auto: true, // Enable CSS Modules for .module.scss files
                  localIdentName: '[name]__[local]--[hash:base64:5]'
                  //localIdentName: isProduction ? '[hash:base64:8]' : '[name]__[local]--[hash:base64:5]'
                }
              }
            },
            {
              loader: 'sass-loader',
              options: {
                sourceMap: !isProduction
              }
            }
          ]
        },
        {
          test: /\.css$/,
          use: [
            MiniCssExtractPlugin.loader,
            {
              loader: 'css-loader',
              options: {
                sourceMap: !isProduction
              }
            }
          ]
        }
      ]
    },
    plugins: [
      new MiniCssExtractPlugin({
        filename: 'css/[name].css'
      })
    ],
    externals: {
      'react': 'React',
      'react-dom': 'ReactDOM'
    },
    resolve: {
      extensions: ['.js', '.jsx', '.css', '.scss']
    },
    devtool: isProduction ? false : 'source-map',
    optimization: isProduction ? {
      minimize: true,
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            format: {
              comments: false,
            },
            compress: {
              drop_console: true,
              drop_debugger: true,
            },
          },
          extractComments: false,
        }),
        new CssMinimizerPlugin(),
      ],
    } : {},
  };
}; 