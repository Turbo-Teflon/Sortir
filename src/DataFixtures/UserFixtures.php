<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // --- Admin ---
        $admin = new User();
        $admin->setNom('Admin');
        $admin->setPrenom('Système');
        $admin->setTelephone('0600000000');
        $admin->setEmail('admin@campus-eni.fr'); // <-- pour respecter la contrainte de domaine
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setActif(true);
        $admin->setPseudo($faker->userName);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // --- Création de 10 utilisateurs aléatoires ---
        for ($i = 0; $i < 10; ++$i) {
            $u = new User();

            $prenom = $faker->firstName();
            $nom = $faker->lastName();

            // helper pour avoir un email ASCII (retire les caractères spéciaux dans le mail, voir la function plus bas) + unique @campus-eni.fr
            $localPart = $this->toAscii(strtolower($prenom.'.'.$nom));
            $email = $localPart.'_'.$i.'@campus-eni.fr'; // suffixe pour garantir l'unicité des mails.

            $u->setNom($nom);
            $u->setPrenom($prenom);
            $u->setTelephone($faker->numerify('06########'));
            $u->setEmail($email);
            $u->setRoles(['ROLE_USER']); // pas de création d'admin dans l'aléatoire.
            $u->setActif($faker->boolean(95)); // plupart actifs.
            $u->setPseudo($faker->userName);
            $u->setPassword($this->hasher->hashPassword($u, 'password')); // mot de passe de démo

            $manager->persist($u);
        }

        $manager->flush();
    }

    private function toAscii(string $s): string
    {
        // enlève les accents et caractères spéciaux pour un mail propre.
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^A-Za-z0-9\.]+/', '.', $s); // garde lettres/chiffres/points

        return trim($s, '.');
    }
}
