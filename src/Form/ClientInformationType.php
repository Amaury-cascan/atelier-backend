<?php

namespace App\Form;

use App\Entity\ClientInformation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType; // Importer DateType
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

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
            ->add('images', FileType::class, [
                'label' => 'Images',
                'mapped' => false, // Ce champ n’est pas directement lié à l’entité ClientInformation
                'required' => false,
                'multiple' => true, // Permet l’upload multiple
            ]);

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ClientInformation::class,
        ]);
    }
}
