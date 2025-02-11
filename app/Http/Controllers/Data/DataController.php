<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DataController extends Controller
{
    public function getTutorials()
    {
        $tutorials = file_get_contents(__DIR__ . '/tutorial.json');

        $decoded_data = json_decode($tutorials);

        return $this->successResponse($decoded_data);
    }
    public function getContactInfo()
    {
        $tutorials = file_get_contents(__DIR__ . '/contactInfo.json');

        $decoded_data = json_decode($tutorials);
        $infos = collect($decoded_data)->map(function($item) {
            return [
                "icon" => asset("images/contacts/".$item->icon),
                "bg" => $item->bg,
                "color" => $item->color,
                "content" => $item->content
            ];
        });

        return $this->successResponse($infos);
    }
}
