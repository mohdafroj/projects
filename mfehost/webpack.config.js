const { ModuleFederationPlugin } = require("webpack").container;
const HtmlWebpackPlugin = require("html-webpack-plugin");

module.exports = {
    entry: "./src/index.js",
    output: {
        publicPath: "auto",
    },
    mode: "development",
    devServer: {
        port: 3000,
    },
    module: {
        rules: [
            {
                test: /\.jsx?$/,
                loader: "babel-loader",
                exclude: /node_modules/,
                options: {
                    presets: ["@babel/preset-react"],
                },
            },
            {
                test: /\.css$/i,
                use: ["style-loader", "css-loader", "postcss-loader"],
            },
        ],
    },
    plugins: [
        new ModuleFederationPlugin({
            name: "host",
            remotes: {
                // Add remotes here, e.g., remoteHeader: "remoteHeader@http://localhost:3001/remoteEntry.js",
                remoteHeader: "HeaderApp@http://localhost:3001/remoteEntry.js"
            },
            shared: {
                react: {
                    singleton: true,
                    requiredVersion: "^18.3.1",
                },
                "react-dom": {
                    singleton: true,
                    requiredVersion: "^18.3.1",
                },
            },
        }),
        new HtmlWebpackPlugin({
            template: "./public/index.html",
        }),
    ],
};