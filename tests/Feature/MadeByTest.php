<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The home page says why the app is free, and who made it (ideas from Asụsụ Ọma, written fresh). */
class MadeByTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_says_why_it_is_free_and_who_made_it(): void
    {
        $this->withoutVite();
        $this->seedAutomaticCourse();

        $page = $this->get('/')->assertOk()
            ->assertSee("That's why this app is free", false)
            ->assertSee('Made by Kato James Kalemba.')
            ->assertSee('Who is behind this')
            ->assertSee('href="https://katojkalemba-5eb7e.web.app/"', false)
            ->assertSee('href="https://www.youtube.com/@webdesignerug"', false)
            ->assertSee('alt="Kato James Kalemba"', false);
        $this->assertSame(1, substr_count($page->getContent(), 'every lesson, game and recording'), 'said once, not twice');

        foreach (['kato-james-kalemba.webp', 'kato-james-kalemba.jpg'] as $file) {
            $this->assertFileExists(public_path('images/'.$file));
        }
    }
}
