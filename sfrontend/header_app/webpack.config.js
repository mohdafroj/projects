const HtmlWebpackPlugin = require("html-webpack-plugin");
const { ModuleFederationPlugin } = require("webpack").container;
const path = require("path");
const deps = require("./package.json").dependencies;

module.exports = {
    entry: "./src/index",
    mode: "development",
    output: {
        path: path.resolve(__dirname, "dist"),
        filename: "[name].js",
        publicPath: "http://localhost:3001/",
        chunkFilename: "[name].js",
        clean: true,
        uniqueName: "header_app",
        hotUpdateGlobal: "webpackHotUpdateheader_app",
    },
    resolve: {
        extensions: [".tsx", ".ts", ".jsx", ".js"],
        alias: {
            "@": path.resolve(__dirname, "src/"),
        },
    },
    devServer: {
        port: 3001,
        host: "0.0.0.0",
        hot: false,
        liveReload: true,
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
            name: "remoteHeader",
            filename: "remoteEntry.js",
            exposes: {
                "./Header": "./src/Header",
            },
            shared: {
                react: {
                    singleton: true,
                    requiredVersion: deps.react,
                    eager: false,
                    strictVersion: false,
                },
                "react-dom": {
                    singleton: true,
                    requiredVersion: deps["react-dom"],
                    eager: false,
                    strictVersion: false,
                },
                "react-router-dom": {
                    singleton: true,
                    requiredVersion: deps["react-router-dom"],
                    eager: false,
                    strictVersion: false,
                },
            },
        }),
        new HtmlWebpackPlugin({
            template: "./public/index.html",
        }),
    ],
};
