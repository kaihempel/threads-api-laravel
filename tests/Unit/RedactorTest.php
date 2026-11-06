<?php

declare(strict_types=1);

use KaiHempel\Threads\Support\Redactor;

it('removes known secrets', function () {
    $redactor = new Redactor(['my-secret-token', null, 'abc']);

    expect($redactor->redact('token my-secret-token and '.rawurlencode('my-secret-token')))->toBe('token *** and ***')
        ->and($redactor->redact('abc is too short to be treated as secret'))->toBe('abc is too short to be treated as secret');
});

it('masks sensitive query and json parameters', function () {
    $redactor = new Redactor;

    expect($redactor->redact('https://graph.threads.net/v1.0/me?fields=id&access_token=XYZ123&x=1'))
        ->toBe('https://graph.threads.net/v1.0/me?fields=id&access_token=***&x=1')
        ->and($redactor->redact('client_secret=s3cr3t&code=c0de'))->toBe('client_secret=***&code=***')
        ->and($redactor->redact('{"access_token":"XYZ","error_code":5}'))->toBe('{"access_token":"***","error_code":5}')
        ->and($redactor->redact('error_code=5'))->toBe('error_code=5')
        ->and($redactor->redact(null))->toBeNull()
        ->and($redactor->redact(''))->toBe('');
});

it('can be extended with more secrets', function () {
    expect((new Redactor(['first-secret']))->with(['second-secret'])->redact('first-secret second-secret'))->toBe('*** ***');
});
