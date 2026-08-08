<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Soap;

use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Soap\DianEndpoint;
use Tribux\Dian\Soap\DianSoapMessage;
use Tribux\Dian\Soap\DianSoapOperation;
use Tribux\Dian\Soap\Transport\CurlDianSoapTransport;
use Tribux\Dian\Soap\Transport\DianTransportException;
use Tribux\Dian\Soap\Transport\DianTransportFailure;

final class CurlDianSoapTransportTest extends TestCase
{
    #[Test]
    public function it_posts_the_exact_signed_message_over_verified_tls(): void
    {
        $responseXml = '<?xml version="1.0"?><Envelope>accepted</Envelope>';

        $this->withTlsServer($responseXml, function (
            DianEndpoint $endpoint,
            string $caBundlePath,
            string $capturePath,
        ) use ($responseXml): void {
            $message = new DianSoapMessage(
                DianSoapOperation::SendTestSetAsync,
                '<?xml version="1.0"?><signed-request>unchanged</signed-request>',
            );
            $transport = new CurlDianSoapTransport(
                connectTimeoutMilliseconds: 2_000,
                requestTimeoutMilliseconds: 5_000,
                caBundlePath: $caBundlePath,
            );
            $response = $transport->send($endpoint, $message);

            self::assertSame(202, $response->statusCode);
            self::assertSame($responseXml, $response->body);
            self::assertSame(['first', 'second'], $response->header('X-Tribux-Test'));
            $capture = $this->waitForCapture($capturePath);
            self::assertSame('POST /service HTTP/1.1', $capture['request_line']);
            self::assertSame($message->contentType(), $capture['headers']['content-type']);
            self::assertSame('application/soap+xml', $capture['headers']['accept']);
            self::assertSame($message->xml, $capture['body']);
        });
    }

    #[Test]
    public function it_stops_reading_an_oversized_response(): void
    {
        $this->withTlsServer(str_repeat('x', 128), function (
            DianEndpoint $endpoint,
            string $caBundlePath,
        ): void {
            $transport = new CurlDianSoapTransport(
                connectTimeoutMilliseconds: 2_000,
                requestTimeoutMilliseconds: 5_000,
                maxResponseBytes: 16,
                caBundlePath: $caBundlePath,
            );

            try {
                $transport->send(
                    $endpoint,
                    new DianSoapMessage(DianSoapOperation::GetStatus, '<Envelope/>'),
                );
                self::fail('An oversized SOAP response must fail.');
            } catch (DianTransportException $exception) {
                self::assertSame(DianTransportFailure::ResponseTooLarge, $exception->failure);
                self::assertGreaterThan(0, $exception->transportCode);
                self::assertNotSame('', $exception->originalMessage);
            }
        });
    }

    #[Test]
    public function it_rejects_a_request_timeout_shorter_than_the_connect_timeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be shorter');

        new CurlDianSoapTransport(
            connectTimeoutMilliseconds: 5_000,
            requestTimeoutMilliseconds: 1_000,
        );
    }

    /**
     * @param callable(DianEndpoint, string, string): void $assertions
     */
    private function withTlsServer(string $response, callable $assertions): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tribux-curl-test-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            self::fail('Could not create the cURL integration test directory.');
        }

        $certificatePath = $directory.DIRECTORY_SEPARATOR.'certificate.pem';
        $privateKeyPath = $directory.DIRECTORY_SEPARATOR.'private-key.pem';
        $readyPath = $directory.DIRECTORY_SEPARATOR.'ready';
        $capturePath = $directory.DIRECTORY_SEPARATOR.'capture.json';
        $responsePath = $directory.DIRECTORY_SEPARATOR.'response.xml';
        $process = null;
        $pipes = [];

        try {
            $this->generateTlsMaterial($certificatePath, $privateKeyPath);
            self::assertSame(strlen($response), file_put_contents($responsePath, $response));
            $port = $this->availablePort();
            $process = proc_open([
                PHP_BINARY,
                __DIR__.'/../Fixtures/transport/one-shot-tls-server.php',
                (string) $port,
                $certificatePath,
                $privateKeyPath,
                $readyPath,
                $capturePath,
                $responsePath,
            ], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);

            if (! is_resource($process)) {
                self::fail('Could not start the local TLS fixture server.');
            }

            fclose($pipes[0]);
            $this->waitForFile($readyPath, $process, $pipes);
            $assertions(
                new DianEndpoint(DianEnvironment::Habilitation, 'https://localhost:'.$port.'/service'),
                $certificatePath,
                $capturePath,
            );
        } finally {
            if (is_resource($process)) {
                $status = proc_get_status($process);

                if (is_array($status) && ($status['running'] ?? false) === true) {
                    proc_terminate($process);
                }

                foreach ([1, 2] as $index) {
                    if (isset($pipes[$index]) && is_resource($pipes[$index])) {
                        fclose($pipes[$index]);
                    }
                }

                proc_close($process);
            }

            foreach ([$readyPath, $capturePath, $responsePath, $privateKeyPath, $certificatePath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    private function generateTlsMaterial(string $certificatePath, string $privateKeyPath): void
    {
        $configuration = __DIR__.'/../Fixtures/transport/tls-openssl.cnf';
        $options = [
            'config' => $configuration,
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'req_extensions' => 'test_certificate',
            'x509_extensions' => 'test_certificate',
        ];
        $key = openssl_pkey_new($options);

        if (! $key instanceof OpenSSLAsymmetricKey) {
            self::fail('Could not generate the local TLS private key.');
        }

        $requestKey = $key;
        $request = openssl_csr_new(['commonName' => 'localhost'], $requestKey, $options);

        if (! $request instanceof \OpenSSLCertificateSigningRequest) {
            self::fail('Could not generate the local TLS certificate request.');
        }

        $certificate = openssl_csr_sign($request, null, $key, 1, $options, 141421);

        if (! $certificate instanceof \OpenSSLCertificate) {
            self::fail('Could not self-sign the local TLS certificate.');
        }

        self::assertTrue(openssl_pkey_export_to_file($key, $privateKeyPath, null, $options));
        self::assertTrue(openssl_x509_export_to_file($certificate, $certificatePath));
    }

    private function availablePort(): int
    {
        $errorCode = 0;
        $errorMessage = '';
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            self::fail(sprintf('Could not allocate a test port (%d): %s', $errorCode, $errorMessage));
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        if (! is_string($address) || ! str_contains($address, ':')) {
            self::fail('Could not resolve the allocated test port.');
        }

        return (int) substr($address, strrpos($address, ':') + 1);
    }

    /**
     * @param resource $process
     * @param array<int, resource> $pipes
     */
    private function waitForFile(string $path, mixed $process, array $pipes): void
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            if (is_file($path)) {
                return;
            }

            $status = proc_get_status($process);

            if (is_array($status) && ($status['running'] ?? true) !== true) {
                $error = isset($pipes[2]) ? stream_get_contents($pipes[2]) : '';
                self::fail('TLS fixture server stopped early: '.(is_string($error) ? $error : ''));
            }

            usleep(10_000);
        }

        self::fail('TLS fixture server did not become ready.');
    }

    /** @return array{request_line:string,headers:array<string,string>,body:string} */
    private function waitForCapture(string $path): array
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $contents = is_file($path) ? file_get_contents($path) : false;

            if (is_string($contents) && $contents !== '') {
                $capture = json_decode($contents, true);
                self::assertIsArray($capture);

                return $capture;
            }

            usleep(10_000);
        }

        self::fail('TLS fixture server did not capture the request.');
    }
}
