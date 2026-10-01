<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Service\LeaseService;
use Illuminate\Http\Request;
use RuntimeException;

/** Bail PDF (génération) et signature électronique simple des parties. */
class LeaseController extends Controller
{
    public function generate(Request $request, Contract $contract, LeaseService $leases)
    {
        $this->authorize('manageLease', $contract);

        try {
            $leases->generate($contract, $request->user());
        } catch (RuntimeException $e) {
            return $this->error(__($e->getMessage()), 409);
        }

        return response()->json([
            'message' => __('messages.lease.generated'),
            'data' => new ContractResource($this->fresh($contract)),
        ], 201);
    }

    public function sign(Request $request, Contract $contract, LeaseService $leases)
    {
        $party = $request->user()->can('view', $contract)
            ? app(\App\Policies\ContractPolicy::class)->signingParty($request->user(), $contract)
            : null;
        abort_if($party === null, 403);

        // Consentement explicite à signer électroniquement.
        $request->validate(['accept' => 'accepted']);

        try {
            $leases->sign($contract, $request->user(), $party, $request->ip(), $request->userAgent());
        } catch (RuntimeException $e) {
            return $this->error(__($e->getMessage()), 409);
        }

        return response()->json([
            'message' => __('messages.lease.signed'),
            'data' => new ContractResource($this->fresh($contract)),
        ]);
    }

    private function fresh(Contract $contract): Contract
    {
        return $contract->fresh(['property.owner', 'tenant.user', 'signatures.signer']);
    }
}
