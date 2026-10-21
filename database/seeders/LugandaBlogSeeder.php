<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

/**
 * Three starter posts for the blog, written in English, saved as DRAFTS for an admin to read,
 * edit and publish (Admin → Blog). Their Luganda ([[…]]) is only phrases already in the course.
 * Add-only: a post whose web address already exists is left alone, even if it was edited or deleted
 * and written again under the same address.
 *
 *   php artisan db:seed --class=LugandaBlogSeeder
 */
class LugandaBlogSeeder extends Seeder
{
    /** slug => [title, topic, summary] (the text is in database/seeders/data/blog/{slug}.md) */
    public const POSTS = [
        'why-learn-luganda' => ['Why learn Luganda?', 'culture',
            'Five good reasons to learn the language of Buganda: family, respect, everyday life in Kampala, culture, and how quickly it comes.'],
        'how-to-use-this-app' => ['How to use this app: a few minutes a day', 'learning',
            'The course path, daily review, tapping words, games and cowries, speaking practice and learning offline: how to get the most out of the app.'],
        'greetings-in-buganda' => ['Greetings in Buganda: more than hello', 'culture',
            'How people greet each other in the morning, in the afternoon and when they leave, and how to show respect to elders.'],
    ];

    public function run(): void
    {
        foreach (self::POSTS as $slug => [$title, $topic, $excerpt]) {
            $post = Post::firstOrCreate(['slug' => $slug], [
                'title' => $title, 'topic' => $topic, 'excerpt' => $excerpt,
                'body' => trim((string) file_get_contents(database_path("seeders/data/blog/{$slug}.md"))),
                'published_at' => null, // a draft: an admin reads it and publishes it
            ]);
            $this->command?->line($post->wasRecentlyCreated ? "  Draft post added: {$title}" : "  {$title}: already there, left as it is.");
        }
    }
}
