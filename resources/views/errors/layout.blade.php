{{-- Friendly error pages. Standalone on purpose: no database, no build files, so they show even when things are broken. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #fbf6ec; color: #3b1f12;
               font: 16px/1.5 'Instrument Sans', system-ui, -apple-system, 'Segoe UI', sans-serif; }
        main { max-width: 28rem; margin: 1rem; padding: 2rem 1.5rem; text-align: center; background: #fff;
               border: 2px solid #efd9c8; border-radius: 1.5rem; box-shadow: 0 4px 0 #efd9c8; }
        svg { width: 7rem; height: 7rem; }
        h1 { margin: .75rem 0 .25rem; font-size: 1.75rem; line-height: 1.2; }
        p { margin: .5rem 0 0; color: #5c2f1b; }
        .code { font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #a05530; }
        a.btn { display: inline-block; margin-top: 1.5rem; padding: .7rem 1.4rem; border-radius: 9999px; background: #8b4a2b;
                color: #fff; font-weight: 700; text-decoration: none; box-shadow: 0 4px 0 #5c2f1b; }
        a.btn:focus-visible { outline: 3px solid #f4cf6b; outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <x-art.ngoma :mood="$mood ?? 'sad'" />
        <p class="code">Error {{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a class="btn" href="{{ $link ?? url('/') }}">{{ $button ?? 'Back to the start' }}</a>
    </main>
</body>
</html>
