<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $propertyId = $this->input('property_id');

        // Laisse la règle 'exists' de rules() gérer un id absent/invalide —
        // on ne bloque ici que si la propriété existe mais n'appartient pas
        // à l'utilisateur (voir PropertyPolicy::update).
        $property = $propertyId ? Property::find($propertyId) : null;
        if (!$property) {
            return true;
        }

        return $this->user()->can('update', $property);
    }

    public function rules(): array
    {
        return [
            'property_id' => 'required|exists:properties,id',
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'profession' => 'nullable|string|max:150',
            'nationality' => 'nullable|string|max:100',
            'gender' => 'nullable|string|max:10',
            'date_birth' => 'nullable|date',
            'father_name' => 'nullable|string|max:100',
            'mother_name' => 'nullable|string|max:100',
            'document_type' => 'nullable|in:cni,passport',
            'document_id' => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'number_person' => 'nullable|integer|min:1',
            'rent_amount' => 'required|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'terms' => 'nullable|string',
        ];
    }
}
