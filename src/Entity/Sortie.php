<?php

namespace App\Entity;

use App\Repository\SortieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SortieRepository::class)]
class Sortie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column]
    private ?\DateTime $startDateTime = null;

    #[ORM\Column]
    private ?int $duration = null;

    #[ORM\Column]
    private ?\DateTime $limitDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbRegistration = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $info = null;

    #[ORM\Column(length: 255)]
    #[Assert\Choice(choices: [Etat::CR, Etat::OU, Etat::AN, Etat::CL, Etat::EC, Etat::PA], message: 'Ce choix n\'est pas valable')]
    private ?string $etat = null;


    public function getId(): ?int
    {
        return $this->id;
    }


    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getStartDateTime(): ?\DateTime
    {
        return $this->startDateTime;
    }

    public function setStartDateTime(\DateTime $startDateTime): static
    {
        $this->startDateTime = $startDateTime;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getLimitDate(): ?\DateTime
    {
        return $this->limitDate;
    }

    public function setLimitDate(\DateTime $limitDate): static
    {
        $this->limitDate = $limitDate;

        return $this;
    }

    public function getNbRegistration(): ?int
    {
        return $this->nbRegistration;
    }

    public function setNbRegistration(?int $nbRegistration): static
    {
        $this->nbRegistration = $nbRegistration;

        return $this;
    }

    public function getInfo(): ?string
    {
        return $this->info;
    }

    public function setInfo(?string $info): static
    {
        $this->info = $info;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(?string $etat): void
    {
        $this->etat = $etat;
    }


}
