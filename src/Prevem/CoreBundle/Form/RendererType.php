<?php

namespace Prevem\CoreBundle\Form;

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
    public function setConfigureOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Prevem\CoreBundle\Entity\Renderer'
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'prevem_core_renderer';
    }
}
