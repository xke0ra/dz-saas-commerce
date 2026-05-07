<?php

use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;

it('trusts forwarded proto only from configured proxies for secure request detection', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.10']);

    $trustedResponse = proxyAwareSecurityResponse(Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.10',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]));

    expect($trustedResponse->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains');

    SymfonyRequest::setTrustedProxies([], 0);

    $untrustedResponse = proxyAwareSecurityResponse(Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]));

    expect($untrustedResponse->headers->has('Strict-Transport-Security'))->toBeFalse();
});

function proxyAwareSecurityResponse(Request $request): Response
{
    return app(TrustProxies::class)->handle(
        $request,
        fn (Request $trustedRequest): Response => app(AddSecurityHeaders::class)->handle(
            $trustedRequest,
            fn (): Response => response('ok'),
        ),
    );
}
