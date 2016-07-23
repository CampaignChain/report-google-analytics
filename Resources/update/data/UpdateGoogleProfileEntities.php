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

namespace CampaignChain\Report\GoogleAnalyticsBundle\Resources\update\data;

use CampaignChain\UpdateBundle\Service\DataUpdateInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateGoogleProfileEntities implements DataUpdateInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getVersion()
    {
        return 20160630124400;
    }

    public function getDescription()
    {
        return [
            'Move every profileId -> property id',
            'Extract from the location url the real profileId',
            'Add the profileId to the profileId column and also for the identifier column',
        ];
    }

    public function execute(SymfonyStyle $io = null)
    {
        $currentProfiles = $this->entityManager
            ->getRepository('CampaignChainLocationGoogleAnalyticsBundle:Profile')
            ->findAll();

        if (empty($currentProfiles)) {
            $io->text('There is no Profile entity to update');

            return true;
        }

        foreach ($currentProfiles as $profile) {
            if (substr($profile->getProfileId(), 0, 2) != 'UA') {
                continue;
            }

            $profile->setPropertyId($profile->getProfileId());

            $gaProfileUrl = $profile->getLocation()->getUrl();
            $google_base_url = 'https:\/\/www.google.com\/analytics\/web\/#report\/visitors-overview\/a'.$profile->getAccountId().'w\d+p';
            $pattern = '/'.$google_base_url.'(.*)/';

            preg_match($pattern, $gaProfileUrl, $matches);

            if (!empty($matches) && count($matches) == 2) {
                $profile->setProfileId($matches[1]);
                $profile->setIdentifier($profile->getProfileId());
            }

            $this->entityManager->persist($profile);
        }

        $this->entityManager->flush();

        return true;
    }

}