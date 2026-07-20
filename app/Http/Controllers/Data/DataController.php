<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Services\TutorialService;

class DataController extends Controller
{
    public function getTutorials(TutorialService $tutorialService)
    {
        return $this->successResponse($tutorialService->toPluginPayload());
    }

    public function getContactInfo()
    {
        $tutorials = file_get_contents(__DIR__ . '/contactInfo.json');

        $decoded_data = json_decode($tutorials);
        $infos = collect($decoded_data)->map(function ($item) {
            return [
                'icon' => asset('images/contacts/'.$item->icon),
                'content' => $item->content,
            ];
        });

        return $this->successResponse($infos);
    }
}
