<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\InvoicePolicy;
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
        \App\Models\Customer::class => \App\Policies\CustomerPolicy::class,
        \App\Models\SalesOrder::class => \App\Policies\SalesOrderPolicy::class,
        Invoice::class => InvoicePolicy::class,
        \App\Models\Project::class => \App\Policies\ProjectPolicy::class,
        \App\Models\ProjectQuotation::class => \App\Policies\ProjectQuotationPolicy::class,
        \App\Models\Document::class => \App\Policies\DocumentPolicy::class,
        \App\Models\Prospect::class => \App\Policies\ProspectPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function (User $user, string $ability = null) {
            $ability = $ability ?? '';

            if ($user->hasRole('SuperAdmin')) {
                return true;
            }

            if ($user->hasRole('Admin') && str_starts_with($ability, 'finance.')) {
                return true;
            }

            if ($user->hasRole('Admin') && str_starts_with($ability, 'deliveries.')) {
                return true;
            }

            if ($user->hasRole('Admin') && str_starts_with($ability, 'invoices.')) {
                return true;
            }

            if ($user->hasRole('Finance')) {
                $financeAbilities = [
                    'invoices.view',
                    'invoices.create',
                    'invoices.update',
                    'invoices.post',
                    'deliveries.view',
                    'deliveries.create',
                    'deliveries.update',
                    'deliveries.post',
                ];

                if (in_array($ability, $financeAbilities, true)) {
                    return true;
                }
            }

            return null;
        });
    }
}
