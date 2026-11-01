<?php

namespace App\Http\Requests\CourierCheck;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckCourierCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'site_url' => ['required', 'string', 'max:255'],
            'site_key' => ['required', 'string', 'max:64'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $failed = $validator->failed();

        if (isset($failed['site_key'])) {
            throw new HttpResponseException(response()->json(['error' => 'invalid_site_key'], 401));
        }

        if (isset($failed['phone'])) {
            throw new HttpResponseException(response()->json(['error' => 'invalid_phone'], 422));
        }

        throw new HttpResponseException(response()->json(['error' => 'invalid_site_url'], 422));
    }
}
