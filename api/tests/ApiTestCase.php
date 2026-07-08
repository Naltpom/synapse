<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Socle des tests d'API : base SQLite de test recréée et re-seedée une fois par processus.
 */
abstract class ApiTestCase extends WebTestCase
{
    private static bool $schemaReady = false;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->prepareDatabase();
    }

    private function prepareDatabase(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $hasher = $container->get(UserPasswordHasherInterface::class);
        $executor = new ORMExecutor($em, new ORMPurger($em));
        $executor->execute([new AppFixtures($hasher)]);

        self::$schemaReady = true;
    }

    protected function login(string $email = 'direction@synapse.demo', string $password = 'Synapse!2026'): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => $email, 'password' => $password]);
        self::assertResponseIsSuccessful();
    }

    /** @return array<mixed> */
    protected function json(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
