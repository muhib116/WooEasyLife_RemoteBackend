<?php

it('returns fraud check report via web route', function () {
    $response = $this->postJson('/q8w1d9zp7kuo2vrb5m6cnx0ahjls4et3ifyugpdbq2m1vnz0l/fraud-check', [
        'phone' => '01770989591',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'total_order',
            'confirmed',
            'frauds',
            'cancel',
            'success_rate',
            'courier' => [
                ['title', 'report'],
            ],
        ]);
});
