<?php


namespace App\Http\Controllers;


use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    /**
     * Rattache un ou plusieurs bailleurs (owners) à un agent.
     * Réservé aux admins (voir routes/api.php, groupe role:admin).
     */
    public function assignOwners(Request $request, int $agentId)
    {
        $agent = User::where('role', 'agent')->findOrFail($agentId);

        $validated = $request->validate([
            'owner_ids' => 'required|array|min:1',
            'owner_ids.*' => Rule::exists('users', 'id')->where('role', 'owner'),
        ]);

        $agent->owners()->syncWithoutDetaching($validated['owner_ids']);

        return response()->json([
            'message' => __('messages.agents.owners_assigned'),
            'data' => $agent->owners()->get(),
        ]);
    }

    /**
     * Liste les bailleurs rattachés à un agent.
     * L'agent ne peut consulter que sa propre liste ; l'admin peut tout voir.
     */
    public function getOwners(Request $request, int $agentId)
    {
        $requester = $request->user();

        if ($requester->role === 'agent' && $requester->id !== $agentId) {
            return $this->error(__('messages.auth.forbidden'), 403);
        }

        $agent = User::where('role', 'agent')->findOrFail($agentId);

        return response()->json(['data' => $agent->owners()->get()]);
    }

    /**
     * Retire un bailleur du portefeuille d'un agent (admin).
     */
    public function detachOwner(int $agentId, int $ownerId)
    {
        $agent = User::where('role', 'agent')->findOrFail($agentId);
        $agent->owners()->detach($ownerId);

        return response()->json([
            'message' => __('messages.agents.owner_detached'),
            'data' => $agent->owners()->get(),
        ]);
    }
}
