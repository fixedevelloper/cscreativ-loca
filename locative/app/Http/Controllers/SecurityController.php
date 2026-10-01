<?php


namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SecurityController extends Controller
{
    /**
     * 🔹 Authentification d'un utilisateur (login)
     */
    public function login(LoginRequest $request)
    {
        // Vérifie les identifiants
        // Guard "web" explicite : la vérification des identifiants ne doit pas
        // dépendre du guard par défaut courant (Sanctum le remplace par un
        // RequestGuard, qui n'a pas d'attempt()).
        $guard = Auth::guard('web');

        if (!$guard->attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.auth.invalid_credentials'),
            ], 401);
        }

        /** @var User $user */
        $user = $guard->user();

        // Compte suspendu par un administrateur : identifiants valides mais
        // aucune session délivrée.
        if ($user->isSuspended()) {
            $guard->logout();

            return response()->json([
                'status' => 'error',
                'message' => __('messages.auth.suspended'),
            ], 403);
        }

        // Crée un token API
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => __('messages.auth.login_success'),
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'locale' => $user->locale,
                'photoURL' => $user->photo_url ?? null,
                'token' => $token,
            ],
        ]);
    }

    /**
     * 🔹 Déconnexion
     */
    public function logout(Request $request)
    {
        // Révoque uniquement le token de cette session (les autres appareils
        // restent connectés).
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('messages.auth.logout_success'),
        ]);
    }

    /**
     * 🔹 Retourne les infos du user connecté
     */
    public function profile(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $request->user(),
        ]);
    }
}
