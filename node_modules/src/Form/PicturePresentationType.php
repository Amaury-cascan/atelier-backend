<?php

namespace App\Form;

use App\Entity\PicturePresentation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PicturePresentationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description')
            ->add('image', FileType::class, [ // Changez 'picture' en 'image' pour un champ de type File
                'label' => 'Image',
                'mapped' => false, // Ce champ ne sera pas mappé directement à l'entité
                'required' => false, // Rendre ce champ optionnel si nécessaire
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PicturePresentation::class,
        ]);
    }
}
