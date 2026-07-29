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
                $channel = (($event['channel'] ?? '') === 'instagram') ? 'instagram' : 'messenger';
                $cache[$psid] = $this->oauth->fetchSenderProfile($psid, $pageToken, $channel);
            }

            $profile = $cache[$psid];
            $existing = is_array($event['sender_profile'] ?? null) ? $event['sender_profile'] : [];
            $name = (string) ($profile['name'] ?? '');
            $pic = (string) ($profile['profile_pic'] ?? '');
            $username = (string) ($profile['username'] ?? '');
            $gender = $this->oauth->normalizeSenderGender($profile['gender'] ?? '');
            if ($name === '' && $username !== '') {
                $name = $username;
            }
            if ($name === '') {
                $name = (string) ($existing['name'] ?? '');
            }
            if ($pic === '' && (($event['channel'] ?? '') === 'instagram')) {
                $hint = $username !== '' ? $username : (string) ($existing['name'] ?? '');
                $pic = $this->oauth->instagramPublicAvatarUrl($hint);
            }
            if ($pic === '') {
                $pic = (string) ($existing['profile_pic'] ?? '');
            }
            if ($gender === '') {
                $gender = $this->oauth->normalizeSenderGender($existing['gender'] ?? '');
            }

            if ($name !== '' || $pic !== '' || $gender !== '') {
                $event['sender_profile'] = [
                    'name' => $name,
                    'profile_pic' => $pic,
                    'username' => $username,
                    'gender' => $gender,
                ];
            }
        }
        unset($event);

        return $events;
    }
}
