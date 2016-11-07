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

namespace CampaignChain\Report\GoogleAnalyticsBundle\Controller;

use CampaignChain\CoreBundle\Entity\Campaign;
use CampaignChain\CoreBundle\Entity\ReportAnalyticsActivityFact;
use CampaignChain\Location\GoogleAnalyticsBundle\Entity\Profile;
use CampaignChain\Report\GoogleAnalyticsBundle\Form\Type\MetricType;
use CampaignChain\Report\GoogleAnalyticsBundle\Form\Type\SegmentType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GoogleAnalyticsController extends Controller
{

    public function indexAction(Request $request)
    {

        $campaign = array();
        $form = $this->createFormBuilder($campaign)
            ->add('locaton', 'entity', array(
                'label' => 'Profile',
                'class' => 'CampaignChainLocationGoogleAnalyticsBundle:Profile',
                'property' => 'displayName',
                'empty_value' => 'Select a Google Analytics Profile',
                'empty_data' => null,
            ))
            ->add('campaign', 'entity', array(
                'label' => 'Campaign',
                'class' => 'CampaignChainCoreBundle:Campaign',
                // Only display campaigns for selection that actually have report data
                'query_builder' => function(EntityRepository $er) {
                    return $er->createQueryBuilder('campaign')
                        // Exclude repeating campaigns or templates
                        ->where('campaign.startDate != :relativeStartDate')
                        ->setParameter('relativeStartDate', Campaign::RELATIVE_START_DATE)
                        // Only show campaigns that already started
                        ->andWhere('campaign.startDate < :today')
                        // Show only campaigns that actually have Activities facts.
                        ->andWhere(
                            "EXISTS (SELECT af.id FROM CampaignChain\CoreBundle\Entity\ReportAnalyticsActivityFact af WHERE af.campaign = campaign.id)"
                        )
                        ->orderBy('campaign.startDate', 'ASC')
                        ->setParameter('today', date("Y-m-d H:i:s"));
                },
                'property' => 'name',
                'empty_value' => 'Select a Campaign',
                'empty_data' => null,
            ))
            ->getForm();


        $form->handleRequest($request);

        if ($form->isValid()) {
            return $this->redirect(
                $this->generateUrl(
                    'campaignchain_report_google_analytics_report',
                    array(
                        'locationId' => $form->getData()['locaton']->getId(),
                        'campaignId' => $form->getData()['campaign']->getId(),
                    )
                )
            );
        }

        $tplVars = array(
            'page_title' => 'Select Google Analytics Profile',
            'form' => $form->createView(),
        );

        return $this->render(
            'CampaignChainCoreBundle:Base:new.html.twig',
            $tplVars);

    }

    /**
     * @param $locationId
     * @return Response
     * @throws \Exception
     */
    public function reportAction(Request $request, $locationId, $campaignId)
    {

        $campaignRepo = $this->getDoctrine()->getRepository('CampaignChainCoreBundle:Campaign');
        /** @var Campaign $campaign */
        $campaign = $campaignRepo->findOneById($campaignId);

        $profileRepo = $this->getDoctrine()->getRepository('CampaignChainLocationGoogleAnalyticsBundle:Profile');
        /** @var Profile $profile */
        $profile = $profileRepo->findOneById($locationId);
        $gaMetrics = $profile->getMetrics();
        $location = $profile->getLocation();
        $tokenService = $this->get('campaignchain.security.authentication.client.oauth.token');
        $token = $tokenService->getToken($location);

        //Form for metrics selection
        $formMetrics = $this->createForm(new MetricType(), $profile);
        $formMetrics->handleRequest($request);

        if ($formMetrics->isValid()){
            $em = $this->getDoctrine()->getManager();
            $em->persist($profile);
            $em->flush();

            return $this->redirectToRoute('campaignchain_report_google_analytics_report', ['locationId' => $locationId, 'campaignId' => $campaignId]);
        }


        $analytics = $this->get('campaignchain_report_google_analytics.service_client')->getService($token);

        //Data from the metric facts tables i.e. Facebook Likes
        $repository = $this->getDoctrine()
            ->getRepository('CampaignChainCoreBundle:ReportAnalyticsActivityFact');

        $query = $repository->createQueryBuilder('fact');
        $facts = $query->select('activity', 'fact', 'metric', 'channel')
            ->join('fact.metric', 'metric')
            ->join('fact.activity', 'activity')
            ->join('activity.channel', 'channel')
            ->where('fact.campaign = :campaign')
            ->setParameters([
                'campaign' => $campaignId,
            ])
            ->groupBy('fact.metric')
            ;

        $facts = $facts->getQuery()->getResult();
        $factData = array();

        if($facts) {
            $row = [];
            /** @var ReportAnalyticsActivityFact[] $facts */
            foreach ($facts as $fact) {
                /** @var ReportAnalyticsActivityFact[] $entrys */
                $entrys = $this->getDoctrine()->getRepository('CampaignChainCoreBundle:ReportAnalyticsActivityFact')->findBy(
                    array('campaign' => $campaignId, 'metric' => $fact->getMetric()->getId()), ['time' => 'ASC']);

                foreach ($entrys as $entry) {
                    $row[$fact->getId()][] = [
                        $entry->getTime()->getTimestamp() * 1000,
                        $entry->getValue()];
                }
                $factData[] = [
                    'channel' => $fact->getActivity()->getChannel(),
                    'location' => $fact->getActivity()->getLocation(),
                    'label' => $fact->getMetric()->getName(),
                    'data' => $row[$fact->getId()]
                ];
            }
        }


        //Google Analytics Report Data
        $reportData = [];
        $startDate = $campaign->getStartDate()->format('Y-m-d');
        $endDate = $campaign->getEndDate()->format('Y-m-d');;

        if (!empty($gaMetrics)) {

            $metrics = implode(',', $gaMetrics);

            try {
                $data = $analytics->data_ga->get('ga:' . $profile->getProfileId(), $startDate, $endDate, $metrics, array(
                    'dimensions' => 'ga:date',
                    'segment' => $profile->getSegment() ? $profile->getSegment() : null,
                ));
            } catch (\Exception $e) {
                $this->addFlash('warning', $e->getMessage());
                return $this->redirectToRoute('campaignchain_report_google_analytics_index');
            }

            $items = $data->getRows();


            foreach (array_values($gaMetrics) as $m => $metricName) {
                $row = [];
                foreach ($items as $item) {
                    $row[] = [
                        strtotime($item[0]) * 1000,
                        (int) $item[$m + 1]

                    ];

                }
                $reportData[] = [
                    'label' => ucfirst(substr($metricName, 3)),
                    'data' => $row,
                ];
            }
        }


        return $this->render(
            '@CampaignChainReportGoogleAnalytics/report.html.twig',
            array(
                'page_title' => 'Google Analytics Report',
                'profile' => $profile,
                'belonging_location' => $profile->getBelongingLocation()->getUrl(),
                'formMetrics' => $formMetrics->createView(),
                'report_data' => $reportData,
                'fact_data' => $factData,
                'start_date' => $startDate,
                'end_date' => $endDate,


            )
        );

    }

}