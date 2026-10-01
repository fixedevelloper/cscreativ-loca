<?php

namespace App\Http\Requests;

use App\Models\Rent;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rentId = $this->input('rent_id');
        $rent = $rentId ? Rent::with('contract.property')->find($rentId) : null;

        if (!$rent) {
            return true; // laisse la règle 'exists' de rules() gérer un id absent/invalide
        }

        // Seuls le bailleur du bien concerné, un agent qui le gère, ou un
        // admin peuvent enregistrer un encaissement (jamais le locataire).
        return $this->user()->can('update', $rent->contract->property);
    }

    public function rules(): array
    {
        return [
            'rent_id' => 'required|exists:rents,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,bank,mobile',
        ];
    }
}
