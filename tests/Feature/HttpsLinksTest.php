<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Behind a tunnel or proxy the app sees http; with an https APP_URL its links must still be https. */
class HttpsLinksTest extends TestCase
{
    public function test_links_follow_an_https_app_url(): void
    {
        $this->assertStringStartsWith('http://', route('blog.index'), 'http APP_URL: unchanged');

        config(['app.url' => 'https://example.trycloudflare.com']);
        (new AppServiceProvider($this->app))->boot();
        $this->assertStringStartsWith('https://', route('blog.index'));
        $this->assertStringStartsWith('https://', asset('build/manifest.json'));

        URL::forceScheme(null);
    }

    public function test_a_page_behind_an_https_proxy_knows_it_is_secure(): void
    {
        $this->get('/up', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'luganda.onrender.com'])->assertOk();
        $this->assertTrue(request()->secure(), 'X-Forwarded-Proto from Render is trusted');
        $this->assertSame('luganda.onrender.com', request()->getHost());
    }
}
