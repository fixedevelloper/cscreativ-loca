<?php

namespace App\Http\Requests;

use App\Models\Contract;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nouvelle demande d'intervention :
 * - locataire : sur un de SES baux actifs (contract_id) ;
 * - bailleur / agent / admin : sur un bien qu'il gère (property_id).
 */
class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user->role === 'tenant') {
            $contract = Contract::find($this->input('contract_id'));

            return !$contract || $user->tenants()->whereKey($contract->tenant_id)->exists();
        }

        $property = Property::find($this->input('property_id'));

        return !$property || $user->can('update', $property);
    }

    public function rules(): array
    {
        $isTenant = $this->user()->role === 'tenant';

        return [
            'title' => 'required|string|max:150',
            'description' => 'required|string|max:5000',
            'category' => ['required', Rule::in(MaintenanceRequest::CATEGORIES)],
            'priority' => ['required', Rule::in(MaintenanceRequest::PRIORITIES)],
            'contract_id' => $isTenant
                ? ['required', Rule::exists('contracts', 'id')->where('status', 'active')]
                : ['nullable', Rule::exists('contracts', 'id')->where('property_id', $this->input('property_id'))],
            'property_id' => $isTenant ? ['prohibited'] : ['required', 'exists:properties,id'],
        ];
    }
}
