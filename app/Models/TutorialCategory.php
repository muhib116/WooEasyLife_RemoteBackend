<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TutorialCategory extends Model
{
    /**
     * Plugin Vue route names that can open tutorials via $route.name.
     * Excludes redirects-only, NotFound, RestrictionAlert, and layout parents
     * that always redirect (those are listed only when the play button can land on them).
     *
     * @var list<string>
     */
    public const KNOWN_KEYS = [
        'dashboard',
        'orders',
        'missingOrders',
        'blackList',
        'fraudCheck',
        'openAi',
        'license',
        'subscription',
        'subscriptionTransactionHistory',
        'employees',
        'smsConfig',
        'smsConfigTab',
        'sendSms',
        'sendSmsTab',
        'smsRecharge',
        'integration',
        'integrationTab',
        'courier',
        'courierTab',
        'customStatus',
        'customStatusTab',
        'securitySettings',
        'securityCheckout',
        'securityFraud',
        'developerTools',
        'developerToolsDeveloper',
        'marketingTools',
        'marketingToolsTab',
        'databaseMigration',
        'databaseIndexing',
        'orderStatusLogs',
        'databaseBackup',
        'metaAiBot',
        'connectApp',
    ];

    /**
     * @var array<string, string>
     */
    public const KEY_LABELS = [
        'dashboard' => 'Dashboard',
        'orders' => 'Orders',
        'missingOrders' => 'Missing Orders',
        'blackList' => 'Blacklist',
        'fraudCheck' => 'Fraud Check',
        'openAi' => 'OpenAI',
        'license' => 'License',
        'subscription' => 'Subscription',
        'subscriptionTransactionHistory' => 'Subscription History',
        'employees' => 'Employees',
        'smsConfig' => 'SMS Config',
        'smsConfigTab' => 'SMS Config Tab',
        'sendSms' => 'Send SMS',
        'sendSmsTab' => 'Send SMS Tab',
        'smsRecharge' => 'SMS Recharge',
        'integration' => 'Integration',
        'integrationTab' => 'Integration Tab',
        'courier' => 'Courier',
        'courierTab' => 'Courier Tab',
        'customStatus' => 'Custom Status',
        'customStatusTab' => 'Custom Status Tab',
        'securitySettings' => 'Security Settings',
        'securityCheckout' => 'Security — Checkout',
        'securityFraud' => 'Security — Fraud & Blocking',
        'developerTools' => 'Developer Tools',
        'developerToolsDeveloper' => 'Developer',
        'marketingTools' => 'Marketing Tools',
        'marketingToolsTab' => 'Marketing Tools Tab',
        'databaseMigration' => 'Database Migration',
        'databaseIndexing' => 'Database Indexing',
        'orderStatusLogs' => 'Order Status Logs',
        'databaseBackup' => 'Database Backup',
        'metaAiBot' => 'Meta AI Bot',
        'connectApp' => 'Connect App',
    ];

    protected $fillable = [
        'key',
        'label',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<TutorialVideo, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(TutorialVideo::class)->orderBy('sort_order')->orderBy('id');
    }
}
