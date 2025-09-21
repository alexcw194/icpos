<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Daftarkan policy
        $this->registerPolicies();

        // Super Admin bypass semua ability
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        // Gate tambahan bisa didefinisikan di sini bila diperlukan
        // Gate::define('something', fn($user) => ...);
    }
}
