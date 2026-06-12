const HtmlWebpackPlugin = require("html-webpack-plugin");
const { ModuleFederationPlugin } = require("webpack").container;
const path = require("path");
const deps = require("./package.json").dependencies;

const remoteHeaderUrl = process.env.REMOTE_HEADER_URL || "http://localhost:3001";
const remoteIAMUrl = process.env.REMOTE_IAM_URL || "http://localhost:3002";

module.exports = {
  entry: "./src/bootstrap",
  mode: "development",
  output: {
    path: path.resolve(__dirname, "dist"),
    filename: "[name].js",
    publicPath: "/",
    chunkFilename: "[name].js",
    clean: true,
    uniqueName: "main_app",
    hotUpdateGlobal: "webpackHotUpdatemain_app",
  },
  resolve: {
    extensions: [".tsx", ".ts", ".jsx", ".js"],
    alias: {
      "@": path.resolve(__dirname, "src/"),
    },
  },
  devServer: {
    port: 3000,
    host: "0.0.0.0",
    historyApiFallback: true,
    allowedHosts: "all",
    headers: {
      "Access-Control-Allow-Origin": "*",
    },
    watchFiles: {
      paths: ['src/**/*', 'public/**/*'],
      options: {
        usePolling: true,
      },
    },
  },
  watchOptions: {
    poll: 1000,
    ignored: /node_modules/,
  },
  cache: false,
  module: {
    rules: [
      {
        test: /\.(ts|tsx|js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader",
          options: {
            presets: [
              "@babel/preset-env",
              "@babel/preset-react",
              "@babel/preset-typescript",
            ],
          },
        },
      },
      {
        test: /\.css$/i,
        use: ["style-loader", "css-loader"],
      },
    ],
  },
  plugins: [
    new ModuleFederationPlugin({
      name: "main_app",
      filename: "remoteEntry.js",
      remotes: {
        remoteHeader: `remoteHeader@${remoteHeaderUrl}/remoteEntry.js`,
        remoteIAM: `remoteIAM@${remoteIAMUrl}/remoteEntry.js`,
      },
      exposes: {
        "./App": "./src/App",
      },
      shared: {
        react: {
          singleton: true,
          requiredVersion: deps.react,
          eager: true,
          strictVersion: false,
        },
        "react-dom": {
          singleton: true,
          requiredVersion: deps["react-dom"],
          eager: true,
          strictVersion: false,
        },
        "react-router-dom": {
          singleton: true,
          requiredVersion: deps["react-router-dom"],
          eager: true,
          strictVersion: false,
        },
      },
    }),
    new HtmlWebpackPlugin({
      template: "./public/index.html",
    }),
  ],
};
