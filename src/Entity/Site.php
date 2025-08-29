<?php

namespace App\Entity;

use App\Repository\SiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\OneToMany(mappedBy: 'site', targetEntity: Sortie::class)]
    private Collection $sorties;

    public function __construct()
    {
        $this->sorties = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->getNom();
    }

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

    /** @return Collection<int, Sortie> */
    public function getSorties(): Collection
    {
        return $this->sorties;
    }

    // Helpers côté inverse site vers sortie
    public function addSortie(Sortie $sortie): static
    {
        if (!$this->sorties->contains($sortie)) {
            $this->sorties->add($sortie);
            $sortie->setSite($this);
        }
        return $this;
    }

    public function removeSortie(Sortie $sortie): static
    {
        if ($this->sorties->removeElement($sortie)) {
            if ($sortie->getSite() === $this) {
                $sortie->setSite(null);
            }
        }
        return $this;
    }
}
