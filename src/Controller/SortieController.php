<?php

namespace App\Controller;

use App\Entity\Etat;
use App\Entity\Sortie;
use App\Entity\User;
use App\Form\CreateSortieType;
use App\Repository\SiteRepository;
use App\Repository\SortieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/sortie', name: 'sortie')]
final class SortieController extends AbstractController
{
    #[Route('/', name: '_home')]
    public function index(): Response
    {
        return $this->render('sortie/index.html.twig', [
            'controller_name' => 'SortieController',
        ]);
    }

    #[Route('/create', name: '_create')]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SiteRepository $siteRepo): Response
    {
        $sortie = new Sortie();

        // site par défaut "Ligne"
        if (null === $sortie->getSite()) {
            $defaultSite = $siteRepo->findOneBy(['nom' => 'Ligne']);
            if ($defaultSite) {
                $sortie->setSite($defaultSite);
            }
        }

        // un seul type de formulaire !
        $form = $this->createForm(CreateSortieType::class, $sortie);
        $user = $this->getUser();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sortie->setEtat(Etat::CR->value);
            $sortie->setPromoter($user);
            $em->persist($sortie->getPlace()->getCity());
            $em->persist($sortie->getPlace());
            $em->persist($sortie);
            $em->flush();

            $this->addFlash('success', 'Outing is successfully created.');

            return $this->redirectToRoute('sortie_home');
        }

