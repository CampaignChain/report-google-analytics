<?php
/*
 * Copyright 2016 CampaignChain, Inc. <info@campaignchain.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
            'label' => 'Metrics',
            'choices' => Profile::getMetricsArray(),
            'expanded' => true,
            'multiple' => true,
            'constraints' => array(
                new Assert\NotBlank(array('message' => 'Please choose at least one metric'))
            )
        ));
            $builder->add('segment', 'choice', array(
                'label' => 'Segments',
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

    public function getBlockPrefix()
    {
       return 'campaignchain_report_google_analytics_metric';
    }

}