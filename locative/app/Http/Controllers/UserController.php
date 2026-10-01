<?php

namespace App\Http\Controllers;

use App\Http\Helpers\Helpers;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{

    /**
     * Display a listing of the resource. Réservé aux admins (voir routes/api.php).
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = User::query()->orderBy('created_at', 'desc');

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%"));
        }

        // Format de liste commun (avant : paginator Laravel imbriqué sous
        // data, avec ?per_page= au lieu du ?limit= envoyé par le frontend).
        return $this->paginated($query, $request);
    }

    /**
     * Inscription publique. Seul le rôle "owner" peut être créé par ce point
     * d'entrée non authentifié — "agent" et "admin" doivent être créés par un
     * admin déjà authentifié via une route protégée dédiée.
     *
     * @param Request $request
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('users', 'public');
            $imageUrl = config('app.url') . Storage::url($imagePath);
        }

        $user = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'role' => 'owner', // 🔐 jamais pris depuis la requête sur ce endpoint public
            'locale' => app()->getLocale(),
            'password' => Hash::make($validated['password']),
            'image_url' => $imageUrl,
        ]);

        return Helpers::success($user->makeHidden(['password']));
    }

    /**
     * Création d'un agent ou d'un admin — réservé aux admins authentifiés
     * (voir routes/api.php, groupe role:admin).
     */
    public function storeStaff(StoreStaffRequest $request)
    {
        $validated = $request->validated();

        // Mot de passe aléatoire inutilisable + lien "définir mon mot de
        // passe" : l'admin ne connaît jamais le mot de passe de l'agent
        // (même flux que l'invitation des locataires).
        $user = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'locale' => app()->getLocale(),
            'password' => Hash::make(Str::random(40)),
        ]);

        $user->sendInvitation();

        return response()->json([
            'message' => __('messages.users.staff_invited'),
            'data' => $user,
        ], 201);
    }

    /**
     * Display the specified resource.
     * @param User $user
     */
    public function show(User $user)
    {
        return Helpers::success($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }
}
