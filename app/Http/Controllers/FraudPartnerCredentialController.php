<?php

namespace App\Http\Controllers;

use App\Models\FraudPartnerCredential;
use App\Services\FraudCheck\FraudPartnerCredentialResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FraudPartnerCredentialController extends Controller
{
    public function __construct(
        private FraudPartnerCredentialResolver $resolver,
    ) {}

    public function index(): Response
    {
        return Inertia::render('FraudCheck/Credentials', [
            'credentials' => $this->resolver->listForAdmin()->map->toAdminArray()->values(),
            'envFallbacks' => $this->resolver->envFallbacksForAdmin(),
            'courierMeta' => $this->resolver->courierMeta(),
            'couriers' => FraudPartnerCredential::COURIERS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, creating: true);

        $credential = FraudPartnerCredential::query()->create([
            'courier' => $data['courier'],
            'label' => $data['label'] ?? null,
            'identifier' => $data['identifier'],
            'secret' => $data['secret'],
            'is_active' => $data['is_active'] ?? true,
            'priority' => $data['priority'] ?? 100,
        ]);

        $this->resolver->forgetSessionCaches($credential->courier);

        return response()->json([
            'message' => 'Credential saved.',
            'credential' => $credential->toAdminArray(),
        ], 201);
    }

    public function update(Request $request, FraudPartnerCredential $credential): JsonResponse
    {
        $data = $this->validated($request, creating: false, existingId: $credential->id);

        // Clear sessions for both old and new identifiers/passwords.
        $this->resolver->forgetSessionCaches($credential->courier);

        $credential->courier = $data['courier'];
        $credential->label = $data['label'] ?? null;
        $credential->identifier = $data['identifier'];
        $credential->is_active = $data['is_active'] ?? $credential->is_active;
        $credential->priority = $data['priority'] ?? $credential->priority;

        if (filled($data['secret'] ?? null)) {
            $credential->secret = $data['secret'];
        }

        $credential->save();
        $this->resolver->forgetSessionCaches($credential->courier);

        return response()->json([
            'message' => 'Credential updated.',
            'credential' => $credential->fresh()->toAdminArray(),
        ]);
    }

    public function destroy(FraudPartnerCredential $credential): JsonResponse
    {
        $courier = $credential->courier;
        $this->resolver->forgetSessionCaches($courier);
        $credential->delete();

        return response()->json([
            'message' => 'Credential deleted.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating, ?int $existingId = null): array
    {
        $data = $request->validate([
            'courier' => ['required', 'string', Rule::in(FraudPartnerCredential::COURIERS)],
            'label' => ['nullable', 'string', 'max:120'],
            'identifier' => ['required', 'string', 'max:191'],
            'secret' => [$creating ? 'required' : 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:9999'],
        ]);

        $data['identifier'] = $this->resolver->normalizeIdentifier(
            (string) $data['courier'],
            (string) $data['identifier'],
        );

        if (in_array($data['courier'], ['redx', 'carrybee'], true)
            && ! preg_match('/^01[3-9]\d{8}$/', $data['identifier'])) {
            throw ValidationException::withMessages([
                'identifier' => 'Use a Bangladesh mobile number like 017XXXXXXXX.',
            ]);
        }

        $unique = Rule::unique('fraud_partner_credentials', 'identifier')
            ->where(fn ($q) => $q->where('courier', $data['courier']))
            ->ignore($existingId);

        validator(
            ['identifier' => $data['identifier']],
            ['identifier' => [$unique]],
        )->validate();

        return $data;
    }
}
