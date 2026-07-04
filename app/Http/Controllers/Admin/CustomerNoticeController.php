<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerNotice;
use App\Services\CustomerNoticeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerNoticeController extends Controller
{
    public function __construct(
        private CustomerNoticeService $customerNoticeService,
    ) {}

    public function index()
    {
        return Inertia::render('CustomerNotices/Index', [
            'notices' => CustomerNotice::query()
                ->orderByDesc('priority')
                ->orderByDesc('id')
                ->get(),
            'options' => [
                'types' => CustomerNotice::TYPES,
                'severities' => CustomerNotice::SEVERITIES,
                'audiences' => CustomerNotice::AUDIENCES,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateNotice($request);

        CustomerNotice::create($data);
        $this->customerNoticeService->forgetCache();

        return back()->with('success', 'Notice created successfully.');
    }

    public function update(Request $request, CustomerNotice $customerNotice)
    {
        $data = $this->validateNotice($request);

        $customerNotice->update($data);
        $this->customerNoticeService->forgetCache();

        return back()->with('success', 'Notice updated successfully.');
    }

    public function destroy(CustomerNotice $customerNotice)
    {
        $customerNotice->delete();
        $this->customerNoticeService->forgetCache();

        return back()->with('success', 'Notice deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateNotice(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::in(CustomerNotice::TYPES)],
            'severity' => ['required', Rule::in(CustomerNotice::SEVERITIES)],
            'audience' => ['required', Rule::in(CustomerNotice::AUDIENCES)],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'cta_url' => ['nullable', 'url', 'max:2048'],
            'is_dismissible' => ['boolean'],
            'is_active' => ['boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        return [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'type' => $validated['type'],
            'severity' => $validated['severity'],
            'audience' => $validated['audience'],
            'cta_label' => $validated['cta_label'] ?? null,
            'cta_url' => $validated['cta_url'] ?? null,
            'is_dismissible' => $validated['is_dismissible'] ?? true,
            'is_active' => $validated['is_active'] ?? true,
            'priority' => $validated['priority'] ?? 0,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ];
    }
}
