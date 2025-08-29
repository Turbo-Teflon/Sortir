<?php

namespace App\DataFixtures;

use App\Entity\Site;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SiteFixtures extends Fixture
{
    public function load(ObjectManager $em): void
    {
        foreach (['Nantes','Niort','Rennes','Quimper', 'Ligne'] as $n) {
            $em->persist((new Site())->setNom($n));
        }
        $em->flush();
    }
}
