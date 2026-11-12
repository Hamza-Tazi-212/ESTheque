<?php

namespace App\Http\Requests;

class RecordScanRequest extends CirculationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'qr_codes' => ['required', 'array', 'list', 'size:2'],
            'qr_codes.*' => ['required', 'string', 'max:191', 'distinct:strict'],
        ];
    }
}
