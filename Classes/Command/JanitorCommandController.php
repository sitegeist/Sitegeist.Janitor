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

use Neos\Flow\Annotations as Flow;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Cli\CommandController;
use Neos\Neos\Controller\CreateContentContextTrait;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Service\NodeOperations;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;

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
     * @param string $workspaceName
     * @return void
     * @throws \Neos\Eel\Exception
     */
    public function showCommand($nodePath, $workspaceName = 'live')
    {
        $context = $this->createContentContext($workspaceName, []);
        $flowQuery = new FlowQuery([$context->getRootNode()]);
        $subjectNode = $flowQuery->find($nodePath)->get(0);

        if ($subjectNode instanceof NodeInterface) {
            $this->outputLine();

            $this->outputLine('Node <b>"%s"</b> of type <b>%s</b>', [
                $subjectNode->getLabel(),
                $subjectNode->getNodeType()->getName()
            ]);

            $this->outputLine();
            $this->outputLine('<b>Properties:</b>');
            $this->outputLine();

            $this->outputLine(json_encode($subjectNode->getProperties(), JSON_PRETTY_PRINT));

            $this->outputLine();
            $this->outputLine('<b>Children:</b>');
            $this->outputLine();

            foreach ($subjectNode->getChildNodes() as $childNode) {
                $this->outputLine('    <b>%s</b>', [$childNode->getPath()]);
                $this->outputLine('    <i>%s</i>', [$childNode->getLabel()]);
                $this->outputLine('    %s', [$childNode->getNodeType()->getName()]);
                $this->outputLine();
            }
        } else {
            $this->outputLine();
            $this->outputLine('<error>Node not found</error>');
            $this->outputLine();
        }
    }

    /**
     * Copy a site
     *
     * @param string $sourceSiteNodeName
     * @param string $targetSiteNodeName
     * @return void
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws \Neos\Eel\Exception
     * @throws \Neos\Flow\ObjectManagement\Exception\UnresolvedDependenciesException
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    public function copySiteCommand($sourceSiteNodeName, $targetSiteNodeName)
    {
        $dimensionPresets = $this->contentDimensionCombinator->getAllAllowedCombinations();

        foreach ($dimensionPresets as $dimensionPreset) {
            $context = $this->createContentContext('live', $dimensionPreset);
            $flowQuery = new FlowQuery([$context->getRootNode()]);
            $sitesNode = $flowQuery->find(sprintf('/sites', $sourceSiteNodeName))->get(0);
            $sourceSiteNode = $flowQuery->find(sprintf('/sites/%s', $sourceSiteNodeName))->get(0);
            /** @var Site $sourceSite */
            $sourceSite = $this->siteRepository->findOneByNodeName($sourceSiteNodeName);

            $targetSiteNode = $this->nodeOperations->copy($sourceSiteNode, $sitesNode, 'into', $targetSiteNodeName);
            $targetSite = new Site($targetSiteNodeName);
            $targetSite->setSiteResourcesPackageKey($sourceSite->getSiteResourcesPackageKey());

            $this->siteRepository->add($targetSite);

        }

        $this->outputLine('Successfully copied %s to %s', [$sourceSiteNodeName, $targetSiteNodeName]);
    }
}
