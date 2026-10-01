<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // scoping à l'utilisateur fait dans le contrôleur (where user_id)
    }

    public function rules(): array
    {
        return [
            'reference' => 'required|string',
            'phone' => ['required', 'regex:/^\d{9}$/'], // numéro local, l'indicatif 237 est ajouté par MomoService
        ];
    }
}
