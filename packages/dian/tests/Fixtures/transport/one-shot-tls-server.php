<?php

declare(strict_types=1);

[$script, $port, $certificatePath, $privateKeyPath, $readyPath, $capturePath, $responsePath] = $argv;

$context = stream_context_create(['ssl' => [
    'allow_self_signed' => true,
    'local_cert' => $certificatePath,
    'local_pk' => $privateKeyPath,
    'verify_peer' => false,
]]);
$errorCode = 0;
$errorMessage = '';
$server = stream_socket_server(
    'tls://127.0.0.1:'.$port,
    $errorCode,
    $errorMessage,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context,
);

if ($server === false) {
    fwrite(STDERR, sprintf('TLS server error %d: %s', $errorCode, $errorMessage));
    exit(1);
}

file_put_contents($readyPath, 'ready');
$connection = stream_socket_accept($server, 10);

if ($connection === false) {
    fwrite(STDERR, 'TLS server did not receive a connection.');
    fclose($server);
    exit(2);
}

stream_set_timeout($connection, 5);
$requestLine = fgets($connection);
$headers = [];

while (($line = fgets($connection)) !== false) {
    $trimmed = trim($line);

    if ($trimmed === '') {
        break;
    }

    if (str_contains($trimmed, ':')) {
        [$name, $value] = explode(':', $trimmed, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }
}

$contentLength = isset($headers['content-length']) ? (int) $headers['content-length'] : 0;
$body = '';

while (strlen($body) < $contentLength && ! feof($connection)) {
    $chunk = fread($connection, $contentLength - strlen($body));

    if ($chunk === false || $chunk === '') {
        break;
    }

    $body .= $chunk;
}

file_put_contents($capturePath, json_encode([
    'request_line' => is_string($requestLine) ? trim($requestLine) : '',
    'headers' => $headers,
    'body' => $body,
], JSON_THROW_ON_ERROR));
$response = file_get_contents($responsePath);

if (! is_string($response)) {
    fwrite(STDERR, 'TLS response fixture is unreadable.');
    fclose($connection);
    fclose($server);
    exit(3);
}

$head = implode("\r\n", [
    'HTTP/1.1 202 Accepted',
    'Content-Type: application/soap+xml; charset=utf-8',
    'X-Tribux-Test: first',
    'X-Tribux-Test: second',
    'Content-Length: '.strlen($response),
    'Connection: close',
    '',
    '',
]);
fwrite($connection, $head.$response);
fclose($connection);
fclose($server);
