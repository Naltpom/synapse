<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Module\Billing\Entity\Invoice;
use App\Module\Billing\Enum\InvoiceStatus;
use App\Module\Core\Entity\AuditLog;
use App\Module\Core\Entity\User;
use App\Module\Core\Enum\Practice;
use App\Module\Crm\Entity\Client;
use App\Module\Crm\Entity\Contact;
use App\Module\Crm\Entity\Opportunity;
use App\Module\Crm\Enum\ClientStatus;
use App\Module\Crm\Enum\OpportunityStage;
use App\Module\Project\Entity\Project;
use App\Module\Project\Enum\ProjectHealth;
use App\Module\Project\Enum\ProjectStatus;
use App\Module\Staffing\Entity\Assignment;
use App\Module\Staffing\Entity\Consultant;
use App\Module\Staffing\Entity\Mission;
use App\Module\Staffing\Enum\ConsultantGrade;
use App\Module\Staffing\Enum\MissionStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de démonstration : 100 % fictif, généré avec une graine fixe
 * pour être reproductible. Aucune donnée réelle de Synetis ou de ses clients.
 */
final class AppFixtures extends Fixture
{
    private const DEMO_PASSWORD = 'Synapse!2026';

    private Generator $faker;

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
        $this->faker = Factory::create('fr_FR');
        $this->faker->seed(20260708);
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadUsers($manager);
        $clients = $this->loadClients($manager);
        // Flush intermédiaire : les modules aval référencent les clients par identifiant.
        $manager->flush();

        $this->loadOpportunities($manager, $clients);
        $consultants = $this->loadConsultants($manager);
        $missions = $this->loadMissions($manager, $clients);
        // Idem pour les identifiants de missions (projets, factures).
        $manager->flush();

        $this->loadAssignments($manager, $missions, $consultants);
        $this->loadProjects($manager, $missions);
        $this->loadInvoices($manager, $clients, $missions);
        $this->loadAuditSeed($manager);

