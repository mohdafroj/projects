<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title !== '' ? $title : config('app.name') }}</title>
</head>
<body>
    @if ($title !== '')
        <h1>{{ $title }}</h1>
    @endif

    {!! nl2br(e($contentText)) !!}
</body>
</html>
