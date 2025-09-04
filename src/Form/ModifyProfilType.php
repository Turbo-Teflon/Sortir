<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;

class ModifyProfilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {


        $builder
            ->add('nom')
            ->add('prenom')
            ->add('pseudo', null, [
                'label' => 'Pseudo',
            ])

            ->add('description', null, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('email')
            ->add('telephone')
            ->add('password', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Nouveau mot de passe',
                'constraints' => [
                    new Length(['min' => 6, 'minMessage' => 'Le mot de passe doit faire au moins {{ limit }} caractères']),
                ],
            ])
            ->add('confirm_password', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Confirmer le mot de passe',
            ])

            ->add('photo', FileType::class, [
            'required' => false,
            'mapped' => false,
            'constraints' => [
                new File([
                    'maxSize' => '1024k',
                    'maxSizeMessage' => 'Votre fichier est trop lourd !',
                    'mimeTypes' => [
                        'image/jpeg',
                        'image/jpg',
                        'image/png',
                    ],
                    'mimeTypesMessage' => 'Les formats acceptés sont jpeg, jpg, png',
                ])
            ]
        ]);


        function configureOptions(OptionsResolver $resolver): void
        {
            $resolver->setDefaults([
                'data_class' => User::class,
            ]);
        }
    }
}
