<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('pdf.receipt.title') }} - {{ $rent->month }}</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td, th { padding: 6px 8px; border: 1px solid #ccc; text-align: left; vertical-align: top; }
        .total { font-weight: bold; }
        .footer { margin-top: 30px; font-size: 11px; color: #666; }
    </style>
</head>
<body>
    <h1>{{ __('pdf.receipt.title') }}</h1>
    <p>{{ __('pdf.receipt.period') }} : {{ $rent->month }}</p>

    <table>
        <tr><th>{{ __('pdf.receipt.landlord') }}</th><td>{{ $rent->contract->property->owner->name ?? '-' }}</td></tr>
        <tr><th>{{ __('pdf.receipt.tenant') }}</th><td>{{ $rent->contract->tenant->user->name ?? '-' }}</td></tr>
        <tr><th>{{ __('pdf.receipt.property') }}</th><td>{{ $rent->contract->property->name ?? '-' }}, {{ $rent->contract->property->address ?? '' }}</td></tr>
        <tr><th>{{ __('pdf.receipt.rent') }}</th><td>{{ number_format((float) $rent->amount, 0, ',', ' ') }} {{ __('pdf.currency') }}</td></tr>
        @if($rent->late_penalty)
            <tr><th>{{ __('pdf.receipt.late_fee') }}</th><td>{{ number_format((float) $rent->late_penalty, 0, ',', ' ') }} {{ __('pdf.currency') }}</td></tr>
        @endif
        <tr class="total"><th>{{ __('pdf.receipt.total_paid') }}</th><td>{{ number_format($rent->totalDue(), 0, ',', ' ') }} {{ __('pdf.currency') }}</td></tr>
        <tr><th>{{ __('pdf.receipt.payment_date') }}</th><td>{{ optional($rent->payments->last())->created_at?->format('Y-m-d') ?? now()->format('Y-m-d') }}</td></tr>
    </table>

    <p class="footer">{{ __('pdf.receipt.statement') }}</p>
</body>
</html>
