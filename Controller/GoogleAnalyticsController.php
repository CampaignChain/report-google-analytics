<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Report\GoogleAnalyticsBundle\Controller;

use CampaignChain\CoreBundle\Entity\Campaign;
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
                        ->select('cpgn')
                        ->from('CampaignChain\CoreBundle\Entity\Campaign', 'cpgn')
                        ->orderBy('campaign.startDate', 'ASC');
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

        $prop = $analytics->management_webproperties->get($profile->getAccountId(), $profile->getProfileId());
        $profileId = $prop->getDefaultProfileId();

        if ($profileId === null) {
            $profiles = $analytics->management_profiles->listManagementProfiles($profile->getAccountId(), $profile->getProfileId());
            $profileId = $profiles->getItems()[0]['id'];

        }
        $reportData = [];
        if (!empty($profile->getMetrics())) {

            $startDate = $campaign->getStartDate()->format('Y-m-d');
            $endDate = $campaign->getEndDate()->format('Y-m-d');;
            $metrics = implode(',', $profile->getMetrics());
            $segment = $profile->getSegment();


            $data = $analytics->data_ga->get('ga:' . $profileId, $startDate, $endDate, $metrics, array(
                'dimensions' => 'ga:date',
                'segment' => $profile->getSegment() ? $profile->getSegment():null,
            ));

            $items = $data->getRows();


            foreach (array_values($profile->getMetrics()) as $m => $metricName) {
                $row = [];
                foreach ($items as $item) {
                    $row[] = [
                        strtotime($item[0]) * 1000,
                        $item[$m + 1]
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
                'page_title' => sprintf('Google Analytics for %s on %s', $profile->getDisplayName(), $campaign->getName()),
                'formMetrics' => $formMetrics->createView(),
                'report_data' => $reportData
            )
        );

    }

}