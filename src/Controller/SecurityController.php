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

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/connect/google', name: 'connect_google_start')]
    public function connectGoogle(ClientRegistry $clientRegistry)
    {
        return $clientRegistry
            ->getClient('google') 
            ->redirect(['email', 'profile']); 
    }

    #[Route(path: '/connect/google/check', name: 'connect_google_check')]
public function connectGoogleCheck(
    ClientRegistry $clientRegistry,
    EntityManagerInterface $em,
    UserAuthenticatorInterface $userAuthenticator,
    AppAuthenticator $authenticator,
    Request $request
): Response
{
    $client = $clientRegistry->getClient('google');

    try {
        $googleUser = $client->fetchUser();
    } catch (\Exception $e) {
        return $this->redirectToRoute('app_login');
    }

    $email = $googleUser->getEmail();

    $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

    if (!$user) {
        $user = new User();
        $user->setEmail($email);
        $user->setName($googleUser->getName() ?? $email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(bin2hex(random_bytes(10)));
        $user->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();
    }

    // Manually authenticate the user
    $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
    $this->container->get('security.token_storage')->setToken($token);
    $request->getSession()->set('_security_main', serialize($token));

    return $this->redirectToRoute('app_dashboard');
}
}
