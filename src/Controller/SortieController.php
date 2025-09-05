<?php

namespace App\Controller;

use App\Entity\Etat;
use App\Entity\Sortie;
use App\Entity\User;
use App\Form\CreateSortieType;
use App\Repository\CityRepository;
use App\Form\ModifyProfilType;
use App\Repository\SiteRepository;
use App\Repository\SortieRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/sortie', name: 'sortie_')]
final class SortieController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    #[Route('/', name: 'sortie_home', methods: ['GET'])]
    public function home(Request $request,
                         SortieRepository $repo,
                         SiteRepository $siteRepo
                        ): Response {
        $sites = $siteRepo->findAllForMenu();
        $siteId = $request->query->getInt('siteId', 0);

        if ($siteId > 0) {
            $sorties = $repo->findBySiteId($siteId);
            $last = $repo->findBy(['site' => $siteId], ['startDateTime' => 'DESC'], 6);
        } else {
            $sorties = $repo->findBy([], ['startDateTime' => 'DESC']);
            $last = $repo->findBy([], ['startDateTime' => 'DESC'], 6);
        }

        return $this->render('home/index.html.twig', [
            'last' => $last,
            'sites'        => $sites,
            'sorties'      => $sorties,
            'activeSiteId' => $siteId
            ]);

    }
    /**
     * Page "Toutes les sorties" + filtre AJAX par site (?site=Niort|Quimper|Nantes|Rennes|Ligne|all)
     */
    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(
        Request $request,
        SortieRepository $sortieRepo,
        SiteRepository $siteRepo
    ): Response {
        // 1) Lire le filtre de site (par nom) depuis l’URL
        $activeSite = $request->query->get('site', 'all');
        $siteEntity = $activeSite !== 'all' ? $siteRepo->findOneBy(['nom' => $activeSite]) : null;

        /** @var \App\Entity\User|null $me */
        $me = $this->getUser();

        // 2) Charger les sorties
        if ($siteEntity) {
            // Filtre par site sélectionné
            $sorties = $sortieRepo->findForSiteListing(
                $siteEntity,
                $me,
                false, // onlyMine
                false, // iAmRegistered
                false  // iAmNotRegistered
            );
        } else {
            // ALL → prendre toute la liste (méthode utilisé déjà sur home())
            $sorties = $sortieRepo->findBy([], ['startDateTime' => 'DESC']);

        }

        // 3) Charger la liste des sites depuis la BDD (pour les pills/menus)
        $sites = $siteRepo->findAll();

        // 4) AJAX → ne renvoie que la grille (fragment en java)
        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('sortie/_grid.html.twig', ['sorties' => $sorties]);
            return new Response($html);
        }

        // 5) Requête normale → page complète
        return $this->render('sortie/index.html.twig', [
            'sorties'    => $sorties,
            'sites'      => $sites,       // ici ce sont des entités Site complètes
            'activeSite' => $activeSite,  // ici c’est toujours le nom ou 'all'
        ]);
    }

    /**
     * Création d'une sortie
     */
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SiteRepository $siteRepo,
        CityRepository $cityRepo,
        UserRepository $userRepo
    ): Response {
        $sortie = new Sortie();

        // Fallback (roue de secours) :
        // Vérifie si la sortie a déjà un site.
        // - Si oui → on garde.
        // - Si non → on essaye d'abord "Ligne".
        // - Si "Ligne" n'existe pas → on prend le premier site dispo.
        // - Si aucun site en base → on bloque et on affiche un message.
        // => But : éviter que site_id = NULL (interdit par la BDD).
        $site = $sortie->getSite();
        if (!$site) {
            // 1) tente "Ligne".
            $site = $siteRepo->findOneBy(['nom' => 'Ligne']);
            // 2) sinon, prend le premier site existant.
            if (!$site) {
                $site = $siteRepo->findOneBy([]); // n'importe quel site
            }
            // 3) s'il n'y a vraiment aucun site, bloque proprement.
            if (!$site) {
                $this->addFlash('warning', 'No site available. Create a site before creating an exit.');
                return $this->redirectToRoute('sortie_list');
            }
            $sortie->setSite($site);
        }
        // fin du fallback.

        // un seul type de formulaire !
        $form = $this->createForm(CreateSortieType::class, $sortie);
        $user = $this->getUser();
        $u = $userRepo->findOneBy(['email' => $user->getUserIdentifier()]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sortie->setEtat(Etat::CR->value);
            $sortie->setPromoter($user); // ou setOrganisateur($user) selon ton entité
            $sortie->setSite($u->getSite());

            // Si ton formulaire crée Ville/Lieu dynamiquement :
            if ($sortie->getPlace() && $sortie->getPlace()->getCity()) {
                // Si la ville existe déja, linker la ville à la sortie
                if ($cityRepo->existsCity($sortie->getPlace()->getCity())) {
                    $city = $cityRepo->findOneBy(['name' => $sortie->getPlace()->getCity()->getName(), 'postCode' => $sortie->getPlace()->getCity()->getPostCode()]);
                    $sortie->getPlace()->setCity($city);
                }else {
                    $em->persist($sortie->getPlace()->getCity());
                }
            }
            if ($sortie->getPlace()) {
                $em->persist($sortie->getPlace());
            }

            $em->persist($sortie);
            $em->flush();

            $this->addFlash('success', 'Outing is successfully created.');

            return $this->redirectToRoute('sortie_list');
        }

        return $this->render('sortie/create.html.twig', [
            'sortie_form' => $form,
        ]);
    }
    #[Route('/myOutings', name: 'myOutings', methods: ['GET'])]
    public function myCreated(SortieRepository $sortieRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $me = $this->getUser();

        $sorties = $sortieRepo->findBy(
            ['promoter' => $me],
            ['startDateTime' => 'DESC']
        );

        return $this->render('sortie/myOutings.html.twig', [
            'sorties' => $sorties,
        ]);
    }

