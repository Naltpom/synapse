<?php

declare(strict_types=1);

namespace App\Module\Crm\Entity;

use App\Module\Crm\Enum\ClientStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'crm_client')]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 80)]
    private string $sector;

    #[ORM\Column(length: 80)]
    private string $city;

    #[ORM\Column(length: 20, enumType: ClientStatus::class)]
    private ClientStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Contact> */
    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'client', cascade: ['persist', 'remove'])]
    private Collection $contacts;

    /** @var Collection<int, Opportunity> */
    #[ORM\OneToMany(targetEntity: Opportunity::class, mappedBy: 'client', cascade: ['persist', 'remove'])]
    private Collection $opportunities;

    public function __construct(string $name, string $sector, string $city, ClientStatus $status)
    {
        $this->name = $name;
        $this->sector = $sector;
        $this->city = $city;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->contacts = new ArrayCollection();
        $this->opportunities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSector(): string
    {
        return $this->sector;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getStatus(): ClientStatus
    {
        return $this->status;
    }

    public function setStatus(ClientStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Contact> */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): self
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
        }

        return $this;
    }

    /** @return Collection<int, Opportunity> */
    public function getOpportunities(): Collection
    {
        return $this->opportunities;
    }

    public function addOpportunity(Opportunity $opportunity): self
    {
        if (!$this->opportunities->contains($opportunity)) {
            $this->opportunities->add($opportunity);
        }

        return $this;
    }
}
