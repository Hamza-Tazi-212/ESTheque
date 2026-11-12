<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IdentifyQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['qr_code' => ['required', 'string', 'max:191']];
    }

    public function messages(): array
    {
        return [
            'qr_code.required' => 'Le QR code est vide.',
            'qr_code.string' => 'Le QR code est invalide.',
            'qr_code.max' => 'Le QR code dépasse 191 caractères.',
        ];
    }
}
