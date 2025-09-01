<?php

namespace App\Controller;

use App\Form\ModifyProfilType;
use App\Helper\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ModifyProfilController extends AbstractController
{
    #[Route('/profil', name: 'modify_profil')]
    #[IsGranted('ROLE_USER')]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, SluggerInterface $slugger,): Response
    {
        $user = $this->getUser();
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

                    return $this->render('modify_profil/modifyProfil.html.twig', [
                        'modifyProfil' => $form,
                    ]);
                }
            }

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('sortie_list');
        }

        return $this->render('modify_profil/modifyProfil.html.twig', [
            'modifyProfil' => $form,
        ]);
    }
}
