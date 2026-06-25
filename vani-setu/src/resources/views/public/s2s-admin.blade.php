@php
    $s2sAssetVersion = static function (string $path): string {
        $fullPath = public_path($path);

        return is_file($fullPath) ? '?v='.filemtime($fullPath) : '';
    };
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vanisetu · वाणीसेतु · Speech to Speech</title>
    <link rel="stylesheet" href="/vanisetu-speech-to-speech/styles.css{{ $s2sAssetVersion('vanisetu-speech-to-speech/styles.css') }}">
    <script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
</head>
<body>
    <div id="root"></div>

    <script type="text/babel" src="/vanisetu-speech-to-speech/tweaks-panel.jsx{{ $s2sAssetVersion('vanisetu-speech-to-speech/tweaks-panel.jsx') }}"></script>
    <script type="text/babel" src="/vanisetu-speech-to-speech/sarvam.jsx{{ $s2sAssetVersion('vanisetu-speech-to-speech/sarvam.jsx') }}"></script>
    <script type="text/babel" src="/vanisetu-speech-to-speech/recorder.jsx{{ $s2sAssetVersion('vanisetu-speech-to-speech/recorder.jsx') }}"></script>
    <script type="text/babel" src="/vanisetu-speech-to-speech/glossary.jsx{{ $s2sAssetVersion('vanisetu-speech-to-speech/glossary.jsx') }}"></script>
    <script type="text/babel" src="/vanisetu-speech-to-speech/components.jsx{{ $s2sAssetVersion('vanisetu-speech-to-speech/components.jsx') }}"></script>
    <script type="text/babel" src="/vanisetu-speech-to-speech/app.jsx{{ $s2sAssetVersion('vanisetu-speech-to-speech/app.jsx') }}"></script>
    <script type="text/babel">
        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<App />);
    </script>
</body>
</html>
