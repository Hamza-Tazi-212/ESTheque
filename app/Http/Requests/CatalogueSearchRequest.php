<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogueSearchRequest extends FormRequest
{
    protected $redirectRoute = 'student.index';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'Saisissez un titre ou un auteur dans le champ de recherche.',
            'q.max' => 'La recherche ne doit pas dépasser 255 caractères.',
        ];
    }

    public function searchTerm(): string
    {
        return trim($this->validated('q') ?? '');
    }
}
