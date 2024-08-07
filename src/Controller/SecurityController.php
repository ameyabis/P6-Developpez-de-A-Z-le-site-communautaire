<?php

namespace App\Controller;

use App\Form\ResetPasswordRequestType;
use App\Form\ResetPasswordType;
use App\Repository\UserRepository;
use App\Service\SendMailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout', methods: 'GET')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/forgot-password', name: 'forgotten_password', methods: ['GET', 'POST'])]
    public function forgottenPassword(
        Request $request,
        UserRepository $userRepository,
        TokenGeneratorInterface $tokenGenerator,
        EntityManagerInterface $em,
        SendMailService $mail,
    ): Response {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $userRepository->findOneByUsername($form->get('username')->getData());

            if ($user) {
                $token = $tokenGenerator->generateToken();
                $user->setResetToken($token);
                $em->persist($user);
                $em->flush();

                $url = $this->generateUrl(
                    'reset_pass',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $context = compact('url', 'user');

                $mail->send(
                    'jg@a-w-c.eu',
                    $user->getEmail(),
                    'Réinitialisation de mot de passe',
                    'password_reset',
                    $context,
                );

                $this->addFlash('success', 'Email envoyé avec succès');
                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('danger', 'Un problème est survenu');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password_request.html.twig', [
            'requestPassForm' => $form->createView(),
        ]);
    }

    #[Route(path: '/forgot-password/{token}', name: 'reset_pass', methods: ['GET', 'POST'])]
    public function resetPass(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $userRepository->findOneByResetToken($token);

        if ($user) {
            $form = $this->createForm(ResetPasswordType::class);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $user->setResetToken('');
                $user->setPassword(
                    $passwordHasher->hashPassword(
                        $user,
                        $form->get('password')->getData()
                    )
                );
                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Mot de passe changé avec succès');
                return $this->redirectToRoute('app_login');
            }

            return $this->render('security/reset_password.html.twig', [
                'passForm' => $form->createView()
            ]);
        }
        $this->addFlash('danger', 'Jeton invalide');

        return $this->redirectToRoute('app_login');
    }
}
