<?php

namespace App\Form;

use App\Entity\ClientInformation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType; // Importer DateType
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientInformationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [ // Utiliser DateType

            ])
            ->add('technique')
            ->add('forme')
            ->add('produit')
            ->add('prix')
            ->add('note')

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ClientInformation::class,
        ]);
    }
}
