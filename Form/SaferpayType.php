<?php

namespace Ongoing\Payment\SaferpayBundle\Form;

use Ongoing\Payment\SaferpayBundle\Plugin\SaferpayPlugin;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Type for Saferpay Checkout.
 *
 * @author Oliver Milanovic <milanovic.oliver@gmail.com>
 */
class SaferpayType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'payment_processor_separation' => []
        ]);
    }

    public function getBlockPrefix()
    {
        return SaferpayPlugin::PAYMENT_SYSTEM_NAME;
    }
}