<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WeeklyDayType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('weekday', HiddenType::class, [
                'required' => true,
            ])
            ->add('kind', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => ScheduleFormHelper::kindChoices(),
                'required' => true,
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé affiché',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('ranges', CollectionType::class, [
                'entry_type' => TimeRangeType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Plages',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
