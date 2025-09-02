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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
            $last = $repo->findLastBySiteId($siteId, 6);
        } else {
            $sorties = $repo->findAllOrdered();
            $last = $repo->findLast(6);
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
        $activeSite = $request->query->get('site', 'all');
        $siteEntity = $activeSite !== 'all' ? $siteRepo->findOneBy(['nom' => $activeSite]) : null;

        /** @var \App\Entity\User|null $me */
        $me = $this->getUser();

        $sorties = $sortieRepo->findForSiteListing(
            $siteEntity,
            $me,
            false, // onlyMine
            false, // iAmRegistered
            false  // iAmNotRegistered
        );

        // 👉 Ici on ne fait plus de tableau en dur ni de array_map : juste un findAll
        $sites = $siteRepo->findAll();

        // Requête AJAX → renvoie seulement la grille (fragment)
        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('sortie/_grid.html.twig', ['sorties' => $sorties]);
            return new Response($html);
        }

        // Requête normale → page complète
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
        SiteRepository $siteRepo
    ): Response {
        $sortie = new Sortie();

        // Site par défaut "Ligne" si absent
        if (null === $sortie->getSite()) {
            $defaultSite = $siteRepo->findOneBy(['nom' => 'Ligne']);
            if ($defaultSite) {
                $sortie->setSite($defaultSite);
            }
        }

        $form = $this->createForm(CreateSortieType::class, $sortie);
        $user = $this->getUser();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sortie->setEtat(Etat::CR->value);
            $sortie->setPromoter($user); // ou setOrganisateur($user) selon ton entité

            // Si ton formulaire crée Ville/Lieu dynamiquement :
            if ($sortie->getPlace() && $sortie->getPlace()->getCity()) {
                $em->persist($sortie->getPlace()->getCity());
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

    /**
     * Inscription à une sortie
     */
    #[Route('/{id<\d+>}/inscrire', name: 'subscribe', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function subscribe(
        Sortie $sortie,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // CSRF
        if (!$this->isCsrfTokenValid('subscribe'.$sortie->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $user = $this->getUser();
        $now = new \DateTime();

        // 1) Inscriptions ouvertes ?
        if ($sortie->getEtat() !== Etat::OU->value) {
            $this->addFlash('warning', 'Registration is not open for this outing.');
            return $this->redirectToRoute('sortie_list');
        }

        // 2) Date limite OK ?
        if (null !== $sortie->getLimitDate() && $now > $sortie->getLimitDate()) {
            $this->addFlash('warning', 'The registration deadline has passed.');
            return $this->redirectToRoute('sortie_list');
        }

        // 3) Capacité non atteinte ?
        if (null !== $sortie->getNbRegistration()
            && $sortie->getUsers()->count() >= $sortie->getNbRegistration()) {
            $this->addFlash('warning', 'Outing is full.');
            return $this->redirectToRoute('sortie_list');
        }

        // 4) Pas déjà inscrit ?
        if ($sortie->getUsers()->contains($user)) {
            $this->addFlash('info', 'You are already registered to this outing.');
            return $this->redirectToRoute('sortie_list');
        }

        // Validation
        $sortie->addUser($user);
        $em->flush();

        $this->addFlash('success', 'Subscribe registered.');
        return $this->redirectToRoute('sortie_list');
    }

    /**
     * Détail d'une sortie
     */
    #[Route('/{id<\d+>}', name: 'detail', methods: ['GET'])]
    public function show(Sortie $sortie): Response
    {
        return $this->render('sortie/detail.html.twig', [
            'sortie' => $sortie,
            'ETAT_OU' => Etat::OU->value,
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

    /**
     * Désistement
     */
    #[Route('/{id<\d+>}/desister', name: 'desister', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function desister(
        Sortie $sortie,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // CSRF
        if (!$this->isCsrfTokenValid('desister'.$sortie->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $user = $this->getUser();
        $now = new \DateTimeImmutable();

        // 1) La sortie ne doit pas avoir commencé
        if (null !== $sortie->getStartDateTime() && $sortie->getStartDateTime() <= $now) {
            $this->addFlash('warning', 'This outing has already started, withdrawal is not possible.');
            return $this->redirectToRoute('sortie_detail', ['id' => $sortie->getId()]);
        }

        // 2) Il faut être inscrit
        if (!$sortie->getUsers()->contains($user)) {
            $this->addFlash('info', 'You are not registered to this outing.');
            return $this->redirectToRoute('sortie_detail', ['id' => $sortie->getId()]);
        }

        // 3) Se désinscrire
        $sortie->removeUser($user);

        // 4) Si la sortie était "Clôturée" et que les conditions le permettent, on la rouvre
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
}
