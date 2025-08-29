<?php

namespace App\Form;

use App\Entity\Place;
use App\Entity\Sortie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreateSortieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'évennement',
            ])
            ->add('startDateTime', DateTimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('duration')
            ->add('limitDate', DateTimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('nbRegistration')
            ->add('info')
            ->add('place', PlaceType::class)
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
            ]);
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sortie::class,
        ]);
    }
}
