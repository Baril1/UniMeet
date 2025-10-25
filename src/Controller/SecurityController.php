<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Security\AppAuthenticator;
use App\Repository\MeetingRepository; // ← ADD THIS LINE
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class SecurityController extends AbstractController
{
    private ?MailerInterface $mailer;

    public function __construct(?MailerInterface $mailer = null)
    {
        $this->mailer = $mailer;
    }

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        // If user is already logged in, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('home/index.html.twig');
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function dashboard(MeetingRepository $meetingRepository): Response
    {
        $user = $this->getUser();
        
        // Get user's meetings
        $meetings = $meetingRepository->findBy(['host' => $user], ['createdAt' => 'DESC']);
        
        // Filter meetings
        $ongoingMeetings = array_filter($meetings, fn($m) => $m->getStatus() === 'ongoing');
        $upcomingMeetings = array_filter($meetings, fn($m) => $m->getStatus() === 'scheduled');
        $recentMeetings = array_slice($meetings, 0, 5); // Last 5 meetings
        
        // Calculate total participants
        $totalParticipants = array_reduce($meetings, fn($carry, $m) => $carry + $m->getParticipants()->count(), 0);
    
        return $this->render('home/dashboard.html.twig', [
            'meetings' => $meetings,
            'ongoing_meetings' => $ongoingMeetings,
            'upcoming_meetings' => $upcomingMeetings,
            'recent_meetings' => $recentMeetings,
            'total_participants' => $totalParticipants,
        ]);
    }

    #[Route('/settings', name: 'app_settings')]
    public function settings(): Response
    {
        return $this->render('settings/index.html.twig');
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        return $this->render('profile/index.html.twig', [
            'user' => $this->getUser()
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        // If user is already logged in, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encode the plain password
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Set created date
            $user->setCreatedAt(new \DateTimeImmutable());

            // Set default role if not set
            if (empty($user->getRoles())) {
                $user->setRoles(['ROLE_USER']);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            // Redirect to login page after successful registration
            $this->addFlash('success', 'Registration successful! You can now log in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

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

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            
            if (empty($email)) {
                $this->addFlash('error', 'Please enter your email address.');
                return $this->render('security/forgot_password.html.twig');
            }

            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user) {
                // Generate PIN
                $pinCode = str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Store in session
                $session = $request->getSession();
                $session->set('reset_pin', $pinCode);
                $session->set('reset_email', $email);
                $session->set('pin_expires_at', time() + 900);

                // ALWAYS show PIN for testing
                $this->addFlash('success', 'Your PIN code is: ' . $pinCode);
                error_log("PASSWORD RESET PIN: " . $pinCode . " for email: " . $email);

                // Try to send email only if mailer is available
                if ($this->mailer) {
                    try {
                        $emailMessage = (new Email())
                            ->from('unimeet7@gmail.com')
                            ->to($user->getEmail())
                            ->subject('UniMeet Password Reset PIN')
                            ->text('Your PIN code is: ' . $pinCode . '. This code expires in 15 minutes.');

                        $this->mailer->send($emailMessage);
                        $this->addFlash('success', 'PIN code has also been sent to your email!');
                        
                    } catch (\Exception $e) {
                        $this->addFlash('warning', 'Email sending failed. Using on-screen PIN.');
                    }
                }

               return $this->redirectToRoute('app_verify_pin');
            } else {
                $this->addFlash('success', 'If an account with that email exists, we have sent a PIN code.');
                return $this->redirectToRoute('app_verify_pin');
            }
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/verify-pin', name: 'app_verify_pin')]
    public function verifyPin(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $session = $request->getSession();

        if (!$session->has('reset_pin')) {
            $this->addFlash('error', 'Please request a PIN code first.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if (time() > $session->get('pin_expires_at')) {
            $session->remove('reset_pin');
            $session->remove('reset_email');
            $session->remove('pin_expires_at');
            $this->addFlash('error', 'PIN code has expired. Please request a new one.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $enteredPin = $request->request->get('pin');
            $storedPin = $session->get('reset_pin');

            if ($enteredPin === $storedPin) {
                $this->addFlash('success', 'PIN verified successfully. You can now reset your password.');
                return $this->redirectToRoute('app_reset_password');
            } else {
                $this->addFlash('error', 'Invalid PIN code. Please try again.');
            }
        }

        return $this->render('security/verify_pin.html.twig', [
            'email' => $session->get('reset_email')
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function resetPassword(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $session = $request->getSession();

        if (!$session->has('reset_pin') || !$session->has('reset_email')) {
            $this->addFlash('error', 'Please verify your PIN first.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $email = $session->get('reset_email');
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->redirectToRoute('app_reset_password');
            }

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Password should be at least 6 characters long.');
                return $this->redirectToRoute('app_reset_password');
            }

            $user->setPassword(
                $passwordHasher->hashPassword($user, $password)
            );
            $entityManager->flush();

            $session->remove('reset_pin');
            $session->remove('reset_email');
            $session->remove('pin_expires_at');

            $this->addFlash('success', 'Your password has been reset successfully. You can now log in with your new password.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig');
    }

    #[Route(path: '/connect/google', name: 'connect_google_start')]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
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
    ): Response {
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

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->container->get('security.token_storage')->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/test-email', name: 'app_test_email')]
    public function testEmail(): Response
    {
        if (!$this->mailer) {
            return new Response('Mailer service not available');
        }

        try {
            $email = (new Email())
                ->from('unimeet7@gmail.com')
                ->to('test@example.com')
                ->subject('Test Email from UniMeet')
                ->text('This is a test email from UniMeet application.');

            $this->mailer->send($email);
            return new Response('Test email sent successfully!');
        } catch (\Exception $e) {
            return new Response('Test email failed: ' . $e->getMessage());
        }
    }
}