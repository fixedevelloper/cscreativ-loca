<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiement de la souscription</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 480px;">
        <div class="card-body text-center">
            <h3 class="mb-3">💳 Paiement de la souscription</h3>

            <p><strong>Plan :</strong> {{ $payment->plan_name }}</p>
            <p><strong>Montant :</strong> {{ number_format($payment->amount, 2, ',', ' ') }} FCFA</p>
            <p><strong>Référence :</strong> {{ $payment->reference }}</p>
            <p><strong>Statut :</strong>
                <span class="badge
                    @if($payment->status === 'pending') bg-warning
                    @elseif($payment->status === 'success') bg-success
                    @else bg-danger @endif">
                    {{ strtoupper($payment->status) }}
                </span>
            </p>

            <hr>

            @if($payment->status === 'pending')
                <form method="POST" action="{{ route('payment.confirm', ['ref' => $payment->reference]) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        ✅ Payer maintenant
                    </button>
                </form>
            @else
                <div class="alert alert-success mt-3">
                    ✅ Paiement déjà confirmé !
                </div>
            @endif

            <a href="/" class="btn btn-link mt-3">Retour à l’accueil</a>
        </div>
    </div>
</div>

</body>
</html>
