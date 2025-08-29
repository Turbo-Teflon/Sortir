<?php

namespace App\Controller;

use App\Entity\Etat;
use App\Entity\Sortie;
use App\Form\CreateSortieType;
use App\Repository\SiteRepository;
use App\Repository\SortieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $sortie = new Sortie();
        $form = $this->createForm(CreateSortieType::class, $sortie);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $sortie->setEtat(Etat::CR->value);
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
            $this->addFlash('warning', "The registration deadline has passed.");

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

        $this->addFlash('success', 'You have subscribe');

        return $this->redirectToRoute('sortie_home');
    }

    #[Route('/{id<\d+>}', name: '_detail', methods: ['GET'])]
    public function show(Sortie $sortie): Response
    {
        return $this->render('sortie/detail.html.twig', [
            'sortie' => $sortie,
            'ETAT_OU' => Etat::OU->value,
        ]);
    }
    #[Route('/{id<\d+>}/desister', name: '_desister', methods: ['POST'])]
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
        $now  = new \DateTimeImmutable();

        // 1) La sortie ne doit pas avoir commencé.
        if ($sortie->getStartDateTime() !== null && $sortie->getStartDateTime() <= $now) {
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
        $limitOk = $sortie->getLimitDate() === null || $sortie->getLimitDate() >= $now;
        $hasCapacity = $sortie->getNbRegistration() === null
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
        SiteRepository $siteRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Paramètres GET
        $siteId          = $req->query->getInt('site', 0);
        $onlyMine        = (bool)$req->query->get('onlyMine', false);
        $iAmRegistered   = (bool)$req->query->get('iAmRegistered', false);
        $iAmNotRegistered= (bool)$req->query->get('iAmNotRegistered', false);

        $site    = $siteId ? $siteRepo->find($siteId) : null;
        $me      = $this->getUser();

        // Limite aux 4 sites (Nantes, Niort, Rennes, Quimper)
        $sites = $siteRepo->createQueryBuilder('s')
            ->andWhere('s.nom IN (:allowed)')
            ->setParameter('allowed', ['Nantes','Niort','Rennes','Quimper'])
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
