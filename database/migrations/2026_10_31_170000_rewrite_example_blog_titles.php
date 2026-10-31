<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_posts')) {
            return;
        }

        $disclaimer = '<p>এটি একটি উদাহরণ হিসাব, কোনো নির্দিষ্ট মার্চেন্টের ফল নয়।</p>';

        $this->retitle('steadfast-fraud-check-case-study', [
            'title' => 'SteadFast উদাহরণ — ১৮%→১২% হলে কত সাশ্রয়',
            'meta_title' => 'SteadFast উদাহরণ — ১৮%→১২% হলে কত সাশ্রয়',
            'meta_description' => 'উদাহরণ হিসাব: রিটার্ন ১৮% থেকে ১২% হলে কত সাশ্রয় হতে পারে। কোনো নির্দিষ্ট মার্চেন্টের ফল নয়।',
        ], $disclaimer);

        $this->retitle('reduce-return-rate-30-to-12-percent', [
            'title' => 'রিটার্ন রেট ৩০% থেকে ১২%-এ নামাতে কী করবেন — উদাহরণসহ ধাপ',
            'meta_title' => 'রিটার্ন রেট ৩০% থেকে ১২%-এ নামাতে কী করবেন',
            'meta_description' => 'উদাহরণসহ ধাপ: রিটার্ন রেট ৩০% থেকে ১২% ভাবার হিসাব। কোনো নির্দিষ্ট মার্চেন্টের ফল নয়।',
            'excerpt' => 'এটি একটি উদাহরণ হিসাব, কোনো নির্দিষ্ট মার্চেন্টের ফল নয়।',
        ], $disclaimer);

        $this->retitle('harano-order-ferot-anar-upay', [
            'title' => 'ই-কমার্সে হারানো অর্ডার ফেরত আনার উপায় — Missing Order Recovery গাইড',
            'meta_title' => 'ই-কমার্সে হারানো অর্ডার ফেরত আনার উপায়',
            'meta_description' => 'Missing Order Recovery দিয়ে হারানো অর্ডার ফেরত আনার গাইড। বিক্রি দ্বিগুণের দাবি নয়।',
        ], '', stripDoubleClaim: true);
    }

    public function down(): void
    {
        // Titles were corrected because the old wording over-claimed. Do not restore it.
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function retitle(string $slug, array $fields, string $disclaimer, bool $stripDoubleClaim = false): void
    {
        $post = DB::table('blog_posts')->where('slug', $slug)->first();
        if ($post === null) {
            return;
        }

        $body = (string) ($post->body_html ?? '');
        if ($stripDoubleClaim) {
            $body = self::stripDoubleClaim($body);
            foreach (['title', 'meta_title', 'meta_description', 'excerpt'] as $column) {
                if (! array_key_exists($column, $fields) && isset($post->{$column})) {
                    $cleaned = self::stripDoubleClaim((string) $post->{$column});
                    if ($cleaned !== (string) $post->{$column}) {
                        $fields[$column] = $cleaned;
                    }
                }
            }
        }

        if ($disclaimer !== '' && ! str_contains($body, 'কোনো নির্দিষ্ট মার্চেন্টের ফল নয়')) {
            $body = $disclaimer.$body;
        }

        $fields['body_html'] = $body;
        $fields['updated_at'] = now();

        DB::table('blog_posts')->where('id', $post->id)->update($fields);
    }

    private static function stripDoubleClaim(string $text): string
    {
        $text = str_replace('সেলস বাড়ান ২ গুণ!', '', $text);

        return trim((string) preg_replace('/[^<।\n]{0,180}২ গুণ[^<।\n]{0,180}[।!]?/u', '', $text));
    }
};
