<?php
namespace Sitegeist\Janitor\Command;


/*
 * Copyright notice
 *
 * (c) 2016 Wilhelm Behncke <behncke@sitegeist.de>
 * All rights reserved
 *
 * This file is part of the Sitegeist/Package project under <licence>.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Neos\Controller\CreateContentContextTrait;
use TYPO3\Neos\Domain\Repository\SiteRepository;
use TYPO3\Neos\Domain\Model\Site;
use TYPO3\Neos\Service\NodeOperations;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;
use TYPO3\TYPO3CR\Domain\Service\ContentDimensionCombinator;
use TYPO3\TYPO3CR\Domain\Repository\WorkspaceRepository;
use Sitegeist\Janitor\TYPO3CR\Service\NodeUriService;

/**
 * A command controller to perform certain operations on the CR
 *
 * @author Wilhelm Behncke <behncke@sitegeist.de>
 */
class JanitorCommandController extends CommandController
{
    use CreateContentContextTrait;

    /**
     * @Flow\Inject
     * @var ContentDimensionCombinator
     */
    protected $contentDimensionCombinator;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var NodeOperations
     */
    protected $nodeOperations;

    /**
     * Show information about node
     *
     * @param string $nodePath
     * @return void
     */
    public function showCommand($nodePath)
    {
        $this->outputLine('Not implemented yet');
    }

    /**
     * Copy a site
     *
     * @param string $sourceSiteNodeName
     * @param string $targetSiteNodeName
     * @return void
     */
    public function copySiteCommand($sourceSiteNodeName, $targetSiteNodeName)
    {
        $dimensionPresets = $this->contentDimensionCombinator->getAllAllowedCombinations();

        foreach ($dimensionPresets as $dimensionPreset) {
            $context = $this->createContentContext('live', $dimensionPreset);
            $flowQuery = new FlowQuery([$context->getRootNode()]);
            $sitesNode = $flowQuery->find(sprintf('/sites', $sourceSiteNodeName))->get(0);
            $sourceSiteNode = $flowQuery->find(sprintf('/sites/%s', $sourceSiteNodeName))->get(0);
            $sourceSite = $this->siteRepository->findOneByNodeName($sourceSiteNodeName);

            $targetSiteNode = $this->nodeOperations->copy($sourceSiteNode, $sitesNode, 'into', $targetSiteNodeName);
            $targetSite = new Site($targetSiteNodeName);
            $targetSite->setSiteResourcesPackageKey($sourceSite->getSiteResourcesPackageKey());

            $this->siteRepository->add($targetSite);

        }

        $this->outputLine('Successfully copied %s to %s', [$sourceSiteNodeName, $targetSiteNodeName]);
    }
}
