<?php

namespace App\Http\Controllers;

use App\Services\OrderIntelligence\FraudCheckInventory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class FraudCheckDashboardController extends Controller
{
    public function __invoke(Request $request, FraudCheckInventory $inventory): Response
    {
        $phone = trim((string) $request->query('phone', ''));
        $person = null;
        $phoneError = null;

        if ($phone !== '') {
            try {
                $person = $inventory->person($phone);
            } catch (InvalidArgumentException $exception) {
                $phoneError = $exception->getMessage();
            }
        }

        return Inertia::render('FraudCheck/Dashboard', [
            'summary' => $inventory->summary(),
            'phone' => $phone,
            'person' => $person,
            'phoneError' => $phoneError,
        ]);
    }
}
