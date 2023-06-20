<?php

namespace Ongoing\Payment\SaferpayBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

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

    public function getBlockPrefix()
    {
        return 'saferpay_checkout';
    }
}