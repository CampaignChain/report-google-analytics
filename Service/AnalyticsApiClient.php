<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Report\GoogleAnalyticsBundle\Service;


use CampaignChain\CoreBundle\EntityService\LocationService;
use CampaignChain\Security\Authentication\Client\OAuthBundle\EntityService\ApplicationService;
use CampaignChain\Security\Authentication\Client\OAuthBundle\EntityService\TokenService;

class AnalyticsApiClient
{

    /**
     * @var LocationService
     */
    private $locationService;

    /**
     * @var ApplicationService
     */
    private $applicationService;

    /**
     * @var TokenService
     */
    private $tokenService;

    const RESOURCE_OWNER = 'GoogleAnalytics';

    public function __construct(LocationService $locationService, ApplicationService $applicationService, TokenService $tokenService)
    {
        $this->locationService = $locationService;
        $this->applicationService = $applicationService;
        $this->tokenService = $tokenService;
    }

    /**
     * @return \Google_Service_Analytics
     * @throws \Exception
     * @throws \Google_Exception
     */
    public function getService($token = null)
    {


        $application = $this
            ->applicationService
            ->getApplication(self::RESOURCE_OWNER);

        if ($token === null) {
            $token = $this->getToken();
        }

        $authConfig = array(
            'web' => array(
                'client_id' => $application->getKey(),
                'client_secret' => $application->getSecret()

            )
        );

        $client = new \Google_Client();
        $client->setAuthConfig(json_encode($authConfig));
        $client->setAccessToken(
            json_encode(
                array(
                    'access_token' => $token->getAccessToken(),
                    'refresh_token' => $token->getRefreshToken(),
                )
            )
        );
        return new \Google_Service_Analytics($client);

    }

    private function getToken()
    {
        $locationModule = $this
            ->locationService
            ->getLocationModule('campaignchain/location-google-analytics', 'campaignchain-google-analytics');

        foreach($locationModule->getLocations() as $location) {
            return $this->tokenService->getToken($location);
        }

        return null;
    }
}
