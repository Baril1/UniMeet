<?php

namespace App\Controller;

use App\Entity\Meeting;
use App\Form\MeetingType;
use App\Repository\MeetingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MeetingController extends AbstractController
{
    #[Route('/meetings', name: 'app_meeting_index')]
    public function index(MeetingRepository $meetingRepository): Response
    {
        $user = $this->getUser();
        $meetings = $meetingRepository->findBy(['host' => $user], ['createdAt' => 'DESC']);

        return $this->render('meeting/index.html.twig', [
            'meetings' => $meetings,
        ]);
    }

    #[Route('/meetings/new', name: 'app_meeting_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $meeting = new Meeting();
        $meeting->setHost($this->getUser());
        
        $form = $this->createForm(MeetingType::class, $meeting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle instant meetings
            if ($meeting->getType() === 'instant') {
                $meeting->setScheduledAt(new \DateTime());
                $meeting->setStatus('ongoing');
            } else {
                $meeting->setStatus('scheduled');
            }

            $entityManager->persist($meeting);
            $entityManager->flush();

            $this->addFlash('success', 'Meeting created successfully!');
            
            if ($meeting->getType() === 'instant') {
                return $this->redirectToRoute('app_meeting_room', ['id' => $meeting->getId()]);
            }
            
            return $this->redirectToRoute('app_meeting_index');
        }

        return $this->render('meeting/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/meetings/{id}', name: 'app_meeting_show', methods: ['GET'])]
    public function show(Meeting $meeting): Response
    {
        return $this->render('meeting/show.html.twig', [
            'meeting' => $meeting,
        ]);
    }

    #[Route('/meetings/{id}/room', name: 'app_meeting_room', methods: ['GET'])]
    public function meetingRoom(Meeting $meeting): Response
    {
        // Check if user can access this meeting
        if ($meeting->getHost() !== $this->getUser() && !$meeting->getParticipants()->contains($this->getUser())) {
            $this->addFlash('error', 'You do not have access to this meeting.');
            return $this->redirectToRoute('app_meeting_index');
        }

        return $this->render('meeting/room.html.twig', [
            'meeting' => $meeting,
        ]);
    }

    #[Route('/meetings/{id}/start', name: 'app_meeting_start', methods: ['POST'])]
    public function startMeeting(Meeting $meeting, EntityManagerInterface $entityManager): Response
    {
        if ($meeting->getHost() !== $this->getUser()) {
            $this->addFlash('error', 'Only the host can start the meeting.');
            return $this->redirectToRoute('app_meeting_index');
        }

        $meeting->setStatus('ongoing');
        $entityManager->flush();

        return $this->redirectToRoute('app_meeting_room', ['id' => $meeting->getId()]);
    }

    #[Route('/meetings/{id}/join', name: 'app_meeting_join', methods: ['POST'])]
    public function joinMeeting(Meeting $meeting, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        if (!$meeting->getParticipants()->contains($user)) {
            $meeting->addParticipant($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_meeting_room', ['id' => $meeting->getId()]);
    }
}