<?php

namespace Ongoing\Payment\SaferpayBundle\Form;

use OMS\InsertionBundle\Validator\Constraints\NotBlank;
use Ongoing\Payment\SaferpayBundle\Plugin\SaferpayPlugin;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
        $builder
            ->add('agbAccepted', CheckboxType::class, [
                'label' => /** @Desc("Ich bestätige, die <a href=""https://www.xdate.ch/de/agb"" target=""_blank"">AGB</a> gelesen zu haben und akzeptiere diese. Zudem bestätige ich dass ich über 18 Jahre alt bin.") */ 'form.agbaccepted',
                'constraints' => [
                    new NotBlank(
                        message: /** @Desc("Bitte akzeptieren Sie unsere AGBs.") */ 'form.agb.shouldaccept',
                        groups: ['saferpay']
                    )
                ],
                'required' => false,
                'label_attr' => [ 'class' => 'agb-label checkbox-custom']
            ])
            ->add('agbAcceptedCrypto', CheckboxType::class, [
                'label' => /** @Desc("Ich bestätige, die <a href=""https://www.xdate.ch/de/agb"" target=""_blank"">AGB</a> gelesen zu haben und akzeptiere diese. Zudem bestätige ich dass ich über 18 Jahre alt bin.") */ 'form.agbaccepted',
                'constraints' => [
                    new NotBlank(
                        message: /** @Desc("Bitte akzeptieren Sie unsere AGBs.") */ 'form.agb.shouldaccept',
                        groups: ['saferpay']
                    )
                ],
                'required' => false,
                'label_attr' => [ 'class' => 'agb-label checkbox-custom']
            ]);
    }

    public function getBlockPrefix()
    {
        return SaferpayPlugin::PAYMENT_SYSTEM_NAME;
    }
}