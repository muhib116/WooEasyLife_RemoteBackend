<?php

namespace App\Services\Messenger;

use App\Models\MessengerPageConnection;

class MessengerInboundEnricher
{
    public function __construct(
        protected MessengerPageOAuthService $oauth
    ) {
    }

    /**
     * Populate sender name + profile picture for each event (best-effort, cached per PSID).
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    public function enrich(MessengerPageConnection $connection, array $events): array
    {
        $pageToken = (string) $connection->page_access_token;
        if ($pageToken === '') {
            return $events;
        }

        $cache = [];
        foreach ($events as &$event) {
            // Echoes are page→customer; don't burn Graph quota on profile lookups.
            if (! empty($event['is_echo']) || ($event['event_type'] ?? '') === 'reaction') {
                continue;
            }

            $psid = (string) ($event['psid'] ?? '');
            if ($psid === '') {
                continue;
            }

            if (! array_key_exists($psid, $cache)) {
                $cache[$psid] = $this->oauth->fetchSenderProfile($psid, $pageToken);
            }

            $profile = $cache[$psid];
            if (($profile['name'] ?? '') !== '' || ($profile['profile_pic'] ?? '') !== '') {
                $event['sender_profile'] = [
                    'name' => (string) ($profile['name'] ?? ''),
                    'profile_pic' => (string) ($profile['profile_pic'] ?? ''),
                ];
            }
        }
        unset($event);

        return $events;
    }
}
