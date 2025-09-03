<?php

namespace App\Controller;

use App\Repository\SortieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(SortieRepository $sortieRepository): Response
    {

        $sorties = $sortieRepository->findBy(
            ['etat' => 'Ouverte'],
            ['startDateTime' => 'DESC'],
            5
        );

        return $this->render('sortie/home.html.twig', [
            'sorties' => $sorties,
        ]);
    }



    #[Route('/status', name: 'app_status')]
    public function status(): Response
    {
        return $this->json([
            'ok' => true,
            'env' => $_ENV['APP_ENV'] ?? 'dev',
            'db' => $_ENV['DATABASE_URL'] ? 'configured' : 'missing',
            'time' => (new \DateTimeImmutable())->format('c'),
        ]);
    }


}
