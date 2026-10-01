<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceRequest;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\Contract;
use App\Models\MaintenanceRequest;
use App\Notifications\MaintenanceUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/** Demandes d'intervention (tickets) : locataire <-> bailleur / agence. */
class MaintenanceRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = MaintenanceRequest::with(['property', 'contract.tenant.user', 'author'])
            ->withCount('comments')
            ->visibleTo($request->user())
            // Urgentes et non traitées d'abord.
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->latest();

        if (in_array($request->get('status'), MaintenanceRequest::STATUSES, true)) {
            $query->where('status', $request->get('status'));
        }
        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%$search%")
                ->orWhereHas('property', fn ($p) => $p->where('name', 'like', "%$search%")));
        }

        return $this->paginated($query, $request, MaintenanceRequestResource::class);
    }

    public function store(StoreMaintenanceRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $propertyId = $user->role === 'tenant'
            ? Contract::findOrFail($data['contract_id'])->property_id
            : $data['property_id'];

        $ticket = MaintenanceRequest::create([
            'property_id' => $propertyId,
            'contract_id' => $data['contract_id'] ?? null,
            'created_by' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'category' => $data['category'],
            'priority' => $data['priority'],
        ]);

        Notification::send($ticket->participants($user), new MaintenanceUpdate($ticket, 'created', $user));

        return response()->json([
            'message' => __('messages.maintenance.created'),
            'data' => new MaintenanceRequestResource($ticket->load('property', 'author')),
        ], 201);
    }

    public function show(MaintenanceRequest $maintenanceRequest)
    {
        $this->authorize('view', $maintenanceRequest);

        return response()->json([
            'data' => new MaintenanceRequestResource(
                $maintenanceRequest->load(['property', 'contract.tenant.user', 'author', 'comments.author'])
            ),
        ]);
    }

    public function updateStatus(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $data = $request->validate(['status' => ['required', Rule::in(MaintenanceRequest::STATUSES)]]);
        $this->authorize('updateStatus', [$maintenanceRequest, $data['status']]);

        $maintenanceRequest->update([
            'status' => $data['status'],
            'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true)
                ? ($maintenanceRequest->resolved_at ?? now())
                : null,
        ]);

        $user = $request->user();
        Notification::send($maintenanceRequest->participants($user), new MaintenanceUpdate($maintenanceRequest, 'status', $user));

        return response()->json([
            'message' => __('messages.maintenance.status_updated'),
            'data' => new MaintenanceRequestResource($maintenanceRequest->load('property', 'author')),
        ]);
    }

    public function comment(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->authorize('comment', $maintenanceRequest);
        $data = $request->validate(['body' => 'required|string|max:5000']);

        $user = $request->user();
        $maintenanceRequest->comments()->create(['user_id' => $user->id, 'body' => $data['body']]);
        Notification::send($maintenanceRequest->participants($user), new MaintenanceUpdate($maintenanceRequest, 'comment', $user));

        return response()->json([
            'message' => __('messages.maintenance.commented'),
            'data' => new MaintenanceRequestResource(
                $maintenanceRequest->load(['property', 'contract.tenant.user', 'author', 'comments.author'])
            ),
        ], 201);
    }
}
