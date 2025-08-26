<?php

namespace App\Controller;

use App\Entity\Etat;
use App\Entity\Sortie;
use App\Form\CreateSortieType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
}
