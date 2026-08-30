<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AeonMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'integer'],
            'context' => ['nullable', 'array'],
            'context.page' => ['nullable', 'string', 'max:255'],
        ];
    }
}
