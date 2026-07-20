<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorial_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tutorial_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutorial_category_id')
                ->constrained('tutorial_categories')
                ->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('path', 2048);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tutorial_category_id', 'sort_order']);
        });

        $this->seedFromJson();
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorial_videos');
        Schema::dropIfExists('tutorial_categories');
    }

    private function seedFromJson(): void
    {
        $path = app_path('Http/Controllers/Data/tutorial.json');

        if (! is_readable($path)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || $decoded === []) {
            return;
        }

        $labels = [
            'dashboard' => 'Dashboard',
            'orders' => 'Orders',
            'missingOrders' => 'Missing Orders',
            'blackList' => 'Blacklist',
            'fraudCheck' => 'Fraud Check',
            'license' => 'License',
            'smsConfig' => 'SMS Config',
            'sendSms' => 'Send SMS',
            'integration' => 'Integration',
            'courier' => 'Courier',
            'customStatus' => 'Custom Status',
            'smsRecharge' => 'SMS Recharge',
            'marketingTools' => 'Marketing Tools',
        ];

        $now = now();
        $sort = 0;

        foreach ($decoded as $key => $videos) {
            if (! is_string($key) || $key === '' || ! is_array($videos)) {
                continue;
            }

            $categoryId = DB::table('tutorial_categories')->insertGetId([
                'key' => $key,
                'label' => $labels[$key] ?? $this->labelFromKey($key),
                'sort_order' => $sort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $videoSort = 0;
            $rows = [];

            foreach ($videos as $video) {
                if (! is_array($video) || empty($video['path'])) {
                    continue;
                }

                $rows[] = [
                    'tutorial_category_id' => $categoryId,
                    'title' => isset($video['title']) ? (string) $video['title'] : '',
                    'path' => (string) $video['path'],
                    'sort_order' => $videoSort++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('tutorial_videos')->insert($rows);
            }
        }
    }

    private function labelFromKey(string $key): string
    {
        $spaced = preg_replace('/(?<!^)([A-Z])/', ' $1', $key) ?? $key;

        return ucwords(str_replace(['_', '-'], ' ', $spaced));
    }
};
