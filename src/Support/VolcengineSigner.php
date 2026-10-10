<?php

namespace Caiyun\Sms\Support;

use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

/** @internal Signs SMS requests using Volcengine's HMAC-SHA256 protocol. */
final class VolcengineSigner
{
    public function sign(
        RequestInterface $request,
        string $accessKeyId,
        string $secretAccessKey,
        string $region,
        string $securityToken = '',
        ?DateTimeImmutable $time = null,
    ): RequestInterface {
        $date = ($time ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $payloadHash = Utils::hash($request->getBody(), 'sha256');
        $request = $request->withHeader('X-Date', $date)->withHeader('X-Content-Sha256', $payloadHash);
        $headers = [
            'host' => $request->getHeaderLine('Host'),
            'x-content-sha256' => $payloadHash,
            'x-date' => $date,
        ];

        if ($securityToken !== '') {
            $request = $request->withHeader('X-Security-Token', $securityToken);
            $headers['x-security-token'] = $securityToken;
        }

        $canonicalHeaders = '';

        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name.':'.preg_replace('/\s+/', ' ', trim($value))."\n";
        }

        $query = Query::parse($request->getUri()->getQuery());
        ksort($query);
        $signedHeaders = implode(';', array_keys($headers));
        $path = '/'.str_replace('%2F', '/', rawurlencode(ltrim($request->getUri()->getPath(), '/')));
        $canonicalRequest = $request->getMethod()."\n".$path."\n"
            .http_build_query($query, '', '&', PHP_QUERY_RFC3986)."\n"
            .$canonicalHeaders."\n".$signedHeaders."\n".$payloadHash;
        $shortDate = substr($date, 0, 8);
        // SMS uses the case-sensitive service name "volcSMS", not "sms".
        $scope = $shortDate.'/'.$region.'/volcSMS/request';
        $stringToSign = "HMAC-SHA256\n".$date."\n".$scope."\n".hash('sha256', $canonicalRequest);
        $signingKey = hash_hmac('sha256', $shortDate, $secretAccessKey, true);

        foreach ([$region, 'volcSMS', 'request'] as $value) {
            $signingKey = hash_hmac('sha256', $value, $signingKey, true);
        }

        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        return $request->withHeader('Authorization', 'HMAC-SHA256 Credential='.$accessKeyId.'/'.$scope
            .', SignedHeaders='.$signedHeaders.', Signature='.$signature);
    }
}
