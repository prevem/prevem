<?php

namespace CiviBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class RendererType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title')
            ->add('os')
            ->add('osVersion')
            ->add('app')
            ->add('appVersion')
            ->add('icons')
            ->add('options')
            ->add('lastSeen')
        ;
    }
    
    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'CiviBundle\Entity\Renderer'
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'civibundle_renderer';
    }
}
