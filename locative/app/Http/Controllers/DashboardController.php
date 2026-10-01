<?php


namespace App\Http\Controllers;


use App\Http\Helpers\Helpers;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rent;
use App\Models\Tenant;

class DashboardController extends Controller
{

    public function dashboard()
    {
        $user = auth()->user();
        $month = now()->format('Y-m');

        $propertiesQuery = Property::visibleTo($user);
        $propertyCounts = [
            'total' => (clone $propertiesQuery)->count(),
            'occupied' => (clone $propertiesQuery)->where('status', 'occupied')->count(),
            'free' => (clone $propertiesQuery)->where('status', 'free')->count(),
            'maintenance' => (clone $propertiesQuery)->where('status', 'maintenance')->count(),
        ];

        $dueThisMonth = Rent::visibleTo($user)->where('month', $month);
        $dueCount = (clone $dueThisMonth)->count();
        $dueAmount = (clone $dueThisMonth)->get()->sum(fn (Rent $r) => $r->totalDue());

        $collectedThisMonth = Payment::visibleTo($user)
            ->where('status', 'success')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month);
        $collectedCount = (clone $collectedThisMonth)->count();
        $collectedAmount = (clone $collectedThisMonth)->sum('amount');

        $overdueQuery = Rent::visibleTo($user)
            ->whereIn('status', ['pending', 'late'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());
        $overdueCount = (clone $overdueQuery)->count();
        $overdueAmount = (clone $overdueQuery)->get()->sum(fn (Rent $r) => $r->totalDue());

        return Helpers::success([
            'properties' => $propertyCounts,
            'tenants' => [
                'total' => Tenant::visibleTo($user)->count(),
            ],
            'rents' => [
                'due_this_month' => ['count' => $dueCount, 'amount' => round($dueAmount, 2)],
                'collected_this_month' => ['count' => $collectedCount, 'amount' => round((float) $collectedAmount, 2)],
                'overdue' => ['count' => $overdueCount, 'amount' => round($overdueAmount, 2)],
            ],
            'collection_rate' => $dueAmount > 0 ? round(min($collectedAmount, $dueAmount) / $dueAmount * 100, 1) : null,
        ]);
    }
}
