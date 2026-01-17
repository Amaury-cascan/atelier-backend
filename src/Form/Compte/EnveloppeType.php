<?php

namespace App\Form\Compte;

use App\Entity\Compte\Enveloppe;
use App\Entity\Compte\UserMois;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnveloppeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('montant')
            ->add('pourcentage')
            ->add('userMois', EntityType::class, [
                'class' => UserMois::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Enveloppe::class,
        ]);
    }
}
