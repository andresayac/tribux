<?php

namespace App\Providers;

use App\Application\Contracts\Clock;
use App\Application\Contracts\IdGenerator;
use App\Application\Invoices\Contracts\InvoiceRepository;
use App\Application\Invoices\Processing\Contracts\InvoiceProcessingRepository;
use App\Application\Issuers\Contracts\IssuerProfileProvider;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Identifiers\UuidV7Generator;
use App\Infrastructure\Issuers\JsonFileIssuerProfileProvider;
use App\Infrastructure\Persistence\EloquentInvoiceProcessingRepository;
use App\Infrastructure\Persistence\EloquentInvoiceRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(IdGenerator::class, UuidV7Generator::class);
        $this->app->bind(InvoiceRepository::class, EloquentInvoiceRepository::class);
        $this->app->bind(InvoiceProcessingRepository::class, EloquentInvoiceProcessingRepository::class);

        $this->app->singleton(IssuerProfileProvider::class, function (): JsonFileIssuerProfileProvider {
            $path = config('tribux.issuers_file');

            return new JsonFileIssuerProfileProvider(is_string($path) ? $path : null);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
