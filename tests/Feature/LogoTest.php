<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The logo (23 Oct 2026): a Kiganda shield and spears, in the header, the browser tab, the phone icon and link previews. */
class LogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_header_tab_icon_and_link_preview_use_the_logo(): void
    {
        $this->withoutVite();
        $this->seedAutomaticCourse();
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('viewBox="0 0 120 132"', $html, 'the shield in the header');
        $this->assertStringContainsString('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', $html);
        $this->assertStringContainsString('href="/app-icons/apple-touch-icon-shield.png"', $html);
        $this->assertStringContainsString('content="'.url('/images/logo-share.png').'"', $html, 'shared links show the logo');

        foreach (['favicon.ico', 'favicon.svg', 'images/logo-share.png', 'app-icons/apple-touch-icon-shield.png'] as $file) {
            $this->assertFileExists(public_path($file));
            $this->assertGreaterThan(200, filesize(public_path($file)), "{$file} is not empty");
        }
    }

    public function test_the_installed_app_uses_the_new_icons(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        foreach ($manifest['icons'] as $icon) {
            $this->assertStringContainsString('-shield.png', $icon['src']);
            [$w, $h] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], "{$w}x{$h}");
        }
        $sw = file_get_contents(public_path('sw.js'));
        preg_match_all("#'(/app-icons/[^']+)'#", $sw, $m);
        foreach (array_unique($m[1]) as $src) {
            $this->assertFileExists(public_path(ltrim($src, '/')), "{$src}, used by the service worker");
        }
        $this->assertStringNotContainsString("/app-icons/icon-192.png'", $sw, 'the old drum icons are gone');
    }
}
