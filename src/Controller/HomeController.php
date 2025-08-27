<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
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
