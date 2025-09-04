<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\PasswordResetToken;
use App\Form\ForgotPasswordType;
use App\Form\ResetPasswordType;
use App\Repository\UserRepository;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;



class PasswordResetController extends AbstractController
{
    public function __construct(private EM $em) {}

    #[Route('/forgot-password', name: 'forgot_password', methods: ['GET','POST'])]
    public function forgot(
        Request $request,
        UserRepository $users,
        MailerInterface $mailer
    ) {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $emailInput = $form->get('email')->getData();
            $user = $users->findOneBy(['email' => $emailInput]);


            if ($user instanceof User) {

                //TODO: Invalider les anciens tokens non utilisés, à faire via repo

                $token = (new PasswordResetToken())
                    ->setUser($user)
                    ->setToken(Uuid::v4()->toRfc4122())
                    ->setCreateAt(new \DateTimeImmutable('now'))
                    ->setExpiresAt(new \DateTimeImmutable('+1 hour'))
                    ->setUsed(false);


                $this->em->persist($token);
                $this->em->flush();

                $resetUrl = $this->generateUrl(
                    'reset_password',
                    ['token' => $token->getToken()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $message = (new Email())
                    ->from('no-reply@campus-eni.fr')
                    ->to($user->getEmail())
                    ->subject('Reset password')
                    ->html('Lien de reset : '.$resetUrl);

                $mailer->send($message);
            }

            $this->addFlash('success', 'If an account exists, an email has been sent to you.');
            return $this->redirectToRoute('forgot_password');
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'reset_password', methods: ['GET','POST'])]
    public function reset(
        string $token,
        Request $request,
        PasswordResetTokenRepository $tokens,
        UserPasswordHasherInterface $hasher
    ) {
        $tokenEntity = $tokens->findOneBy(['token' => $token]);

        if (
            !$tokenEntity
            || $tokenEntity->isUsed()
            || $tokenEntity->getExpiresAt() < new \DateTimeImmutable('now')
        ) {
            $this->addFlash('danger', 'Invalid or expired link.');
            return $this->redirectToRoute('forgot_password');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $tokenEntity->getUser();
            $newPlain = $form->get('plainPassword')->getData();

            $user->setPassword($hasher->hashPassword($user, $newPlain));

            // Token à usage unique
            $tokenEntity->setUsed(true);

            $this->em->flush();

            $this->addFlash('success', 'Your password has been reset.');
            return $this->redirectToRoute('app_login'); // ou une page de succès
        }

        return $this->render('Security/reset_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Route('/test-mail', name: 'test_mail')]
    public function testMail(\Symfony\Component\Mailer\MailerInterface $mailer)
    {
        $email = (new \Symfony\Component\Mime\Email())
            ->from('no-reply@campus-eni.fr')
            ->to('yves.regnier_8@campus-eni.fr')
            ->subject('Test Mailhog')
            ->text('Hello Mailhog depuis Symfony !');

        $mailer->send($email);

        return $this->json(['ok' => true]);
    }

}
