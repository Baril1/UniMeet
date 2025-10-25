<?php

namespace App\Form;

use App\Entity\Meeting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MeetingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500',
                    'placeholder' => 'Enter meeting title'
                ],
                'label' => 'Meeting Title'
            ])
            ->add('description', TextareaType::class, [
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500',
                    'placeholder' => 'Enter meeting description (optional)',
                    'rows' => 3
                ],
                'required' => false,
                'label' => 'Description'
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Scheduled Meeting' => 'scheduled',
                    'Instant Meeting' => 'instant',
                ],
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 meeting-type-selector'
                ],
                'label' => 'Meeting Type'
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 scheduled-field',
                    'min' => (new \DateTime())->format('Y-m-d\TH:i')
                ],
                'required' => false,
                'label' => 'Schedule Date & Time'
            ])
            ->add('duration', IntegerType::class, [
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500',
                    'min' => 15,
                    'max' => 480
                ],
                'label' => 'Duration (minutes)',
                'data' => 60
            ])
            ->add('password', TextType::class, [
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500',
                    'placeholder' => 'Optional meeting password'
                ],
                'required' => false,
                'label' => 'Meeting Password (Optional)'
            ])
            ->add('maxParticipants', IntegerType::class, [
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500',
                    'min' => 2,
                    'max' => 100
                ],
                'required' => false,
                'label' => 'Max Participants (Optional)'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Meeting::class,
        ]);
    }
}