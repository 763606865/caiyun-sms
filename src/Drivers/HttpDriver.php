<?php

namespace Caiyun\Sms\Drivers;

use Caiyun\Sms\Contracts\SmsSender;
use Caiyun\Sms\Exceptions\SmsException;
use Caiyun\Sms\SmsResult;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Throwable;

/** @internal Shared transport for the HTTP-based providers. */
abstract class HttpDriver implements SmsSender
{
    protected const NAME = '';

    /** @var list<string> */
    protected const REQUIRED_CONFIG = [];

    private readonly ClientInterface $client;

    /** @param array<string, mixed> $config */
    public function __construct(protected readonly array $config, ?ClientInterface $client = null)
    {
        foreach (static::REQUIRED_CONFIG as $key) {
            if (! isset($config[$key]) || ! is_scalar($config[$key]) || trim((string) $config[$key]) === '') {
                throw new SmsException('Missing '.static::NAME." SMS configuration: {$key}.");
            }
        }

        $this->client = $client ?? new Client;
    }

    final public function send(string $mobile, string $template, array $parameters = []): SmsResult
    {
        try {
            $response = $this->client->send($this->buildRequest($mobile, $template, $parameters), [
                'http_errors' => false,
                'allow_redirects' => false,
                'timeout' => (float) ($this->config['timeout'] ?? 10),
                'connect_timeout' => (float) ($this->config['connect_timeout'] ?? 5),
            ]);
            $raw = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($raw)) {
                throw new SmsException(static::NAME.' SMS returned an invalid response.');
            }

            // Parse business errors even when the provider returns a non-2xx status.
            $result = $this->parseResponse($raw);
            $status = $response->getStatusCode();

            if ($status < 200 || $status >= 300) {
                throw new SmsException(static::NAME." SMS request failed with HTTP status {$status}.");
            }

            return $result;
        } catch (SmsException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SmsException(static::NAME.' SMS request failed.', previous: $exception);
        }
    }

    /** @param array<array-key, scalar|null> $parameters */
    abstract protected function buildRequest(string $mobile, string $template, array $parameters): RequestInterface;

    /** @param array<string, mixed> $raw */
    abstract protected function parseResponse(array $raw): SmsResult;

    protected function endpoint(string $default = ''): string
    {
        $endpoint = rtrim((string) ($this->config['endpoint'] ?? $default), '/');
        $uri = new Uri($endpoint);

        if ($uri->getScheme() !== 'https' || $uri->getHost() === '' || $uri->getUserInfo() !== ''
            || $uri->getPath() !== '' || $uri->getQuery() !== '' || $uri->getFragment() !== '') {
            throw new SmsException(static::NAME.' SMS endpoint must be an HTTPS base URL without a path or query.');
        }

        return $endpoint;
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     * @return array<array-key, string>
     */
    protected function stringParameters(array $parameters): array
    {
        return array_map(static function ($value): string {
            if (! is_scalar($value) && $value !== null) {
                throw new SmsException('SMS template parameters must contain scalar values or null.');
            }

            return (string) $value;
        }, $parameters);
    }
}
