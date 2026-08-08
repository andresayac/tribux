<?php

namespace App\Providers;

use App\Application\Contracts\Clock;
use App\Application\Contracts\IdGenerator;
use App\Application\Invoices\Building\Contracts\Fev19DocumentValidator;
use App\Application\Invoices\Contracts\InvoiceRepository;
use App\Application\Invoices\Numbering\Contracts\DocumentSequenceReserver;
use App\Application\Invoices\Numbering\Contracts\InvoiceNumberReserver;
use App\Application\Invoices\Processing\Contracts\EvidenceStore;
use App\Application\Invoices\Processing\Contracts\InvoiceProcessingRepository;
use App\Application\Issuers\Contracts\IssuerProfileProvider;
use App\Application\Issuers\Contracts\IssuerSecretProvider;
use App\Application\Issuers\Contracts\SigningCredentialsProvider;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Evidence\DiskEvidenceStore;
use App\Infrastructure\Identifiers\UuidV7Generator;
use App\Infrastructure\Issuers\JsonFileIssuerProfileProvider;
use App\Infrastructure\Issuers\Secrets\FileIssuerSecretProvider;
use App\Infrastructure\Issuers\Secrets\FileSigningCredentialsProvider;
use App\Infrastructure\Issuers\Secrets\MountedSecretFiles;
use App\Infrastructure\Persistence\EloquentDocumentSequenceReserver;
use App\Infrastructure\Persistence\EloquentInvoiceNumberReserver;
use App\Infrastructure\Persistence\EloquentInvoiceProcessingRepository;
use App\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Infrastructure\Validation\OfficialFev19DocumentValidator;
use Illuminate\Support\Facades\Storage;
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
        $this->app->bind(InvoiceNumberReserver::class, EloquentInvoiceNumberReserver::class);
        $this->app->bind(DocumentSequenceReserver::class, EloquentDocumentSequenceReserver::class);

        $this->app->singleton(IssuerProfileProvider::class, function (): JsonFileIssuerProfileProvider {
            $path = config('tribux.issuers_file');

            return new JsonFileIssuerProfileProvider(is_string($path) ? $path : null);
        });

        $this->app->singleton(MountedSecretFiles::class, function (): MountedSecretFiles {
            $path = config('tribux.secrets_path');

            return new MountedSecretFiles(is_string($path) ? $path : null);
        });

        $this->app->bind(IssuerSecretProvider::class, FileIssuerSecretProvider::class);
        $this->app->bind(SigningCredentialsProvider::class, FileSigningCredentialsProvider::class);

        $this->app->singleton(Fev19DocumentValidator::class, function (): OfficialFev19DocumentValidator {
            $toolbox = config('tribux.fev19.toolbox_path');
            $saxonHome = config('tribux.fev19.saxon_home');
            $java = config('tribux.fev19.java_binary');
            $timeout = config('tribux.fev19.schematron_timeout');

            return new OfficialFev19DocumentValidator(
                is_string($toolbox) ? $toolbox : null,
                is_string($saxonHome) ? $saxonHome : null,
                is_string($java) && $java !== '' ? $java : 'java',
                is_int($timeout) && $timeout > 0 ? $timeout : 30,
            );
        });

        $this->app->singleton(EvidenceStore::class, function (): DiskEvidenceStore {
            $disk = config('tribux.evidence.disk');

            return new DiskEvidenceStore(
                Storage::disk(is_string($disk) ? $disk : 'evidence'),
                (bool) config('tribux.evidence.store_soap_requests'),
            );
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
