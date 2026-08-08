<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Issuers\Exceptions\IssuerConfigurationInvalid;
use App\Application\Issuers\Exceptions\IssuerNotConfigured;
use App\Application\Issuers\IssuerProfileFactory;
use App\Infrastructure\Issuers\JsonFileIssuerProfileProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tribux\Core\Decimal\DecimalRoundingMode;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Submission\Fev19\Fev19SequenceEncoding;

final class IssuerProfileTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->temporaryFiles = [];

        parent::tearDown();
    }

    public function test_it_loads_the_published_example_issuer(): void
    {
        $profile = (new JsonFileIssuerProfileProvider($this->examplePath()))->get('issuer_demo');

        self::assertSame('issuer_demo', $profile->reference);
        self::assertSame(DianEnvironment::Habilitation, $profile->environment);
        self::assertSame('SETP', $profile->control->prefix);
        self::assertSame('900123456', $profile->supplier->identification);
        self::assertSame('001', $profile->softwareProviderCode);
        self::assertSame('America/Bogota', $profile->timezone->getName());
        self::assertSame(2, $profile->calculationPolicy->moneyScale);
        self::assertSame(DecimalRoundingMode::HalfUp, $profile->calculationPolicy->roundingMode);
        self::assertSame('habilitation-primary', $profile->credentialReference);
        self::assertSame(Fev19SequenceEncoding::Decimal, $profile->fileSequenceEncoding);
        self::assertTrue($profile->allowsUnitCode('94'));
        self::assertFalse($profile->allowsUnitCode('EA'));
        self::assertSame('VAT', $profile->taxMappings[0]->coreTaxType);
        self::assertSame('01', $profile->taxMappings[0]->dianId);
    }

    public function test_the_published_example_declares_no_secret_fields(): void
    {
        $forbidden = ['pin', 'password', 'passphrase', 'technical_key', 'private_key', 'certificate', 'secret'];

        foreach ($this->keysOf($this->exampleData()) as $key) {
            self::assertNotContains(
                $key,
                $forbidden,
                sprintf('The example issuer file must not declare a "%s" field.', $key),
            );
        }
    }

    public function test_an_unknown_issuer_names_the_configuration_source(): void
    {
        $provider = new JsonFileIssuerProfileProvider($this->examplePath());

        $this->expectException(IssuerNotConfigured::class);
        $this->expectExceptionMessage('issuer.habilitation.json');

        $provider->get('issuer_unknown');
    }

    public function test_an_unconfigured_file_points_at_the_environment_variable(): void
    {
        $provider = new JsonFileIssuerProfileProvider(null);

        $this->expectException(IssuerNotConfigured::class);
        $this->expectExceptionMessage('TRIBUX_ISSUERS_FILE');

        $provider->get('issuer_demo');
    }

    public function test_a_missing_file_is_reported_as_configuration(): void
    {
        $provider = new JsonFileIssuerProfileProvider(__DIR__.'/does-not-exist.json');

        $this->expectException(IssuerConfigurationInvalid::class);
        $this->expectExceptionMessage('does not exist or cannot be read');

        $provider->get('issuer_demo');
    }

    public function test_malformed_json_is_reported_without_echoing_the_file(): void
    {
        $provider = new JsonFileIssuerProfileProvider($this->temporaryFile('{"issuer_demo": '));

        $this->expectException(IssuerConfigurationInvalid::class);
        $this->expectExceptionMessage('does not contain a JSON object');

        $provider->get('issuer_demo');
    }

    public function test_an_invalid_field_names_the_issuer_and_the_path(): void
    {
        $data = $this->exampleData();
        unset($data['issuer_demo']['supplier']['address']['municipality_code']);

        $provider = new JsonFileIssuerProfileProvider(
            $this->temporaryFile((string) json_encode($data, JSON_THROW_ON_ERROR)),
        );

        $this->expectException(IssuerConfigurationInvalid::class);
        $this->expectExceptionMessage('Issuer "issuer_demo"');
        $this->expectExceptionMessage('supplier.address.municipality_code must be a string');

        $provider->get('issuer_demo');
    }

    public function test_it_rejects_a_calculation_policy_fev_19_cannot_use(): void
    {
        $data = $this->exampleData();
        $data['issuer_demo']['calculation']['money_scale'] = 4;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('two-decimal calculation policy');

        (new IssuerProfileFactory)->fromArray('issuer_demo', $data['issuer_demo']);
    }

    public function test_it_rejects_a_provider_code_that_is_not_three_digits(): void
    {
        $data = $this->exampleData();
        $data['issuer_demo']['software']['provider_code'] = '1';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly three digits');

        (new IssuerProfileFactory)->fromArray('issuer_demo', $data['issuer_demo']);
    }

    public function test_it_rejects_a_supplier_identification_carrying_the_check_digit(): void
    {
        $data = $this->exampleData();
        $data['issuer_demo']['supplier']['identification'] = '9001234568';
        $data['issuer_demo']['supplier']['identification'] .= '9';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('without the check digit');

        (new IssuerProfileFactory)->fromArray('issuer_demo', $data['issuer_demo']);
    }

    public function test_it_rejects_an_unknown_environment(): void
    {
        $data = $this->exampleData();
        $data['issuer_demo']['environment'] = 'sandbox';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('environment must be one of');

        (new IssuerProfileFactory)->fromArray('issuer_demo', $data['issuer_demo']);
    }

    public function test_it_rejects_duplicate_tax_mappings(): void
    {
        $data = $this->exampleData();
        $data['issuer_demo']['tax_mappings'][] = ['core_type' => 'VAT', 'dian_id' => '04', 'dian_name' => 'INC'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate issuer tax mapping for core type VAT');

        (new IssuerProfileFactory)->fromArray('issuer_demo', $data['issuer_demo']);
    }

    public function test_the_file_sequence_encoding_must_be_stated_not_inherited(): void
    {
        // Q-008 leaves the annex and its own example disagreeing, so there is
        // deliberately no default to fall back on.
        $data = $this->exampleData();
        unset($data['issuer_demo']['file_sequence_encoding']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('file_sequence_encoding must be a string');

        (new IssuerProfileFactory)->fromArray('issuer_demo', $data['issuer_demo']);
    }

    public function test_it_rejects_an_unknown_file_sequence_encoding(): void
    {
        $data = $this->exampleData();
        $data['issuer_demo']['file_sequence_encoding'] = 'octal';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('file_sequence_encoding must be one of');

        (new IssuerProfileFactory)->fromArray('issuer_demo', $data['issuer_demo']);
    }

    public function test_a_missing_test_set_id_fails_loudly_only_when_requested(): void
    {
        $data = $this->exampleData();
        unset($data['issuer_demo']['test_set_id']);

        $profile = (new IssuerProfileFactory)->fromArray('issuer_demo', $data['issuer_demo']);

        self::assertNull($profile->testSetId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no testSetId configured');

        $profile->testSetId();
    }

    private function examplePath(): string
    {
        return __DIR__.'/../../../../examples/issuer.habilitation.json';
    }

    /** @return array<string, array<string, mixed>> */
    private function exampleData(): array
    {
        /** @var array<string, array<string, mixed>> $decoded */
        $decoded = json_decode((string) file_get_contents($this->examplePath()), true, 32, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private function keysOf(array $data): array
    {
        $keys = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }

            if (is_array($value)) {
                $keys = [...$keys, ...$this->keysOf($value)];
            }
        }

        return $keys;
    }

    private function temporaryFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'tribux-issuer-');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
