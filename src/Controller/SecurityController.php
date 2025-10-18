<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\AppAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class SecurityController extends AbstractController
{
    private $requestStack;

    public function __construct(\Symfony\Component\HttpFoundation\RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    // NORMAL LOGIN
    #[Route(path: '/login', name: 'app_login')]
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

    // LOGOUT
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    // REDIRECT TO GOOGLE LOGIN
    #[Route(path: '/connect/google', name: 'connect_google_start')]
    public function connectGoogle(ClientRegistry $clientRegistry)
    {
        // Redirect to Google OAuth page
        return $clientRegistry
            ->getClient('google') // key from knpu_oauth2_client.yaml
            ->redirect(['email', 'profile']); // optional scopes
    }

    // GOOGLE CALLBACK
    #[Route(path: '/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $userAuthenticator,
        AppAuthenticator $authenticator
    ): Response
    {
        $client = $clientRegistry->getClient('google');

        try {
            $googleUser = $client->fetchUser();
        } catch (\Exception $e) {
            // Redirect to login if Google login fails
            return $this->redirectToRoute('app_login');
        }

        $email = $googleUser->getEmail();

        // Check if user already exists
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            // Create a new user if it doesn't exist
            $user = new User();
            $user->setEmail($email);
            $user->setName($googleUser->getName() ?? $email);
            $user->setRoles(['ROLE_USER']);
            $user->setPassword(bin2hex(random_bytes(10))); // temporary random password
            $user->setCreatedAt(new \DateTimeImmutable());

            $em->persist($user);
            $em->flush();
        }

        // Authenticate the user
        return $userAuthenticator->authenticateUser(
            $user,
            $authenticator,
            $this->requestStack->getCurrentRequest()
        );
    }
}
