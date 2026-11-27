<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status ?? 500 }} — Error</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Segoe UI", system-ui, sans-serif;
            background: linear-gradient(160deg, #f3f6f4 0%, #e7eee9 45%, #dfe8e2 100%);
            color: #1a2e24;
        }
        main {
            width: min(28rem, calc(100% - 2rem));
            text-align: center;
        }
        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
            font-weight: 700;
        }
        p {
            margin: 0;
            line-height: 1.5;
            color: #3d5a4c;
        }
        code {
            display: inline-block;
            margin-top: 1.25rem;
            padding: 0.35rem 0.6rem;
            border-radius: 0.35rem;
            background: rgba(26, 46, 36, 0.08);
            font-size: 0.75rem;
            color: #2f4a3c;
        }
        a {
            display: inline-block;
            margin-top: 1.5rem;
            color: #0f6b3a;
            font-weight: 600;
            text-decoration: none;
        }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $message ?? 'Server error.' }}</h1>
        <p>Please try again. If the problem continues, contact support with the reference below.</p>
        @if (! empty($requestId))
            <code>Ref: {{ $requestId }}</code>
        @endif
        <div><a href="/">Back to home</a></div>
    </main>
</body>
</html>
