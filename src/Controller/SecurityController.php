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
    #[Route('/forgot-password', name: 'app_forgot_password')]
public function forgotPassword(Request $request, EntityManagerInterface $entityManager, \Symfony\Component\Mailer\MailerInterface $mailer): Response
{
    if ($this->getUser()) {
        return $this->redirectToRoute('app_dashboard');
    }

    if ($request->isMethod('POST')) {
        $email = $request->request->get('email');
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($user) {
            // Generate reset token (in a real app, you'd use a proper token system)
            $token = bin2hex(random_bytes(32));
            $user->setResetToken($token); // You'll need to add this field to your User entity
            $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
            $entityManager->flush();

            // Send email (simplified - you'd use a proper email service)
            $resetUrl = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            
            // In a real application, you'd send an actual email here
            // $email = (new Email())->...->html($this->renderView('emails/reset_password.html.twig', ['resetUrl' => $resetUrl]));
            // $mailer->send($email);

            $this->addFlash('success', 'If an account with that email exists, we have sent a password reset link.');
        } else {
            // For security, don't reveal if the email exists or not
            $this->addFlash('success', 'If an account with that email exists, we have sent a password reset link.');
        }

        return $this->redirectToRoute('app_forgot_password');
    }

    return $this->render('security/forgot_password.html.twig');
}

#[Route('/reset-password/{token}', name: 'app_reset_password')]
public function resetPassword(Request $request, string $token, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
{
    if ($this->getUser()) {
        return $this->redirectToRoute('app_dashboard');
    }

    $user = $entityManager->getRepository(User::class)->findOneBy([
        'resetToken' => $token,
        // Also check if token is not expired in real implementation
    ]);

    if (!$user) {
        $this->addFlash('error', 'Invalid or expired reset token.');
        return $this->redirectToRoute('app_forgot_password');
    }

    if ($request->isMethod('POST')) {
        $password = $request->request->get('password');
        $confirmPassword = $request->request->get('confirm_password');

        if ($password !== $confirmPassword) {
            $this->addFlash('error', 'Passwords do not match.');
            return $this->redirectToRoute('app_reset_password', ['token' => $token]);
        }

        // Update password
        $user->setPassword(
            $passwordHasher->hashPassword($user, $password)
        );
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $entityManager->flush();

        $this->addFlash('success', 'Your password has been reset successfully. You can now login with your new password.');
        return $this->redirectToRoute('app_login');
    }

    return $this->render('security/reset_password.html.twig');
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
