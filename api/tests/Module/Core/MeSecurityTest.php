<?php

declare(strict_types=1);

namespace App\Tests\Module\Core;

use App\Tests\ApiTestCase;

final class MeSecurityTest extends ApiTestCase
{
    public function testRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/me/security');

        self::assertResponseStatusCodeSame(401);
    }

    public function testShowsOnlyOwnLoginHistory(): void
    {
        // Une tentative échouée puis une connexion réussie pour alimenter l'historique.
        $this->client->jsonRequest('POST', '/api/login', ['email' => 'commerce@synapse.demo', 'password' => 'faux']);
        $this->login('commerce@synapse.demo');

        $this->client->request('GET', '/api/me/security');

        self::assertResponseIsSuccessful();
        $data = $this->json();

        self::assertSame('commerce@synapse.demo', $data['session']['email']);
        self::assertNotEmpty($data['logins']);
        $actions = array_column($data['logins'], 'action');
        self::assertContains('login', $actions);
        self::assertContains('login_failure', $actions);

        foreach ($data['logins'] as $entry) {
            self::assertSame('commerce@synapse.demo', $entry['actor'], 'Jamais l\'historique des autres comptes.');
        }
    }
}
