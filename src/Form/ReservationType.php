<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('bookingDate', DateType::class, [
                'widget' => 'single_text',
                'attr' => ['value' => \date('Y-m-d')],
                'label' => 'Please Choose Your Date :'])
            ->add('visitType', ChoiceType::class, [
                'choices' => [
                    'Full-day' => 'fullDay',
                    'Half-day' => 'halfDay'],
                'expanded' => \TRUE,
                'multiple' => \FALSE,
                'label' => 'Which kind of tickets do you wish :'])
            ->add('tickets', CollectionType::class, [
                'entry_type' => TicketType::class,
                'allow_add' => true,
                'allow_delete' => true])
            ->add('email', TextType::class, [
                'attr' => [
                    'placeholder' => 'John.Doe@mail.com'],
                'label' => 'Enter Your Email Adress'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
