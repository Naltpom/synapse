<?php

declare(strict_types=1);

namespace App\Module\Crm\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'crm_contact')]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'contacts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\Column(length: 80)]
    private string $firstName;

    #[ORM\Column(length: 80)]
    private string $lastName;

    #[ORM\Column(length: 120)]
    private string $role;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone;

    public function __construct(Client $client, string $firstName, string $lastName, string $role, string $email, ?string $phone = null)
    {
        $this->client = $client;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->role = $role;
        $this->email = $email;
        $this->phone = $phone;
        $client->addContact($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'role' => $this->role,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
