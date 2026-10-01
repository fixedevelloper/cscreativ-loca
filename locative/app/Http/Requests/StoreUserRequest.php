<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Inscription publique — voir UserController::store. Le rôle n'est
 * volontairement pas un champ validé ici : il est toujours forcé à
 * "owner" côté contrôleur.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route publique
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'image' => 'nullable|image|max:2048',
        ];
    }
}
