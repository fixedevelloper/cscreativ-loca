<?php

// API messages (English). Keys must mirror lang/fr/messages.php
// (checked by tests/Unit/TranslationParityTest).

return [
    'auth' => [
        'invalid_credentials' => 'Invalid credentials.',
        'suspended' => 'Account suspended. Please contact the platform administrator.',
        'login_success' => 'Logged in successfully.',
        'logout_success' => 'Logged out successfully.',
        'unauthenticated' => 'Unauthenticated.',
        'forbidden' => 'Access denied.',
    ],
    'errors' => [
        'generic' => 'Something went wrong.',
        'invalid_data' => 'The given data was invalid.',
        'not_found' => 'Resource not found.',
        'route_not_found' => 'Route not found.',
        'too_many_attempts' => 'Too many attempts. Please try again in a moment.',
    ],
    'admin' => [
        'cannot_suspend_self' => 'You cannot suspend your own account.',
        'suspended' => 'Account suspended.',
        'reactivated' => 'Account reactivated.',
    ],
    'agents' => [
        'owners_assigned' => 'Landlords assigned successfully.',
        'owner_detached' => 'Landlord removed from the agent\'s portfolio.',
    ],
    'documents' => [
        'created' => 'Document added successfully.',
        'file_missing' => 'File not found.',
        'shared' => 'Document shared successfully.',
        'deleted' => 'Document deleted.',
    ],
    'inventories' => [
        'created' => 'Inspection report created successfully.',
        'both_required' => 'Both inspection reports (move-in and move-out) must exist.',
    ],
    'lease' => [
        'generated' => 'Lease generated: the tenant can now review and sign it.',
        'locked' => 'The lease has already been signed by a party: it can no longer be regenerated.',
        'no_document' => 'Generate the lease before signing it.',
        'already_signed' => 'You have already signed this lease.',
        'signed' => 'Lease signed.',
    ],
    'maintenance' => [
        'created' => 'Maintenance request sent.',
        'status_updated' => 'Request status updated.',
        'commented' => 'Comment added.',
    ],
    'password' => [
        'link_sent_if_exists' => 'If an account exists for this email, a link has been sent to you.',
        'invalid_link' => 'Invalid or expired link.',
        'defined' => 'Password set successfully.',
    ],
    'payments' => [
        'rent_already_paid' => 'This rent is already fully paid.',
        'amount_exceeds_remaining' => 'The amount exceeds the remaining balance (:remaining).',
        'recorded' => 'Payment recorded successfully.',
    ],
    'profile' => [
        'updated' => 'Profile updated.',
        'password_changed' => 'Password changed. Your other sessions have been logged out.',
    ],
    'properties' => [
        'no_subscription_self' => 'No active subscription.',
        'no_subscription_owner' => 'This landlord has no active subscription.',
        'limit_self' => 'Property limit reached for your plan.',
        'limit_owner' => 'Property limit reached for the landlord\'s plan.',
    ],
    'rent_payment' => [
        'requested' => 'A payment request has been sent to your phone. Approve it with your Mobile Money PIN, then check the status.',
        'pending_exists' => 'A payment is already in progress for this rent. Approve it on your phone or try again in a few minutes.',
        'momo_message' => 'Rent :month',
        'status_success' => 'Payment received: your rent is paid.',
        'status_failed' => 'The payment failed or was declined.',
        'status_pending' => 'Payment still awaiting approval on your phone.',
    ],
    'subscriptions' => [
        'created' => 'Subscription created successfully.',
        'cancelled' => 'Subscription cancelled successfully.',
        'payment_requested' => 'Payment request sent. Confirm it on your phone, then check the status.',
        'already_confirmed' => 'Payment already confirmed.',
        'provider_unavailable' => 'The payment provider is not responding. Please try again in a moment.',
        'status_checked' => 'Status checked with the payment provider.',
        'required' => 'No active subscription found. Please subscribe.',
        'feature_unavailable' => 'Feature not available.',
        'plan_forbidden' => 'Your current plan (:plan) does not give access to this feature.',
    ],
    'tenants' => [
        'property_occupied' => 'This property is already occupied.',
        'owner_subscription_inactive' => 'The landlord\'s subscription is inactive or expired. It must be renewed to add a tenant.',
        'limit_reached' => 'Tenant limit reached for the landlord\'s plan.',
        'created' => 'Tenant added: an invitation has been sent by email.',
        'removed' => 'Tenant removed and lease terminated.',
    ],
    'users' => [
        'staff_invited' => 'Account created: an invitation has been sent by email.',
    ],
];
