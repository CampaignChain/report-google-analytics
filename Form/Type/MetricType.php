<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Report\GoogleAnalyticsBundle\Form\Type;

use CampaignChain\Location\GoogleAnalyticsBundle\Entity\Profile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class MetricType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('metrics', 'choice', array(
            'label' => 'Select Metrics',
            'choices' => Profile::getMetricsArray(),
            'expanded' => true,
            'multiple' => true,
            'constraints' => array(
                new Assert\NotBlank(array('message' => 'Please choose at least one metric'))
            )
        ));
            $builder->add('segment', 'choice', array(
                'label' => 'Select Segment',
                'choices' => Profile::getSegmentsArray(),
            'expanded' => true,
            'multiple' => false
        ));;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
       $resolver->setDefaults(array(
           'data_class' => 'CampaignChain\Location\GoogleAnalyticsBundle\Entity\Profile',
       ));
    }

    public function getName()
    {
       return 'campaignchain_report_google_analytics_metric';
    }

}