        return $this->render('sortie/create.html.twig', [
            'sortie_form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/inscrire', name: '_inscrire', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function subscribe(
        Sortie $sortie,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        // Token CSRF (Cross Site Request Forgery)
        if (!$this->isCsrfTokenValid('subscribe'.$sortie->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $user = $this->getUser();
        $now = new \DateTime();

        // 1) Check si les inscriptions sont ouvertes.
        if ($sortie->getEtat() !== Etat::OU->value) {
            $this->addFlash('warning', 'Registration is not open for this outing.');

            return $this->redirectToRoute('sortie_home');
        }

        // 2) Check si la date limite est respectée.
        if (null !== $sortie->getLimitDate() && $now > $sortie->getLimitDate()) {
            $this->addFlash('warning', 'The registration deadline has passed.');

            return $this->redirectToRoute('sortie_home');
        }

        // 3) Check des places dispo pour la sortie.
        if (null !== $sortie->getNbRegistration()
            && $sortie->getUsers()->count() >= $sortie->getNbRegistration()) {
            $this->addFlash('warning', 'Outing is full.');

            return $this->redirectToRoute('sortie_home');
        }

        // 4) Check de l'inscription ?
        if ($sortie->getUsers()->contains($user)) {
            $this->addFlash('info', 'You are already registered to this outing.');

            return $this->redirectToRoute('sortie_home');
        }

        // Validation de l'inscription
        $sortie->addUser($user);
        $em->flush();

        $this->addFlash(
            'success',
            'Inscription à l\'activité "'.$sortie->getNom().'" enregistrée.'
        );

        return $this->redirectToRoute('sortie_list');
    }

    #[Route('/{id}/import-users', name: '_import_users', methods: ['POST'])]
    public function importUsersToSortie(
        Request $request,
        Sortie $sortie,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        SerializerInterface $serializer
    ): Response {
        $file = $request->files->get('csvFile');


        if ($file) {
            $csvContent = file_get_contents($file->getPathname());

            if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
                $this->addFlash('warning', 'Le fichier doit être un CSV.');
                return $this->redirectToRoute('sortie_detail', ['id' => $sortie->getId()]);
            }

            /** @var User[] $users */
            $context = [
                'csv_delimiter' => ';',
            ];
            $users = $serializer->deserialize($csvContent, User::class.'[]', 'csv',$context);

            foreach ($users as $user) {
                $email = $user->getEmail();
                if (!str_ends_with($email, '@campus-eni.fr')) {
                    continue;
                }
                $userExist = $em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
                if (!$userExist) {
                    $user->setPassword(
                        $passwordHasher->hashPassword($user, $user->getPassword())
                    );
                    $em->persist($user);
                    $sortie->addUser($user);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Les utilisateurs du CSV ont été inscrits à la sortie.');
        } else {
            $this->addFlash('warning', 'Aucun fichier CSV fourni.');
        }

        return $this->redirectToRoute('sortie_list', ['id' => $sortie->getId()]);
    }


    #[Route('/{id<\d+>}', name: '_detail', methods: ['GET'])]
    public function show(Sortie $sortie): Response
    {
        $u = $this->getUser()->getUserIdentifier();
        $isPromoter = $u === $sortie->getPromoter()->getUserIdentifier();

        return $this->render('sortie/detail.html.twig', [
            'sortie' => $sortie,
            'ETAT_OU' => Etat::OU->value,
            'user' => $u,
            'isPromoter' => $isPromoter,
        ]);
    }

    #[Route('/list/{page}', name: '_list', requirements: ['page' => '\d+'], defaults: ['page' => 1], methods: ['GET'])]
    public function list(SortieRepository $sortieRepository, int $page, ParameterBagInterface $parameters): Response
    {
        $nbPerPage = $parameters->get('sortie')['nb_max'];

        $offset = ($page - 1) * $nbPerPage;

        $sorties = $sortieRepository->findBy(
            [],
            ['startDateTime' => 'ASC'],
            $nbPerPage,
            $offset
        );

        $total = $sortieRepository->count([]);
        $totalPages = ceil($total / $nbPerPage);

        return $this->render('Sortie/list.html.twig', [
            'sorties' => $sorties,
            'page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/user/{id}', name: '_user_profil', methods: ['GET'])]
    public function userProfil(User $user): Response
    {
        return $this->render('sortie/userProfil.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id<\d+>}/Canceling', name: '_canceling', methods: ['POST'])]
    public function cancel(Sortie $sortie, Request $request, EntityManagerInterface $em): Response
    {
        // CSRF
        if (!$this->isCsrfTokenValid('cancel'.$sortie->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        if ($sortie->getEtat() === Etat::CR->value) {
            $em->remove($sortie);
            $this->addFlash('warning', 'Outing is removed from the DB');
            $em->flush();
            return $this->redirectToRoute('sortie_list');
        } else {
            $sortie->setEtat(Etat::AN->value);
            $em->persist($sortie);
            $this->addFlash('success', 'Outing is successfully cancelled.');
        }

        $em->flush();

        return $this->redirectToRoute('sortie_detail', ['id' => $sortie->getId()]);
    }

    #[Route('/{id<\d+>}/desister', name: '_desister', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function desister(
        Sortie $sortie,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        // CSRF
        if (!$this->isCsrfTokenValid('desister'.$sortie->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }
        $user = $this->getUser();
        $now = new \DateTimeImmutable();

        // 1) La sortie ne doit pas avoir commencé.
        if (null !== $sortie->getStartDateTime() && $sortie->getStartDateTime() <= $now) {
            $this->addFlash('warning', 'La sortie a déjà débuté, désistement impossible.');

            return $this->redirectToRoute('sortie_detail', ['id' => $sortie->getId()]);
        }

        // 2) Il faut être inscrit.
        if (!$sortie->getUsers()->contains($user)) {
            $this->addFlash('info', 'You don\'t subscribe to this outing.');

            return $this->redirectToRoute('sortie_detail', ['id' => $sortie->getId()]);
        }

        // 3) Se désinscrire.
        $sortie->removeUser($user);

        /* 4) Si la sortie était pleine et donc "Clôturée",
            on la remet "Ouverte" seulement si la date limite d’inscription n’est pas dépassée,
           et s’il reste de la place.
        */
        $limitOk = null === $sortie->getLimitDate() || $sortie->getLimitDate() >= $now;
        $hasCapacity = null === $sortie->getNbRegistration()
            || $sortie->getUsers()->count() < $sortie->getNbRegistration();

        if ($sortie->getEtat() === Etat::CL->value && $limitOk && $hasCapacity) {
            $sortie->setEtat(Etat::OU->value);
        }

        $em->flush();

        $this->addFlash('success', 'You have withdrawn. The spot will become available again if registration is still open.');

        return $this->redirectToRoute('sortie_detail', ['id' => $sortie->getId()]);
    }

    #[Route('/list', name: '_by_site', methods: ['GET'])]
    public function bySite(
        Request $req,
        SortieRepository $sortieRepo,
        SiteRepository $siteRepo,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Paramètres GET
        $siteId = $req->query->getInt('site', 0);
        $onlyMine = (bool) $req->query->get('onlyMine', false);
        $iAmRegistered = (bool) $req->query->get('iAmRegistered', false);
        $iAmNotRegistered = (bool) $req->query->get('iAmNotRegistered', false);

        $site = $siteId ? $siteRepo->find($siteId) : null;
        $me = $this->getUser();

        // Limite aux 4 sites (Nantes, Niort, Rennes, Quimper)
        $sites = $siteRepo->createQueryBuilder('s')
            ->andWhere('s.nom IN (:allowed)')
            ->setParameter('allowed', ['Nantes', 'Niort', 'Rennes', 'Quimper'])
            ->orderBy('s.nom', 'ASC')
            ->getQuery()->getResult();

        $sorties = $sortieRepo->findForSiteListing($site, $me, $onlyMine, $iAmRegistered, $iAmNotRegistered);

        return $this->render('sortie/by_site.html.twig', [
            'sites' => $sites,
            'current_site' => $site,
            'sorties' => $sorties,
            'filters' => [
                'onlyMine' => $onlyMine,
                'iAmRegistered' => $iAmRegistered,
                'iAmNotRegistered' => $iAmNotRegistered,
            ],
        ]);
    }
}
