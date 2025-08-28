<?php

namespace App\Controller;

use App\Entity\Etat;
use App\Entity\Sortie;
use App\Form\CreateSortieType;
use App\Repository\SortieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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

            $this->addFlash('success', 'Sortie crée avec succes');

            return $this->redirectToRoute('sortie_home');
        }

        return $this->render('sortie/create.html.twig', [
            'sortie_form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/inscrire', name: '_inscrire', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function inscrire(
        Sortie $sortie,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        // Token CSRF (Cross Site Request Forgery)
        if (!$this->isCsrfTokenValid('inscrire'.$sortie->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $user = $this->getUser();
        $now = new \DateTime();

        // 1) Check si les inscriptions sont ouvertes.
        if ($sortie->getEtat() !== Etat::OU->value) {
            $this->addFlash('warning', 'Les inscriptions ne sont pas ouvertes pour cette sortie.');

            return $this->redirectToRoute('sortie_home');
        }

        // 2) Check si la date limite est respectée.
        if (null !== $sortie->getLimitDate() && $now > $sortie->getLimitDate()) {
            $this->addFlash('warning', "La date limite d'inscription est dépassée.");

            return $this->redirectToRoute('sortie_home');
        }

        // 3) Check des places dispo pour la sortie.
        if (null !== $sortie->getNbRegistration()
            && $sortie->getParticipants()->count() >= $sortie->getNbRegistration()) {
            $this->addFlash('warning', 'La sortie est complète.');

            return $this->redirectToRoute('sortie_home');
        }

        // 4) Check de l'inscription ?
        if ($sortie->getParticipants()->contains($user)) {
            $this->addFlash('info', 'Tu es déjà inscrit à cette sortie.');

            return $this->redirectToRoute('sortie_home');
        }

        // Validation de l'inscription
        $sortie->addParticipant($user);
        $em->flush();

        $this->addFlash('success', 'Inscription enregistrée');

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




    #[Route('/list/{page}', name: 'sortie_list', requirements: ['page' => '\d+'], defaults: ['page' => 1], methods: ['GET'])]
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
}
