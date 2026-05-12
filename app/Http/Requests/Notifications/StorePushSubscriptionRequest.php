<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

final class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth middleware garantiza usuario autenticado
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'url', 'starts_with:https://', 'max:500'],
            'key' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ];
    }
}
