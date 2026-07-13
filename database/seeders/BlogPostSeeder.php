<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use App\Support\BlogHtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds 20 SEO marketing blog posts for WooEasyLife (BD COD sellers).
 *
 * Safe to re-run: updateOrCreate by slug.
 *
 * Usage:
 *   php artisan db:seed --class=BlogPostSeeder
 *   Admin → Database Migrations → Seed SEO blogs
 */
class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->value('id');

        $posts = require __DIR__.'/data/blog_posts.php';
        $baseDate = Carbon::parse('2026-06-01', 'Asia/Dhaka')->startOfDay();

        foreach ($posts as $index => $post) {
            BlogPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'title' => $post['title'],
                    'locale' => $post['locale'],
                    'status' => 'published',
                    'excerpt' => $post['excerpt'],
                    'meta_title' => $post['meta_title'],
                    'meta_description' => $post['meta_description'],
                    'focus_keyword' => $post['focus_keyword'],
                    'robots' => 'index,follow',
                    'author_name' => 'WooEasyLife',
                    'body_html' => BlogHtmlSanitizer::sanitize($post['body_html']),
                    'published_at' => $baseDate->copy()->addDays($index)->setTime(9, 0),
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ]
            );
        }

        $this->command?->info('Seeded '.count($posts).' SEO blog posts.');
    }
}
