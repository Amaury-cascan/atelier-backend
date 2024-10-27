<?php

namespace App\Form;

use App\Entity\Category; // Corrigez la casse de Category (il est généralement recommandé de commencer par une majuscule)
use App\Entity\Service;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType; // Importez FileType pour les téléchargements de fichiers
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
            ->add('image', FileType::class, [ // Changez 'picture' en 'image' pour un champ de type File
                'label' => 'Image',
                'mapped' => false, // Ce champ ne sera pas mappé directement à l'entité
                'required' => false, // Rendre ce champ optionnel si nécessaire
            ])
            ->add('description', null, [
                'label' => 'Description'
            ])
            ->add('duration', null, [
                'label' => 'Durée'
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
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
