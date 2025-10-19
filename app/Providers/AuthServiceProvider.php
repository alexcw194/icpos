<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Customer::class   => \App\Policies\CustomerPolicy::class,
        \App\Models\SalesOrder::class => \App\Policies\SalesOrderPolicy::class, // + Sales Order
        Invoice::class => InvoicePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Daftarkan policy
        $this->registerPolicies();

        // Super Admin bypass semua ability
        Gate::before(function (User $user, string $ability = null) {
            if ($user->hasRole('SuperAdmin')) return true;                 // full bypass
            // treat all finance abilities as admin-allowed
            if ($user->hasRole('Admin') && str_starts_with($ability ?? '', 'finance.')) return true;
            return null;
        });

        // Gate tambahan bisa didefinisikan di sini bila diperlukan
        // Gate::define('something', fn($user) => ...);
    }
}
