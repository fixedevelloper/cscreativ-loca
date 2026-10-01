<?php

// Messages de l'API (français, langue par défaut). Toute clé ajoutée ici doit
// exister dans lang/en/messages.php (vérifié par tests/Unit/TranslationParityTest).

return [
    'auth' => [
        'invalid_credentials' => 'Identifiants invalides.',
        'suspended' => 'Compte suspendu. Contactez l\'administrateur de la plateforme.',
        'login_success' => 'Connexion réussie.',
        'logout_success' => 'Déconnexion réussie.',
        'unauthenticated' => 'Non authentifié.',
        'forbidden' => 'Accès refusé.',
    ],
    'errors' => [
        'generic' => 'Une erreur est survenue.',
        'invalid_data' => 'Données invalides.',
        'not_found' => 'Ressource introuvable.',
        'route_not_found' => 'Route introuvable.',
        'too_many_attempts' => 'Trop de tentatives. Réessayez dans un instant.',
    ],
    'admin' => [
        'cannot_suspend_self' => 'Vous ne pouvez pas suspendre votre propre compte.',
        'suspended' => 'Compte suspendu.',
        'reactivated' => 'Compte réactivé.',
    ],
    'agents' => [
        'owners_assigned' => 'Bailleurs rattachés avec succès.',
        'owner_detached' => 'Bailleur retiré du portefeuille de l\'agent.',
    ],
    'documents' => [
        'created' => 'Document ajouté avec succès.',
        'file_missing' => 'Fichier introuvable.',
        'shared' => 'Document partagé avec succès.',
        'deleted' => 'Document supprimé.',
    ],
    'inventories' => [
        'created' => 'État des lieux créé avec succès.',
        'both_required' => 'Les deux états des lieux (entrée et sortie) doivent exister.',
    ],
    'lease' => [
        'generated' => 'Bail généré : le locataire peut le consulter et le signer.',
        'locked' => 'Le bail a déjà été signé par une partie : il ne peut plus être régénéré.',
        'no_document' => 'Générez d\'abord le bail avant de le signer.',
        'already_signed' => 'Vous avez déjà signé ce bail.',
        'signed' => 'Bail signé.',
    ],
    'maintenance' => [
        'created' => 'Demande d\'intervention envoyée.',
        'status_updated' => 'Statut de la demande mis à jour.',
        'commented' => 'Commentaire ajouté.',
    ],
    'password' => [
        'link_sent_if_exists' => 'Si un compte existe pour cet email, un lien vous a été envoyé.',
        'invalid_link' => 'Lien invalide ou expiré.',
        'defined' => 'Mot de passe défini avec succès.',
    ],
    'payments' => [
        'rent_already_paid' => 'Ce loyer est déjà soldé.',
        'amount_exceeds_remaining' => 'Le montant dépasse le reste dû (:remaining).',
        'recorded' => 'Paiement enregistré avec succès.',
    ],
    'profile' => [
        'updated' => 'Profil mis à jour.',
        'password_changed' => 'Mot de passe modifié. Vos autres sessions ont été déconnectées.',
    ],
    'properties' => [
        'no_subscription_self' => 'Aucun abonnement actif.',
        'no_subscription_owner' => 'Aucun abonnement actif pour ce bailleur.',
        'limit_self' => 'Limite de biens atteinte pour votre plan.',
        'limit_owner' => 'Limite de biens atteinte pour le plan du bailleur.',
    ],
    'rent_payment' => [
        'requested' => 'Demande de paiement envoyée sur votre téléphone. Validez-la avec votre code Mobile Money, puis vérifiez le statut.',
        'pending_exists' => 'Un paiement est déjà en cours pour ce loyer. Validez-le sur votre téléphone ou réessayez dans quelques minutes.',
        'momo_message' => 'Loyer :month',
        'status_success' => 'Paiement reçu : votre loyer est réglé.',
        'status_failed' => 'Le paiement a échoué ou a été refusé.',
        'status_pending' => 'Paiement toujours en attente de validation sur votre téléphone.',
    ],
    'subscriptions' => [
        'created' => 'Souscription créée avec succès.',
        'cancelled' => 'Abonnement annulé avec succès.',
        'payment_requested' => 'Demande de paiement envoyée. Confirmez sur votre téléphone puis vérifiez le statut.',
        'already_confirmed' => 'Paiement déjà confirmé.',
        'provider_unavailable' => 'Le fournisseur de paiement ne répond pas. Réessayez dans un instant.',
        'status_checked' => 'Statut vérifié auprès du fournisseur de paiement.',
        'required' => 'Aucun abonnement actif trouvé. Veuillez vous abonner.',
        'feature_unavailable' => 'Fonctionnalité non disponible.',
        'plan_forbidden' => 'Votre plan actuel (:plan) ne permet pas d\'accéder à cette fonctionnalité.',
    ],
    'tenants' => [
        'property_occupied' => 'Ce bien est déjà occupé.',
        'owner_subscription_inactive' => 'L\'abonnement du bailleur est inactif ou expiré. Il doit être renouvelé pour ajouter un locataire.',
        'limit_reached' => 'Limite de locataires atteinte pour le plan du bailleur.',
        'created' => 'Locataire ajouté : une invitation lui a été envoyée par email.',
        'removed' => 'Locataire retiré et bail résilié.',
    ],
    'users' => [
        'staff_invited' => 'Compte créé : une invitation a été envoyée par email.',
    ],
];
