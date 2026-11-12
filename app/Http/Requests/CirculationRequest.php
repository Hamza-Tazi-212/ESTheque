<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CirculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('request_id'))) {
            $this->merge(['request_id' => strtolower($this->input('request_id'))]);
        }
    }

    public function rules(): array
    {
        return ['request_id' => ['required', 'uuid']];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'L’identifiant de la demande est manquant. Recommencez les scans.',
            'request_id.uuid' => 'L’identifiant de la demande est invalide. Recommencez les scans.',
            'qr_codes.required' => 'Scannez les deux QR codes.',
            'qr_codes.array' => 'Deux QR codes sont nécessaires.',
            'qr_codes.list' => 'La paire de QR codes est invalide.',
            'qr_codes.size' => 'Scannez exactement deux QR codes.',
            'qr_codes.*.required' => 'Un QR code est manquant.',
            'qr_codes.*.string' => 'Le contenu du QR code doit être du texte.',
            'qr_codes.*.max' => 'Le QR code dépasse 191 caractères.',
            'qr_codes.*.distinct' => 'Scannez deux QR codes différents : une carte et un exemplaire.',
        ];
    }
}
