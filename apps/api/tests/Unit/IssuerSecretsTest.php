<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Issuers\Exceptions\SecretNotAvailable;
use App\Application\Issuers\IssuerSecrets;
use App\Infrastructure\Issuers\Secrets\FileIssuerSecretProvider;
use App\Infrastructure\Issuers\Secrets\FileSigningCredentialsProvider;
use App\Infrastructure\Issuers\Secrets\MountedSecretFiles;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Support\EphemeralCertificate;

final class IssuerSecretsTest extends TestCase
{
    private const string PIN = 'pin-value-never-logged';

    private const string TECHNICAL_KEY = 'technical-key-never-logged';

    private const string CERTIFICATE_PASSWORD = 'certificate-password-never-logged';

    private string $secretsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretsPath = (string) tempnam(sys_get_temp_dir(), 'tribux-secrets-');
        unlink($this->secretsPath);
        mkdir($this->secretsPath.'/habilitation-primary', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->secretsPath);

        parent::tearDown();
    }

    public function test_it_reads_the_pin_and_technical_key_from_a_mount(): void
    {
        // Editors and `echo` append a newline; a secret must survive that.
        $this->writeSecret('software_pin', self::PIN."\n");
        $this->writeSecret('technical_key', self::TECHNICAL_KEY."\r\n");

        $secrets = $this->secretProvider()->forReference('habilitation-primary');

        self::assertSame(self::PIN, $secrets->softwarePin());
        self::assertSame(self::TECHNICAL_KEY, $secrets->technicalKey());
    }

    public function test_a_missing_secret_names_the_reference_but_not_a_value(): void
    {
        $this->writeSecret('software_pin', self::PIN);

        try {
            $this->secretProvider()->forReference('habilitation-primary');
            self::fail('A missing technical key must not resolve.');
        } catch (SecretNotAvailable $exception) {
            self::assertStringContainsString('technical_key', $exception->getMessage());
            self::assertStringContainsString('habilitation-primary', $exception->getMessage());
            self::assertStringNotContainsString(self::PIN, $exception->getMessage());
        }
    }

    public function test_an_unconfigured_mount_points_at_the_environment_variable(): void
    {
        $provider = new FileIssuerSecretProvider(new MountedSecretFiles(null));

        $this->expectException(SecretNotAvailable::class);
        $this->expectExceptionMessage('TRIBUX_SECRETS_PATH');

        $provider->forReference('habilitation-primary');
    }

    public function test_it_refuses_a_credential_reference_that_escapes_the_mount(): void
    {
        $this->expectException(SecretNotAvailable::class);
        $this->expectExceptionMessage('not a safe path segment');

        $this->secretProvider()->forReference('../../etc');
    }

    public function test_secrets_cannot_be_serialized_into_a_job_payload(): void
    {
        $secrets = new IssuerSecrets(self::PIN, self::TECHNICAL_KEY);

        self::assertSame('{}', (string) json_encode($secrets));
        self::assertStringNotContainsString(self::PIN, print_r($secrets, true));
        self::assertStringNotContainsString(self::TECHNICAL_KEY, print_r($secrets, true));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must not be serialized');

        serialize($secrets);
    }

    public function test_it_loads_signing_credentials_from_a_pkcs12_mount(): void
    {
        $certificate = EphemeralCertificate::create();
        $this->writeSecret('certificate.p12', $certificate->pkcs12(self::CERTIFICATE_PASSWORD));
        $this->writeSecret('certificate_password', self::CERTIFICATE_PASSWORD."\n");

        $credentials = $this->signingProvider()->forReference('habilitation-primary');

        self::assertCount(1, $credentials->signingCertificatePath());
        self::assertNotSame('', $credentials->signSha256('payload'));
    }

    public function test_it_loads_signing_credentials_from_pem_files(): void
    {
        $certificate = EphemeralCertificate::create();
        $this->writeSecret('certificate.pem', $certificate->certificatePem);
        $this->writeSecret('private_key.pem', $certificate->privateKeyPem);
        $this->writeSecret('chain.pem', $certificate->certificatePem);

        $credentials = $this->signingProvider()->forReference('habilitation-primary');

        self::assertCount(2, $credentials->signingCertificatePath());
    }

    public function test_a_wrong_certificate_password_is_reported_without_echoing_it(): void
    {
        $certificate = EphemeralCertificate::create();
        $this->writeSecret('certificate.p12', $certificate->pkcs12(self::CERTIFICATE_PASSWORD));
        $this->writeSecret('certificate_password', 'the-wrong-password');

        try {
            $this->signingProvider()->forReference('habilitation-primary');
            self::fail('An invalid PKCS#12 password must not resolve.');
        } catch (SecretNotAvailable $exception) {
            self::assertStringContainsString('unusable', $exception->getMessage());
            self::assertStringNotContainsString('the-wrong-password', $exception->getMessage());
        }
    }

    public function test_a_key_that_does_not_match_the_certificate_is_rejected(): void
    {
        $certificate = EphemeralCertificate::create();
        $other = EphemeralCertificate::create();
        $this->writeSecret('certificate.pem', $certificate->certificatePem);
        $this->writeSecret('private_key.pem', $other->privateKeyPem);

        $this->expectException(SecretNotAvailable::class);
        $this->expectExceptionMessage('does not match the signing certificate');

        $this->signingProvider()->forReference('habilitation-primary');
    }

    private function secretProvider(): FileIssuerSecretProvider
    {
        return new FileIssuerSecretProvider(new MountedSecretFiles($this->secretsPath));
    }

    private function signingProvider(): FileSigningCredentialsProvider
    {
        return new FileSigningCredentialsProvider(new MountedSecretFiles($this->secretsPath));
    }

    private function writeSecret(string $name, string $contents): void
    {
        file_put_contents($this->secretsPath.'/habilitation-primary/'.$name, $contents);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path.DIRECTORY_SEPARATOR.$entry;
            is_dir($child) ? $this->removeDirectory($child) : unlink($child);
        }

        rmdir($path);
    }
}
