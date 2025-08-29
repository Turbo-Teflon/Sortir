<?php

namespace App\Entity;

use App\Repository\SortieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'sortiesOrganised')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $organisateur = null;


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
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'outing_user')]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    /** @return Collection<int, User> */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        $this->users->removeElement($user);

        return $this;
    }

    // Helpers sortie vers site (Méthode utilitaire qui permet d'encapsuler une règle métier pour la rendre réutilisable).
    public function hasStarted(): bool
    {

        return $this->getStartDateTime() <= new \DateTimeImmutable();
    }

    public function isRegistrationOpen(): bool
    {
        $now = new \DateTimeImmutable();

        // nbRegistration = capacité max (si null, on considère pas de limite)
        $hasCapacity = $this->getNbRegistration() === null
            || $this->getusers()->count() < $this->getNbRegistration();

        // Ouverture  seulement limite pas dépassé et s'il reste de la place.

        $limitOk = $this->getLimitDate() === null || $this->getLimitDate() >= $now;

        // tient compte de l’état : OU = Ouverte, CR = Créée CL = cloturé
        $etatOk = in_array($this->getEtat(), [Etat::OU, Etat::CR, Etat::CL], true);

        return $limitOk && $hasCapacity && $etatOk;
    }

    public function reopenIfPossibleAfterWithdrawal(): void
    {
        // Si la sortie était "Clôturée" car pleine, on peut la rouvrir.
        if ($this->getEtat() === Etat::CL && $this->isRegistrationOpen()) {
            $this->setEtat(Etat::OU);
        }
    }
    #[ORM\ManyToOne(targetEntity: Site::class, inversedBy: 'sorties')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Site $site = null;

    public function getSite(): ?Site { return $this->site; }
    public function setSite(?Site $site): self { $this->site = $site; return $this; }


public function getOrganisateur(): ?User
{
    return $this->organisateur;
}

public function setOrganisateur(?User $organisateur): self
{
    $this->organisateur = $organisateur;

    return $this;
}
}

