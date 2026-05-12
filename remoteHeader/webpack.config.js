const HtmlWebpackPlugin = require("html-webpack-plugin");
const { ModuleFederationPlugin } = require("webpack").container;
const path = require("path");
const deps = require("./package.json").dependencies;

module.exports = {
    entry: "./src/index",
    mode: "development",
    output: {
        path: path.resolve(__dirname, "dist"),
        filename: "[name].[contenthash].js",
        publicPath: process.env.NODE_ENV === "production" ? "/" : "auto",
        chunkFilename: "[name].[contenthash].js",
        clean: true,
    },
    resolve: {
        extensions: [".tsx", ".ts", ".jsx", ".js"],
        alias: {
            "@": path.resolve(__dirname, "src/"),
        },
    },
    devServer: {
        port: 3001,
        historyApiFallback: true,
        headers: {
            "Access-Control-Allow-Origin": "*",
        },
    },
    cache: {
        type: "filesystem",
    },
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
