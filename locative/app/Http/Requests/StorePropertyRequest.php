<?php

namespace App\Http\Requests;

use App\Models\Property;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!$this->user()->can('create', Property::class)) {
            return false;
        }

        // owner_id : bailleur pour lequel le bien est créé (agent/admin). Un
        // bailleur crée toujours pour lui-même. Id inexistant -> règle 'exists'.
        $owner = User::where('role', 'owner')->find($this->ownerId());

        return !$owner || $this->user()->can('createFor', [Property::class, $owner]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'type' => 'required|in:room,studio,apartment,office,other',
            'surface' => 'required|string|max:50',
            'monthly_charge' => 'nullable|numeric|min:0',
            'number_piece' => 'required|integer|min:1',
            'monthly_rent' => 'required|numeric|min:0',
            // Obligatoire pour un agent/admin (qui n'a pas de parc à lui).
            'owner_id' => [
                Rule::requiredIf(fn () => $this->user()->role !== 'owner'),
                'nullable',
                Rule::exists('users', 'id')->where('role', 'owner'),
            ],
        ];
    }

    /** Bailleur cible : soi-même pour un bailleur, owner_id sinon. */
    public function ownerId(): ?int
    {
        return $this->user()->role === 'owner'
            ? $this->user()->id
            : ($this->filled('owner_id') ? (int) $this->input('owner_id') : null);
    }
}
