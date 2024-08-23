<?php

namespace App\Form;

use App\Entity\category;
use App\Entity\Service;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nom'
            ])
            ->add('price', null, [
                'label' => 'Prix'
            ])
            ->add('picture', null, [
                'label' => 'Image'
            ])
            ->add('description', null, [
                'label' => 'Description'
            ])
            ->add('duration', null, [
                'label' => 'Durée'
            ])
            ->add('category', EntityType::class, [
                'class' => category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }
}
