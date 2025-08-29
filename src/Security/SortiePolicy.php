<?php
namespace App\Security;

use App\Entity\Etat;
use App\Entity\Sortie;
use App\Entity\User;


final class SortiePolicy
{
    /** Peut-on se désister ? */
    public function canWithdraw(User $user, Sortie $sortie, \DateTimeImmutable $now): bool
    {
        // doit être inscrit
        if (!$sortie->getUsers()->contains($user)) {
            return false;
        }

        // pas l’organisateur
        if ($user === $sortie->getOrganisateur()) {
            return false;
        }

        // pas commencé
        if ($sortie->getStartDateTime() && $sortie->getStartDateTime() <= $now) {
            return false;
        }

        // états autorisés
        $allowed = [Etat::OU, Etat::CL];
        if (!in_array($sortie->getEtat(), $allowed, true)) {
            return false;
        }

        return true;
    }

    /** Doit-on rouvrir après désistement ? (ta règle actuelle) */
    public function shouldReopenAfterWithdraw(Sortie $sortie, \DateTimeImmutable $now): bool
    {
        // seulement si c’était clos
        if ($sortie->getEtat() !== Etat::CL) {
            return false;
        }

        $limitOk = !$sortie->getLimitDate() || $sortie->getLimitDate() >= $now;
        $hasCapacity = !$sortie->getNbRegistration() || $sortie->getUsers()->count() < $sortie->getNbRegistration();

        return $limitOk && $hasCapacity;
    }
}

