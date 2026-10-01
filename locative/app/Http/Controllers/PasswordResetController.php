<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Flux "mot de passe oublié" / "définir mon mot de passe". Sert aussi
 * d'invitation des locataires : TenantController::store crée le compte avec
 * un mot de passe aléatoire et envoie ce lien.
 */
class PasswordResetController extends Controller
{
    public function sendLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // Réponse identique que l'email existe ou non : pas d'énumération
        // des comptes.
        return response()->json([
            'status' => 'success',
            'message' => __('messages.password.link_sent_if_exists'),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Lien d'invitation (invitation=1) ou de réinitialisation : brokers et
        // tables de tokens distincts (voir config/auth.php).
        $broker = Password::broker($request->boolean('invitation') ? 'invitations' : 'users');

        $status = $broker->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Révoque les tokens API existants : un mot de passe
                // réinitialisé doit déconnecter les sessions en cours.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.password.invalid_link'),
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.password.defined'),
        ]);
    }
}
