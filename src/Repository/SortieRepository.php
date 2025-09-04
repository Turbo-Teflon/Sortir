<?php

namespace App\Repository;

use App\Entity\Site;
use App\Entity\Sortie;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sortie>
 */
class SortieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sortie::class);
    }
    /**
     * @return Sortie[]
     */
    /*version enum:

    enum ViewMode { case ALL; case MINE; case REGISTERED; case NOT_REGISTERED; }
    public function findForSiteListing(?Site $site, ?Utilisateur $me, ViewMode $mode): array
    ?objet $me devient ?User $me*/

    public function findForSiteListing(
        ?Site $site,
        ?object $me, // Participant/User
        bool $onlyMine,
        bool $iAmRegistered,
        bool $iAmNotRegistered
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.site', 'site')->addSelect('site')
            ->leftJoin('s.promoter', 'orga')->addSelect('orga')
            ->leftJoin('s.users', 'insc')->addSelect('insc')
            // adapte si tes valeurs d’énum diffèrent
            ->andWhere('s.etat IN (:publiees)')
            ->setParameter('publiees', [\App\Entity\Etat::OU->value, \App\Entity\Etat::CL->value, \App\Entity\Etat::EC->value ?? 'AC'])
            ->orderBy('s.startDateTime', 'ASC');

        if ($site) {
            $qb->andWhere('s.site = :site')->setParameter('site', $site);
        }

        if ($me) {
            if ($onlyMine) {
                $qb->andWhere('s.promoter = :me')->setParameter('me', $me);
            }
            // “Je suis / ne suis pas inscrit”
            $qb->leftJoin('s.users', 'my', 'WITH', 'my = :me2')->setParameter('me2', $me);
            if ($iAmRegistered && !$iAmNotRegistered) {
                $qb->andWhere(':me2 MEMBER OF s.users');
            } elseif (!$iAmRegistered && $iAmNotRegistered) {
                $qb->andWhere(':me2 NOT MEMBER OF s.users');
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function countUserInSortie(Sortie $sortie): int
    {
        $sql = 'SELECT * FROM outing_user o WHERE o.sortie_id = :sortie_id';
        return $this->getEntityManager()->createQuery($sql)
            ->setParameter('sortie_id', $sortie->getId())
            ->getResult()
            ->count();
    }

//    /**
//     * @return Sortie[] Returns an array of Sortie objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Sortie
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
