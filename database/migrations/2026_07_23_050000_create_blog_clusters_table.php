<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_clusters', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label', 191);
            $table->json('seed_queries')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        $rows = [];
        foreach ($this->seedDefinitions() as $i => $def) {
            $rows[] = [
                'key' => $def['key'],
                'label' => $def['label'],
                'seed_queries' => json_encode(array_values($def['seed_queries']), JSON_UNESCAPED_UNICODE),
                'sort_order' => ($i + 1) * 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('blog_clusters')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_clusters');
    }

    /**
     * @return list<array{key: string, label: string, seed_queries: list<string>}>
     */
    private function seedDefinitions(): array
    {
        return [
            [
                'key' => 'fraud_checker',
                'label' => 'ফ্রড চেকার / Courier history',
                'seed_queries' => [
                    'What is Fraud Checker',
                    'Best Fraud Checker BD',
                    'Free Fraud Checker',
                    'FraudBD Alternative',
                    'Courier Fraud Checker',
                    'Fake Order Detection',
                    'Phone Number Fraud Check',
                    'ফ্রড চেকার',
                    'কুরিয়ার হিস্টোরি চেক',
                    'pathao fraud check',
                ],
            ],
            [
                'key' => 'fake_order',
                'label' => 'ফেক অর্ডার / COD fraud',
                'seed_queries' => [
                    'Stop Fake Orders',
                    'OTP Verification',
                    'Duplicate Orders',
                    'Blacklist Customers',
                    'Fake Customer Detection',
                    'High Risk Customer',
                    'ফেক অর্ডার',
                    'কিভাবে ফেক অর্ডার আটকাবো',
                    'COD fraud check',
                ],
            ],
            [
                'key' => 'courier',
                'label' => 'কুরিয়ার / অটো এন্ট্রি',
                'seed_queries' => [
                    'Steadfast Integration',
                    'Pathao Integration',
                    'RedX Integration',
                    'Auto Courier Entry',
                    'Courier Tracking',
                    'Courier History',
                    'কুরিয়ার অটো এন্ট্রি',
                    'পার্সেল নোট হিস্ট্রি',
                    'steadfast parcel note',
                ],
            ],
            [
                'key' => 'woocommerce',
                'label' => 'WooCommerce Bangladesh',
                'seed_queries' => [
                    'WooCommerce Bangladesh',
                    'WooCommerce Plugins',
                    'WooCommerce Automation',
                    'WooCommerce Mobile App',
                    'WooCommerce Management',
                    'WooCommerce Notifications',
                    'WooCommerce বাংলাদেশ',
                ],
            ],
            [
                'key' => 'cod',
                'label' => 'COD / Cash on Delivery',
                'seed_queries' => [
                    'COD Return Rate',
                    'COD Fraud',
                    'COD Verification',
                    'COD Automation',
                    'COD Order Management',
                    'COD ব্যবসা',
                    'ক্যাশ অন ডেলিভারি',
                ],
            ],
            [
                'key' => 'return_loss',
                'label' => 'রিটার্ন লস ক্যালকুলেটর',
                'seed_queries' => ['রিটার্ন লস', 'রিটার্ন লস ক্যালকুলেটর', 'COD রিটার্ন খরচ'],
            ],
            [
                'key' => 'checkout_protection',
                'label' => 'চেকআউট সুরক্ষা / OTP & block',
                'seed_queries' => ['চেকআউট OTP', 'ডুপ্লিকেট অর্ডার ব্লক', 'fake customer block'],
            ],
            [
                'key' => 'courier_charge',
                'label' => 'কুরিয়ার চার্জ ক্যালকুলেটর',
                'seed_queries' => ['কুরিয়ার চার্জ', 'pathao ডেলিভারি চার্জ', 'steadfast প্রাইসিং'],
            ],
            [
                'key' => 'missing_order',
                'label' => 'হারানো অর্ডার / Missing order',
                'seed_queries' => ['হারানো অর্ডার', 'missing order WooCommerce', 'abandoned checkout'],
            ],
            [
                'key' => 'facebook_ads',
                'label' => 'Facebook Ads / Pixel / ROAS',
                'seed_queries' => ['Facebook Ads ROAS', 'পিক্সেল প্রোটেকশন', 'ফেক purchase facebook'],
            ],
            [
                'key' => 'ai_orders',
                'label' => 'AI অর্ডার / Message & image to order',
                'seed_queries' => ['মেসেজ থেকে অর্ডার', 'AI order WooCommerce', 'screenshot থেকে অর্ডার'],
            ],
            [
                'key' => 'packing_print',
                'label' => 'প্যাকিং / Invoice & sticker',
                'seed_queries' => ['ইনভয়েস প্রিন্ট', 'কুরিয়ার স্টিকার প্রিন্ট', 'packing slip'],
            ],
            [
                'key' => 'multistore_app',
                'label' => 'মাল্টিস্টোর / Mobile app',
                'seed_queries' => ['মাল্টিস্টোর ড্যাশবোর্ড', 'WooCommerce mobile app', 'এক ড্যাশবোর্ডে সব স্টোর'],
            ],
            [
                'key' => 'team_calls',
                'label' => 'টিম / Call tracking',
                'seed_queries' => ['কল হিস্ট্রি', 'true call identifier', 'স্টাফ ম্যানেজমেন্ট'],
            ],
            [
                'key' => 'operations',
                'label' => 'অপারেশন / ড্যাশবোর্ড',
                'seed_queries' => ['WooCommerce অপারেশন', 'অর্ডার ম্যানেজমেন্ট বাংলাদেশ', 'COD সেলার টুল'],
            ],
            [
                'key' => 'general',
                'label' => 'সাধারণ WooCommerce BD',
                'seed_queries' => ['WooCommerce বাংলাদেশ', 'COD ব্যবসা', 'অনলাইন ব্যবসা গাইড'],
            ],
        ];
    }
};