// MES PARTICIPATIONS
    #[Route('/participatingOutings', name: 'myParticipating', methods: ['GET'])]
    public function myParticipating(SortieRepository $sortieRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $me = $this->getUser();

        $sorties = $sortieRepo->createQueryBuilder('s')
            ->andWhere(':me MEMBER OF s.users')       // je suis inscrit
            ->setParameter('me', $me)
            ->orderBy('s.startDateTime', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('sortie/participatingOutings.html.twig', [
            'sorties' => $sorties,
        ]);
    }

    /**
     * Inscription à une sortie
     */
    #[Route('/{id<\d+>}/inscrire', name: 'subscribe', methods: ['POST'])]
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
            return $this->redirectToRoute('sortie_list');
        }

        // 2) Check si la date limite est respectée.
        if (null !== $sortie->getLimitDate() && $now > $sortie->getLimitDate()) {
            $this->addFlash('warning', 'The registration deadline has passed.');
            return $this->redirectToRoute('sortie_list');
        }

        // 3) Check des places dispo pour la sortie.
        if (null !== $sortie->getNbRegistration()
            && $sortie->getUsers()->count() >= $sortie->getNbRegistration()) {
            $this->addFlash('warning', 'Outing is full.');
            return $this->redirectToRoute('sortie_list');
        }

        // 4) Check de l'inscription ?
        if ($sortie->getUsers()->contains($user)) {
            $this->addFlash('info', 'You are already registered to this outing.');
            return $this->redirectToRoute('sortie_list');
        }

        // Validation de l'inscription
        $sortie->addUser($user);
        $em->flush();

        $this->addFlash('success', 'Subscribe registered.');
        return $this->redirectToRoute('sortie_list');
    }


    #[Route('/{id}/import-users', name: 'import_users', methods: ['POST'])]
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


    #[Route('/{id}/addUser', name: 'add_user')]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, SluggerInterface $slugger, Sortie $sortie,): Response
    {
        $user = new User();
        $user->setActif(true);
        $form = $this->createForm(ModifyProfilType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            $confirmPassword = $form->get('confirm_password')->getData();

            /** @var UploadedFile $file */
            $file = $form->get('photo')->getData();

            if ($file instanceof UploadedFile) {
                /** @var UploadedFile $brochureFile */
                $brochureFile = $form->get('photo')->getData();


                if ($brochureFile) {
                    $originalFilename = pathinfo($brochureFile->getClientOriginalName(), PATHINFO_FILENAME);

                    $safeFilename = $slugger->slug($originalFilename);

                    $uploadDir = $this->getParameter('uploads_directory');

                    $file->move($uploadDir, $safeFilename);

                    $user->setPhoto($safeFilename);
                }


            }


            if ($plainPassword) {
                if ($plainPassword === $confirmPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                } else {
                    $this->addFlash('error', 'Les mots de passe ne correspondent pas.');

                    return $this->render('sortie/addUserSortie.html.twig', [
                        'addUser' => $form,
                    ]);
                }
            }

            $entityManager->persist($user);
            $sortie->addUser($user);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur ajouté avec succès !');

            return $this->redirectToRoute('home');
        }

        return $this->render('sortie/addUserSortie.html.twig', [
            'addUser' => $form,
        ]);
    }




    /**
     * Détail d'une sortie
     */
    #[Route('/{id<\d+>}', name: 'detail', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
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

    /**
     * Archive paginée (séparée de la page liste filtrable)
     */
    #[Route('/archive/{page}', name: 'archive', requirements: ['page' => '\d+'], defaults: ['page' => 1], methods: ['GET'])]
    public function archive(
        SortieRepository $sortieRepository,
        int $page,
        ParameterBagInterface $parameters
    ): Response {
        $nbPerPage = $parameters->get('sortie')['nb_max'];

        $offset = ($page - 1) * $nbPerPage;

        $sorties = $sortieRepository->findBy(
            [],
            ['startDateTime' => 'ASC'],
            $nbPerPage,
            $offset
        );

        $total = $sortieRepository->count([]);
        $totalPages = (int) ceil($total / $nbPerPage);

        return $this->render('sortie/list.html.twig', [
            'sorties' => $sorties,
            'page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    /**
     * Profil d'un utilisateur
     */
    #[Route('/user/{id}', name: 'user_profil', methods: ['GET'])]
    public function userProfil(User $user): Response
    {
        return $this->render('sortie/userProfil.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id<\d+>}/Open', name: 'opening', methods: ['POST'])]
    public function open(Sortie $sortie, Request $request, EntityManagerInterface $em): Response
    {
        // CSRF
        if (!$this->isCsrfTokenValid('open'.$sortie->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        if ($sortie->getEtat() === Etat::CR->value) {
            $sortie->setEtat(Etat::OU->value);
            $this->addFlash('success', 'Outing successfully Opened !');
            $em->flush();
            return $this->redirectToRoute('sortie_detail', ['id' => $sortie->getId()]);
        }
        $this->addFlash('danger', 'No Sortie Selected or Invalid Status');

        return $this->redirectToRoute('sortie_list');
    }

    #[Route('/{id<\d+>}/Canceling', name: 'canceling', methods: ['POST'])]
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

    #[Route('/{id<\d+>}/desister', name: 'desister', methods: ['POST'])]
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
            $this->addFlash('warning', 'This outing has already started, withdrawal is not possible.');
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
