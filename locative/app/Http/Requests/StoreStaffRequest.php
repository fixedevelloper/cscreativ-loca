<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un agent/admin — réservé aux admins (voir routes/api.php,
 * groupe role:admin, et UserController::storeStaff). Pas de mot de passe :
 * le membre du personnel le définit lui-même via un lien d'invitation.
 */
class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => ['required', Rule::in(['agent', 'admin'])],
        ];
    }
}
