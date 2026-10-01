<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $propertyId = $this->input('propertyId');
        $property = $propertyId ? Property::find($propertyId) : null;
        if (!$property) {
            return true; // laisse la règle 'exists' gérer un id absent/invalide
        }

        return $this->user()->can('update', $property);
    }

    public function rules(): array
    {
        return [
            'propertyId' => 'required|exists:properties,id',
            // Le contrat doit porter sur le bien inventorié (sinon un état des
            // lieux pourrait être rattaché au bail d'un autre bien/client).
            'contract_id' => [
                'nullable',
                Rule::exists('contracts', 'id')->where('property_id', $this->input('propertyId')),
            ],
            'type' => 'required|in:entry,exit',
            'general_condition' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            // Valeurs de l'enum inventory_items.condition (un texte libre
            // provoquait une erreur SQL en MySQL strict).
            'items.*.condition' => ['nullable', Rule::in(['new', 'good', 'used', 'medium condition', 'bad condition', 'broken', 'missing'])],
            'items.*.remarks' => 'nullable|string',
        ];
    }
}
