{{-- Shown by the service worker (public/sw.js) when there's no signal and the page isn't saved on this phone.
     Standalone (no account data, no database), so the same copy works for everyone. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8b4a2b">
    <title>Offline · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #fbf6ec; color: #3b1f12;
               font: 16px/1.5 'Instrument Sans', system-ui, -apple-system, 'Segoe UI', sans-serif; }
        main { max-width: 28rem; margin: 1rem; padding: 2rem 1.5rem; text-align: center; background: #fff;
               border: 2px solid #efd9c8; border-radius: 1.5rem; box-shadow: 0 4px 0 #efd9c8; }
        svg { width: 7rem; height: 7rem; }
        h1 { margin: .75rem 0 .25rem; font-size: 1.6rem; line-height: 1.2; }
        p { margin: .5rem 0 0; color: #5c2f1b; }
        ul { list-style: none; margin: 1rem 0 0; padding: 0; text-align: left; }
        li a { display: block; margin-top: .5rem; padding: .75rem 1rem; border-radius: 1rem; background: #f8eee6; color: #3b1f12;
               font-weight: 700; text-decoration: none; }
        li a:focus-visible, button:focus-visible { outline: 3px solid #f4cf6b; outline-offset: 2px; }
        button { margin-top: 1.25rem; padding: .7rem 1.4rem; border: 0; border-radius: 9999px; background: #8b4a2b; color: #fff;
                 font: inherit; font-weight: 700; box-shadow: 0 4px 0 #5c2f1b; cursor: pointer; }
        .small { font-size: .85rem; color: #a05530; }
    </style>
</head>
<body>
    <main>
        <x-art.ngoma mood="happy" />
        <h1>You're offline</h1>
        <p>This page isn't saved on your phone yet. Lessons you've already opened still work, and your progress is kept until you're back online.</p>
        <ul id="saved" aria-label="Saved on this phone"></ul>
        <button type="button" onclick="location.reload()">Try again</button>
        <p class="small">Tip: on the course page, "Save for offline" keeps a whole stage on your phone.</p>
    </main>
    <script>
        // List the lesson pages already saved on this phone (by the service worker).
        (async () => {
            if (!('caches' in window)) return;
            const keys = await (await caches.open('pages')).keys();
            const lessons = keys.map((r) => new URL(r.url)).filter((u) => /^\/learn\/[^/]+$/.test(u.pathname) || u.pathname === '/learn' || u.pathname === '/review');
            let titles = {};
            try { titles = JSON.parse(localStorage.getItem('lug.titles') || '{}'); } catch (e) {}
            const name = (u) => u.pathname === '/learn' ? 'Your course path' : u.pathname === '/review' ? 'Review'
                : titles[u.pathname] || decodeURIComponent(u.pathname.split('/').pop()).replace(/-/g, ' ').replace(/^./, (c) => c.toUpperCase());
            document.getElementById('saved').innerHTML = lessons.sort((a, b) => a.pathname.length - b.pathname.length)
                .map((u) => `<li><a href="${u.pathname}">${name(u).replace(/[<>&"]/g, '')}</a></li>`).join('');
        })();
    </script>
</body>
</html>