        $manager->flush();
    }

    private function loadUsers(ObjectManager $manager): void
    {
        $users = [
            ['direction@synapse.demo', 'Claire Morel', 'Directrice Générale', ['ROLE_ADMIN']],
            ['staffing@synapse.demo', 'Thomas Leroy', 'Responsable Staffing', ['ROLE_MANAGER']],
            ['commerce@synapse.demo', 'Sofia Berrada', 'Account Manager', []],
        ];

        foreach ($users as [$email, $fullName, $jobTitle, $roles]) {
            $user = new User($email, $fullName, $jobTitle);
            $user->setRoles($roles);
            $user->setPassword($this->hasher->hashPassword($user, self::DEMO_PASSWORD));
            $manager->persist($user);
        }
    }

    /** @return list<Client> */
    private function loadClients(ObjectManager $manager): array
    {
        $definitions = [
            ['Banque Héliard', 'Banque', 'Paris', ClientStatus::Actif],
            ['Groupe Vertane', 'Retail', 'Lille', ClientStatus::Actif],
            ['Santéo Mutuelle', 'Assurance', 'Nantes', ClientStatus::Actif],
            ['TransLog Atlantique', 'Logistique', 'Bordeaux', ClientStatus::Actif],
            ['Éditions Novapresse', 'Média', 'Paris', ClientStatus::Actif],
            ['CleanNRJ', 'Énergie', 'Lyon', ClientStatus::Actif],
            ['Hôpitaux de l\'Estuaire', 'Santé', 'Le Havre', ClientStatus::Actif],
            ['AeroPièces SAS', 'Industrie', 'Toulouse', ClientStatus::Actif],
            ['Coopérative Terroria', 'Agroalimentaire', 'Rennes', ClientStatus::Actif],
            ['Zelia Paiements', 'Fintech', 'Paris', ClientStatus::Actif],
            ['Métropole de Valdour', 'Secteur public', 'Valdour', ClientStatus::Prospect],
            ['Assurances Prévia', 'Assurance', 'Strasbourg', ClientStatus::Prospect],
            ['DataCampus', 'Éducation', 'Grenoble', ClientStatus::Prospect],
            ['SilverStone Conseil', 'Conseil', 'Paris', ClientStatus::Inactif],
        ];

        $roles = ['DSI', 'RSSI', 'DAF', 'Responsable Conformité', 'DPO'];
        $clients = [];
        foreach ($definitions as [$name, $sector, $city, $status]) {
            $client = new Client($name, $sector, $city, $status);
            $manager->persist($client);

            $contactCount = $this->faker->numberBetween(1, 3);
            for ($i = 0; $i < $contactCount; ++$i) {
                $firstName = $this->faker->firstName();
                $lastName = $this->faker->lastName();
                $manager->persist(new Contact(
                    $client,
                    $firstName,
                    $lastName,
                    (string) $this->faker->randomElement($roles),
                    strtolower($this->transliterate($firstName).'.'.$this->transliterate($lastName)).'@'.$this->domainFor($name),
                    $this->faker->phoneNumber(),
                ));
            }

            $clients[] = $client;
        }

        return $clients;
    }

    /** @param list<Client> $clients */
    private function loadOpportunities(ObjectManager $manager, array $clients): void
    {
        $catalogue = [
            ['Audit PASSI annuel', Practice::AuditSsi, 45000],
            ['Pentest applicatif e-commerce', Practice::AuditSsi, 22000],
            ['Exercice Red Team', Practice::AuditSsi, 68000],
            ['SOC managé 24/7', Practice::Cyberdefense, 240000],
            ['Réponse à incident — retainer CERT', Practice::Cyberdefense, 85000],
            ['Threat Intelligence sectorielle', Practice::Cyberdefense, 36000],
            ['Mise en conformité NIS2', Practice::Grc, 95000],
            ['Accompagnement DORA', Practice::Grc, 78000],
            ['Analyse de risques EBIOS RM', Practice::Grc, 32000],
            ['Déploiement IAM Okta', Practice::IdentiteNumerique, 160000],
            ['Gouvernance des identités Sailpoint', Practice::IdentiteNumerique, 120000],
            ['Bastion comptes à privilèges', Practice::IdentiteNumerique, 90000],
            ['Migration PKI d\'entreprise', Practice::SecuriteOperationnelle, 56000],
            ['Durcissement Microsoft 365', Practice::SecuriteOperationnelle, 41000],
            ['Sécurisation Active Directory', Practice::SecuriteOperationnelle, 62000],
            ['Audit de configuration cloud', Practice::AuditSsi, 28000],
            ['Programme de sensibilisation phishing', Practice::Grc, 18000],
            ['Supervision EDR managée', Practice::Cyberdefense, 110000],
        ];

        $owners = ['Sofia Berrada', 'Marc Antona', 'Julie Vasseur'];
        $stages = [
            [OpportunityStage::Qualification, 20],
            [OpportunityStage::Qualification, 25],
            [OpportunityStage::Proposition, 45],
            [OpportunityStage::Proposition, 55],
            [OpportunityStage::Negociation, 70],
            [OpportunityStage::Negociation, 80],
            [OpportunityStage::Gagnee, 100],
            [OpportunityStage::Perdue, 0],
        ];

        foreach ($catalogue as $i => [$title, $practice, $amount]) {
            [$stage, $probability] = $stages[$i % count($stages)];
            $client = $clients[$i % count($clients)];
            $manager->persist(new Opportunity(
                $client,
                $title,
                $practice,
                (string) $amount,
                $stage,
                $probability,
                new \DateTimeImmutable('today +'.$this->faker->numberBetween(10, 180).' days'),
                (string) $this->faker->randomElement($owners),
            ));
        }
    }

    /** @return list<Consultant> */
    private function loadConsultants(ObjectManager $manager): array
    {
        $skillsByPractice = [
            Practice::AuditSsi->value => ['Pentest web', 'Pentest interne', 'Red Team', 'OSINT', 'Audit de code', 'Burp Suite'],
            Practice::Cyberdefense->value => ['SOC', 'CERT', 'Forensic', 'Splunk', 'Sentinel', 'CTI', 'EDR'],
            Practice::Grc->value => ['EBIOS RM', 'ISO 27001', 'NIS2', 'DORA', 'PSSI', 'Audit organisationnel'],
            Practice::IdentiteNumerique->value => ['Okta', 'Sailpoint', 'CyberArk', 'Entra ID', 'Keycloak', 'MFA'],
            Practice::SecuriteOperationnelle->value => ['PKI', 'Microsoft 365', 'Active Directory', 'Durcissement', 'Zscaler', 'Intune'],
        ];

        $distribution = [
            [Practice::AuditSsi, 6],
            [Practice::Cyberdefense, 7],
            [Practice::Grc, 5],
            [Practice::IdentiteNumerique, 6],
            [Practice::SecuriteOperationnelle, 4],
        ];

        $grades = [
            [ConsultantGrade::Junior, 550],
            [ConsultantGrade::Confirme, 750],
            [ConsultantGrade::Senior, 950],
            [ConsultantGrade::Manager, 1200],
        ];

        $consultants = [];
        foreach ($distribution as [$practice, $count]) {
            for ($i = 0; $i < $count; ++$i) {
                [$grade, $baseRate] = $grades[$this->faker->numberBetween(0, 3)];
                $firstName = $this->faker->firstName();
                $lastName = $this->faker->lastName();
                /** @var list<string> $skills */
                $skills = $this->faker->randomElements($skillsByPractice[$practice->value], $this->faker->numberBetween(2, 4));

                $consultant = new Consultant(
                    $firstName,
                    $lastName,
                    strtolower($this->transliterate($firstName).'.'.$this->transliterate($lastName)).'@synapse.demo',
                    $practice,
                    $grade,
                    $baseRate + $this->faker->numberBetween(-5, 5) * 10,
                    array_values($skills),
                    new \DateTimeImmutable('today -'.$this->faker->numberBetween(90, 2600).' days'),
                );
                $manager->persist($consultant);
                $consultants[] = $consultant;
            }
        }

        return $consultants;
    }

    /**
     * @param list<Client> $clients
     *
     * @return list<Mission>
     */
    private function loadMissions(ObjectManager $manager, array $clients): array
    {
        $catalogue = [
            ['Audit PASSI', Practice::AuditSsi, MissionStatus::EnCours, 40],
            ['Pentest annuel multi-applications', Practice::AuditSsi, MissionStatus::EnCours, 25],
            ['Exercice Red Team', Practice::AuditSsi, MissionStatus::AVenir, 30],
            ['SOC managé — run', Practice::Cyberdefense, MissionStatus::EnCours, 220],
            ['Réponse à incident ransomware', Practice::Cyberdefense, MissionStatus::Terminee, 35],
            ['Déploiement supervision EDR', Practice::Cyberdefense, MissionStatus::EnCours, 60],
            ['Programme NIS2', Practice::Grc, MissionStatus::EnCours, 80],
            ['Accompagnement DORA', Practice::Grc, MissionStatus::EnCours, 65],
            ['Analyse de risques EBIOS RM', Practice::Grc, MissionStatus::Terminee, 20],
            ['Déploiement IAM Okta — build', Practice::IdentiteNumerique, MissionStatus::EnCours, 140],
            ['Gouvernance identités — cadrage', Practice::IdentiteNumerique, MissionStatus::AVenir, 30],
            ['Bastion PAM CyberArk', Practice::IdentiteNumerique, MissionStatus::EnCours, 90],
            ['Migration PKI', Practice::SecuriteOperationnelle, MissionStatus::EnCours, 55],
            ['Durcissement M365', Practice::SecuriteOperationnelle, MissionStatus::Terminee, 28],
            ['Sécurisation Active Directory', Practice::SecuriteOperationnelle, MissionStatus::EnCours, 45],
            ['Audit de configuration cloud', Practice::AuditSsi, MissionStatus::AVenir, 18],
        ];

        $activeClients = array_values(array_filter($clients, static fn (Client $c): bool => ClientStatus::Actif === $c->getStatus()));

        $missions = [];
        foreach ($catalogue as $i => [$title, $practice, $status, $budgetDays]) {
            $client = $activeClients[$i % count($activeClients)];
            [$start, $end] = match ($status) {
                MissionStatus::EnCours => [
                    new \DateTimeImmutable('today -'.$this->faker->numberBetween(30, 150).' days'),
                    new \DateTimeImmutable('today +'.$this->faker->numberBetween(30, 200).' days'),
                ],
                MissionStatus::AVenir => [
                    new \DateTimeImmutable('today +'.$this->faker->numberBetween(15, 60).' days'),
                    new \DateTimeImmutable('today +'.$this->faker->numberBetween(90, 240).' days'),
                ],
                MissionStatus::Terminee => [
                    new \DateTimeImmutable('today -'.$this->faker->numberBetween(200, 400).' days'),
                    new \DateTimeImmutable('today -'.$this->faker->numberBetween(20, 120).' days'),
                ],
            };

            $mission = new Mission(
                $client->getId() ?? 0,
                $client->getName(),
                $title,
                $practice,
                $status,
                $start,
                $end,
                $budgetDays,
            );
            $manager->persist($mission);
            $missions[] = $mission;
        }

        return $missions;
    }

    /**
     * @param list<Mission>    $missions
     * @param list<Consultant> $consultants
     */
    private function loadAssignments(ObjectManager $manager, array $missions, array $consultants): void
    {
        // ~85 % de staffing : les 4 derniers consultants restent volontairement en intercontrat.
        $staffable = array_slice($consultants, 0, count($consultants) - 4);

        $activeMissions = array_values(array_filter(
            $missions,
            static fn (Mission $m): bool => MissionStatus::Terminee !== $m->getStatus(),
        ));

        // Chaque consultant staffable reçoit au moins une affectation, puis on densifie les équipes.
        foreach ($staffable as $i => $consultant) {
            $this->assign($manager, $activeMissions[$i % count($activeMissions)], $consultant);
        }
        foreach ($activeMissions as $i => $mission) {
            if (0 === $i % 2) {
                $this->assign($manager, $mission, $staffable[($i * 5) % count($staffable)]);
            }
        }
    }

    private function assign(ObjectManager $manager, Mission $mission, Consultant $consultant): void
    {
        $manager->persist(new Assignment(
            $mission,
            $consultant,
            new \DateTimeImmutable('today -'.$this->faker->numberBetween(10, 90).' days'),
            new \DateTimeImmutable('today +'.$this->faker->numberBetween(20, 180).' days'),
            (int) $this->faker->randomElement([50, 60, 80, 80, 100, 100]),
            600 + $this->faker->numberBetween(0, 60) * 10,
        ));
    }

    /** @param list<Mission> $missions */
    private function loadProjects(ObjectManager $manager, array $missions): void
    {
        $managers = ['Thomas Leroy', 'Nadia Cheriet', 'Paul Grangier'];
        $milestones = [
            'Restitution d\'audit',
            'Recette module IAM',
            'Go-live SOC',
            'Comité de pilotage',
            'Livraison rapport final',
            'Bascule production',
            'Atelier de cadrage n°3',
        ];

        $healthCycle = [
            ProjectHealth::Vert, ProjectHealth::Vert, ProjectHealth::Vert,
            ProjectHealth::Orange, ProjectHealth::Vert, ProjectHealth::Vert,
            ProjectHealth::Orange, ProjectHealth::Rouge, ProjectHealth::Vert, ProjectHealth::Vert,
        ];

        $i = 0;
        foreach ($missions as $mission) {
            if (MissionStatus::EnCours !== $mission->getStatus() || $i >= 10) {
                continue;
            }

            $manager->persist(new Project(
                $mission->getId(),
                $mission->getTitle(),
                $mission->getClientName(),
                (string) $this->faker->randomElement($managers),
                $this->faker->numberBetween(15, 90),
                $healthCycle[$i % count($healthCycle)],
                (string) $this->faker->randomElement($milestones),
                new \DateTimeImmutable('today +'.$this->faker->numberBetween(10, 90).' days'),
                ProjectStatus::EnCours,
            ));
            ++$i;
        }
    }

    /**
     * @param list<Client>  $clients
     * @param list<Mission> $missions
     */
    private function loadInvoices(ObjectManager $manager, array $clients, array $missions): void
    {
        $activeClients = array_values(array_filter($clients, static fn (Client $c): bool => ClientStatus::Actif === $c->getStatus()));
        $labels = [
            'Prestation de conseil — %s',
            'Forfait mission — %s',
            'Régie mensuelle — %s',
            'Jalon contractuel — %s',
        ];

        $today = new \DateTimeImmutable('today');
        $number = 1;
        // 12 mois d'historique, 3 à 5 factures par mois.
        for ($monthOffset = 11; $monthOffset >= 0; --$monthOffset) {
            $monthStart = new \DateTimeImmutable("first day of -{$monthOffset} months");
            $invoiceCount = $this->faker->numberBetween(3, 5);

            for ($i = 0; $i < $invoiceCount; ++$i) {
                $client = $activeClients[$this->faker->numberBetween(0, count($activeClients) - 1)];
                $mission = $missions[$this->faker->numberBetween(0, count($missions) - 1)];
                $issuedAt = $monthStart->modify('+'.$this->faker->numberBetween(0, 25).' days');
                $dueAt = $issuedAt->modify('+30 days');

                if ($dueAt < $today->modify('-15 days')) {
                    // Factures anciennes : payées à ~92 %, le reste en retard.
                    $status = $this->faker->numberBetween(1, 100) <= 92 ? InvoiceStatus::Payee : InvoiceStatus::EnRetard;
                } elseif ($issuedAt > $today->modify('-10 days')) {
                    $status = $this->faker->boolean(30) ? InvoiceStatus::Brouillon : InvoiceStatus::Envoyee;
                } else {
                    $status = InvoiceStatus::Envoyee;
                }

                $manager->persist(new Invoice(
                    sprintf('FAC-%s-%04d', $issuedAt->format('Y'), $number++),
                    $client->getId() ?? 0,
                    $client->getName(),
                    $this->faker->boolean(70) ? $mission->getId() : null,
                    sprintf((string) $this->faker->randomElement($labels), $client->getName()),
                    (string) ($this->faker->numberBetween(8, 95) * 1000),
                    '20.00',
                    $status,
                    $issuedAt,
                    $dueAt,
                    InvoiceStatus::Payee === $status ? $dueAt->modify('-'.$this->faker->numberBetween(2, 20).' days') : null,
                ));
            }
        }
    }

    /** Quelques entrées d'audit pour que l'écran ne soit pas vide au premier lancement. */
    private function loadAuditSeed(ObjectManager $manager): void
    {
        $entries = [
            ['direction@synapse.demo', 'login', 'Session', null],
            ['commerce@synapse.demo', 'login', 'Session', null],
            ['commerce@synapse.demo', 'create', 'Client', '11'],
            ['commerce@synapse.demo', 'update', 'Opportunity', '4'],
            ['staffing@synapse.demo', 'login', 'Session', null],
        ];

        foreach ($entries as [$actor, $action, $type, $subjectId]) {
            $manager->persist(new AuditLog($actor, $action, $type, $subjectId, null, '10.0.0.'.$this->faker->numberBetween(2, 40)));
        }
    }

    private function transliterate(string $value): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return preg_replace('/[^a-zA-Z]/', '', $converted ?: $value) ?? $value;
    }

    private function domainFor(string $companyName): string
    {
        return strtolower($this->transliterate(str_replace(' ', '', $companyName))).'.example';
    }
}
