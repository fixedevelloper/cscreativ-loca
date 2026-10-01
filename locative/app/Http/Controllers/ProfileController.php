<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Profil de l'utilisateur connecté, pour tous les rôles. L'email n'est pas
 * modifiable ici (identifiant de connexion, à faire évoluer avec une
 * re-vérification si besoin).
 */
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|nullable|string|max:30',
            'address' => 'sometimes|nullable|string|max:255',
            // Langue des emails, PDF et messages (aussi changée depuis le
            // sélecteur de langue de l'interface).
            'locale' => ['sometimes', Rule::in(SetLocale::SUPPORTED)],
        ]);

        $request->user()->update($validated);

        // Message dans la langue qui vient éventuellement d'être choisie.
        if (isset($validated['locale'])) {
            app()->setLocale($validated['locale']);
        }

        return response()->json(['message' => __('messages.profile.updated'), 'data' => $request->user()->fresh()]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password:sanctum',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($request->input('password'))])->save();

        // Déconnecte les autres appareils ; la session courante reste ouverte.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => __('messages.profile.password_changed')]);
    }
}
