<?php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordType extends AbstractType {
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'first_options'  => ['label' => 'New password'],
            'second_options' => ['label' => 'Confirm password'],
            'invalid_message' => 'The passwords do not match.',
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(min: 8, max: 4096),
                // TODO regex
            ],
        ]);
    }
}