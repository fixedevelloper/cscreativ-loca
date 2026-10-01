<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // scoping à l'utilisateur fait dans le contrôleur (where user_id)
    }

    public function rules(): array
    {
        return [
            'reference' => 'required|string',
        ];
    }
}
