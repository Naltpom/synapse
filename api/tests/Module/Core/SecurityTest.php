<?php

declare(strict_types=1);

namespace App\Tests\Module\Core;

use App\Tests\ApiTestCase;

final class SecurityTest extends ApiTestCase
{
    public function testApiRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/dashboard');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Authentification requise.', $this->json()['error']);
    }

    public function testLoginRejectsBadCredentials(): void
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'email' => 'direction@synapse.demo',
            'password' => 'mauvais-mot-de-passe',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginOpensSessionAndExposesProfile(): void
    {
        $this->login();

        $this->client->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
        $me = $this->json();
        self::assertSame('direction@synapse.demo', $me['email']);
        self::assertContains('ROLE_ADMIN', $me['roles']);
    }

    public function testLoginAttemptsAreAudited(): void
    {
        $this->login();
        $this->client->request('GET', '/api/audit');

        self::assertResponseIsSuccessful();
        $actions = array_column($this->json(), 'action');
        self::assertContains('login', $actions);
    }
}
