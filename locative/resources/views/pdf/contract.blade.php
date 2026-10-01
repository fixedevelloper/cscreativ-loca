<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('pdf.contract.title') }} #{{ $contract->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td, th { padding: 6px 8px; border: 1px solid #ccc; text-align: left; vertical-align: top; }
        .section-title { margin-top: 20px; font-size: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ __('pdf.contract.title') }}</h1>
    <p>{{ __('pdf.contract.reference') }} : #{{ $contract->id }}</p>

    <div class="section-title">{{ __('pdf.contract.property') }}</div>
    <table>
        <tr><th>{{ __('pdf.contract.designation') }}</th><td>{{ $contract->property->name ?? '-' }}</td></tr>
        <tr><th>{{ __('pdf.contract.address') }}</th><td>{{ $contract->property->address ?? '-' }}, {{ $contract->property->city ?? '' }}</td></tr>
    </table>

    <div class="section-title">{{ __('pdf.contract.tenant') }}</div>
    <table>
        <tr><th>{{ __('pdf.contract.name') }}</th><td>{{ $contract->tenant->user->name ?? '-' }}</td></tr>
        <tr><th>{{ __('pdf.contract.email') }}</th><td>{{ $contract->tenant->user->email ?? '-' }}</td></tr>
    </table>

    <div class="section-title">{{ __('pdf.contract.terms') }}</div>
    <table>
        <tr><th>{{ __('pdf.contract.start_date') }}</th><td>{{ $contract->start_date }}</td></tr>
        <tr><th>{{ __('pdf.contract.end_date') }}</th><td>{{ $contract->end_date ?? __('pdf.contract.open_ended') }}</td></tr>
        <tr><th>{{ __('pdf.contract.rent') }}</th><td>{{ number_format((float) $contract->rent_amount, 0, ',', ' ') }} {{ __('pdf.currency') }}</td></tr>
        <tr><th>{{ __('pdf.contract.deposit') }}</th><td>{{ number_format((float) $contract->deposit_amount, 0, ',', ' ') }} {{ __('pdf.currency') }}</td></tr>
        <tr><th>{{ __('pdf.contract.occupants') }}</th><td>{{ $contract->number_person }}</td></tr>
    </table>

    @if($contract->terms)
        <div class="section-title">{{ __('pdf.contract.extra_terms') }}</div>
        <p>{{ $contract->terms }}</p>
    @endif
    @if(!empty($signatures) && count($signatures))
        <div class="section-title">{{ __('pdf.contract.signatures') }}</div>
        <table>
            <tr>
                <th>{{ __('pdf.contract.party') }}</th>
                <th>{{ __('pdf.contract.signer') }}</th>
                <th>{{ __('pdf.contract.signed_at') }}</th>
                <th>{{ __('pdf.contract.ip') }}</th>
            </tr>
            @foreach($signatures as $signature)
                <tr>
                    <td>{{ __('pdf.contract.parties.' . $signature->party) }}</td>
                    <td>{{ $signature->signer->name ?? '-' }}</td>
                    <td>{{ $signature->signed_at->format('Y-m-d H:i:s T') }}</td>
                    <td>{{ $signature->ip_address ?? '-' }}</td>
                </tr>
            @endforeach
        </table>
        <p style="font-size: 10px; color: #666;">
            {{ __('pdf.contract.hash') }} : {{ $signatures->first()->document_hash }}<br>
            {{ __('pdf.contract.signature_notice') }}
        </p>
    @endif
</body>
</html>
