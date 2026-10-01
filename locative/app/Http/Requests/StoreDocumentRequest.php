<?php

namespace App\Http\Requests;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 🔐 On ne peut rattacher un document qu'à un contrat qu'on a le droit
        // de voir (sinon injection de documents chez un autre client). Les
        // ids inexistants sont laissés à la règle 'exists' de rules().
        $contractIds = array_filter(array_merge(
            [$this->input('contract_id')],
            (array) $this->input('shared_with', [])
        ));

        foreach (Contract::with('property')->findMany($contractIds) as $contract) {
            if (!$this->user()->can('view', $contract)) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'nullable|exists:contracts,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:contract,quittance,avis,autre',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'shared_with' => 'nullable|array',
            'shared_with.*' => 'exists:contracts,id',
        ];
    }
}
