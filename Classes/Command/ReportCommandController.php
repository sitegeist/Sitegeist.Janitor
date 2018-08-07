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

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Flow\Annotations as Flow;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Cli\CommandController;
use Neos\Neos\Controller\CreateContentContextTrait;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Sitegeist\Janitor\TYPO3CR\Service\NodeUriService;

/**
 * A command controller to generate various reports about
 * your content repository
 *
 * @author Wilhelm Behncke <behncke@sitegeist.de>
 */
class ReportCommandController extends CommandController
{
    use CreateContentContextTrait;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var ContentDimensionCombinator
     */
    protected $contentDimensionCombinator;

    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var NodeUriService
     */
    protected $nodeUriService;

    /**
     * Shows unused node types in your content repository
     *
     * @param integer $threshold Consider all node types that have similar or less occurences than this
     * @param string $superType Limit the considered node types to a specific super type
     * @param string $workspaces Comma-separated list of workspaces to consider or _all if you want to check all (which can take a while)
     * @return void
     * @throws \Neos\Eel\Exception
     */
    public function unusedCommand($threshold = 0, $superType = 'Neos.Neos:Node', $workspaces = 'live')
    {
        $nodeTypes = $this->nodeTypeManager->getSubNodeTypes($superType);
        $nodeTypeNames = array_keys($nodeTypes);

        $workspaces = $this->resolveWorkspaces($workspaces);

        $dimensionPresets = $this->contentDimensionCombinator->getAllAllowedCombinations();

        $results = [];
        foreach ($workspaces as $workspace) {
            foreach ($dimensionPresets as $dimensionPreset) {
                $this->outputLine('Checking...');
                $this->outputWorkspace($workspace);
                $this->outputLine();
                $this->outputDimensionPreset($dimensionPreset);
                $this->outputLine();

                $context = $this->createContentContext($workspace->getName(), $dimensionPreset);

                $this->output->progressStart(count($nodeTypeNames));
                foreach ($nodeTypeNames as $nodeTypeName) {
                    $flowQuery = new FlowQuery([$context->getRootNode()]);
                    $nodes = $flowQuery->find(sprintf('[instanceof %s]', $nodeTypeName));

                    if (!isset($results[$nodeTypeName])) {
                        $results[$nodeTypeName] = 0;
                    }
                    $results[$nodeTypeName] += count($nodes);

                    $this->output->progressAdvance();
                }
                $this->output->progressFinish();
                $this->outputLine();
            }
        }

        $filtered = [];
        foreach ($results as $key => $value) {
            if ($value <= $threshold) {
                $filtered[$key] = $value;
            }
        }
        $results = $filtered;

        $this->outputReportHeadline('Unused NodeTypes');

        if (count($results) === 0) {
            $this->outputLine('<success>Congratulations! No unused NodeTypes could be found :)</success>');
            return;
        }

        $this->outputLine('<b>There are %d unused NodeTypes in your content repository:</b>', [count($results)]);
        $this->outputLine();

        foreach ($results as $key => $value) {
            $this->outputLine('%s (%d)', [$key, $value]);
        }
    }

    /**
     * Shows a list of context paths that belong to a specific node type
     *
     * @param string $nodeType The node type to search for
     * @param string $workspaces Comma-separated list of workspaces to consider or _all if you want to check all
     * @param integer $limit Limit he number of occurences
     * @param integer $startAt The result index to start the report at
     * @return void
     * @throws \Neos\Eel\Exception
     */
    public function occurencesCommand($nodeType, $workspaces = '_all', $limit = 5, $startAt = 1)
    {
        $this->outputReportHeadline('Occurences of %s', [$nodeType]);

        $workspaces = $this->resolveWorkspaces($workspaces);
        $dimensionPresets = $this->contentDimensionCombinator->getAllAllowedCombinations();

        $noOccurencesFound = true;
        $nodeCounter = 0;
        foreach ($workspaces as $workspace) {
            foreach ($dimensionPresets as $dimensionPreset) {
                $context = $this->createContentContext($workspace->getName(), $dimensionPreset);
                $flowQuery = new FlowQuery([$context->getRootNode()]);
                $nodes = $flowQuery->find(sprintf('[instanceof %s]', $nodeType));
                $count = count($nodes);

                if ($count > 0) {
                    $noOccurencesFound = false;
                }

                if (($nodeCounter - $startAt) >= $limit) {
                    $nodeCounter += $count;
                    continue;
                }

                foreach ($nodes as $node) {
                    if (++$nodeCounter >= $startAt && ($nodeCounter - $startAt) < $limit) {
                        $closestDocumentNode = $this->getClosestDocumentNode($node);

                        $this->outputLine('<b>%d.:</b>', [$nodeCounter]);
                        $this->outputLine('<b>Context path:</b> %s', [$node->getContextPath()]);
                        $this->outputWorkspace($workspace);
                        $this->outputLine();
                        $this->outputDimensionPreset($dimensionPreset);
                        $this->outputLine();
                        $this->outputLine('<b>Link:</b> %s', [
                            $closestDocumentNode ? $this->nodeUriService->buildUriFromNode(
                                $closestDocumentNode
                            ) : 'No Document found'
                        ]);
                        $this->outputLine();
                    }
                }
            }
        }

        if ($nodeCounter > $limit) {
            $this->outputLine('There were %d occurences in total, but the number of results has been limited to %d', [
                $nodeCounter, $limit
            ]);
        }

        if ($noOccurencesFound) {
            $this->outputLine('No occurences found.');
        }
    }

    /**
     * List node types
     *
     * @param string $superType List all subtypes of a given node type
     * @param string $filter Filter your results by a glob pattern
     * @param boolean $abstract Include abstract node types?
     * @param boolean $oneline Make the report shorter
     * @return void
     */
    public function nodeTypesCommand($superType = 'Neos.Neos:Node', $filter = '', $abstract = false, $oneline = false)
    {
        $this->outputReportHeadline('All SubNodeTypes of %s', [$superType]);

        $nodeTypes = $this->nodeTypeManager->getSubNodeTypes($superType, $abstract);

        foreach ($nodeTypes as $name => $nodeType) {
            if ($filter) {
                if (!fnmatch($filter, $name)) {
                    continue;
                }
            }

            if ($oneline) {
                $this->outputLine($name);
                continue;
            }

            $this->outputLine('<b>%s</b>', [$name]);
            $this->outputLine('<b>abstract:</b> %s', [$nodeType->isAbstract() ? 'true' : 'false']);
            $this->outputLine('<b>aggregate:</b> %s', [$nodeType->isAggregate() ? 'true' : 'false']);
            $this->outputLine('<b>label:</b> %s', [$nodeType->getLabel()]);
            $this->outputLine();
        }
    }

    /**
     * Get a list of all places where the given node type is allowed to inserted
     *
     * @param string $nodeType The node type to check for
     * @param string $filter Filter your results by a glob pattern
     * @return void
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     */
    public function whereAllowedCommand($nodeType, $filter = '')
    {
        $nodeType = $this->nodeTypeManager->getNodeType($nodeType);
        /** @var array|NodeType[] $nodeTypes */
        $nodeTypes = $this->nodeTypeManager->getNodeTypes(false);

        foreach ($nodeTypes as $referenceNodeType) {
            if ($filter && !fnmatch($filter, $referenceNodeType->getName())) {
                continue;
            }

            $directlyAllowed = $referenceNodeType->allowsChildNodeType($nodeType);
            $nodeTypeHasBeenPrinted = false;

            if ($directlyAllowed) {
                $this->outputLine('<b>%s</b>', [$referenceNodeType->getName()]);
                $nodeTypeHasBeenPrinted = true;
            }

            foreach ($referenceNodeType->getAutoCreatedChildNodes() as
                $autoCreatedChildNodeName => $autoCreatedChildNodeTypeName) {

                if ($referenceNodeType->allowsGrandchildNodeType($autoCreatedChildNodeName, $nodeType)) {
                    if (!$nodeTypeHasBeenPrinted) {
                        $this->outputLine('%s', [$referenceNodeType->getName()]);
                    }

                    $this->outputLine('    <b>%s</b>', [$autoCreatedChildNodeName]);
                }
            }
        }
    }

    /**
     * Get all uris in your system
     *
     * @param string $nodeType Limit your results to a certain node type (must inherit from Neos.Neos:Document)
     * @param string $filter Filter the node types to consider by a glob pattern
     * @param string $workspace The workspace for which to generate the uris
     * @param boolean $verbose Increase verbosity
     * @param integer $limit Limit the number of results
     * @return void
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     * @throws \Neos\Eel\Exception
     */
    public function urisCommand($nodeType = 'Neos.Neos:Document', $filter = '', $workspace = 'live', $verbose = false, $limit = 0)
    {
        $this->outputReportHeadline('All Uris for %s', [$nodeType]);
        $nodeType = $this->nodeTypeManager->getNodeType($nodeType);

        if (!$nodeType->isOfType('Neos.Neos:Document')) {
            $this->outputLine('<error>%s does not inherit from Neos.Neos:Document</error>', [$nodeType->getName()]);
            exit(0);
        }

        $dimensionPresets = $this->contentDimensionCombinator->getAllAllowedCombinations();

        $lineCount = 0;
        foreach ($dimensionPresets as $dimensionPreset) {
            $context = $this->createContentContext($workspace, $dimensionPreset);
            $flowQuery = new FlowQuery([$context->getRootNode()]);
            $nodes = $flowQuery->find(sprintf('[instanceof %s]', $nodeType->getName()));
            $errorCount = 0;

            foreach ($nodes as $node) {
                if ($limit > 0 && $lineCount++ >= $limit) {
                    break;
                }
                try {
                    $this->outputLine(
                        $this->nodeUriService->buildUriFromNode(
                            $node
                        )
                    );
                } catch(\Exception $e) {
                    $errorCount++;

                    if ($verbose) {
                        $this->outputLine('[ERROR]: %s', [$e->getMessage()]);
                    }
                }
            }

            if ($errorCount) {
                $this->outputLine();
                $this->outputLine('(!) There were %d errors during this report', [$errorCount]);
            }
        }
    }

    /**
     * Find the closest document node to a given node or that node itself, if
     * it already is a document node
     *
     * @param NodeInterface $node
     * @return NodeInterface
     * @throws \Neos\Eel\Exception
     */
    protected function getClosestDocumentNode(NodeInterface $node)
    {
        if ($node->getNodeType()->isOfType('TYPO.Neos:Document')) {
            return $node;
        }

        $flowQuery = new FlowQuery(array($node));
        return $flowQuery->closest('[instanceof Neos.Neos:Document]')->get(0);
    }

    /**
     * Helper method to output report headlines
     *
     * @param string $reportName The actual headline (can contain sprintf-style placeholders)
     * @param string $substitutions Substitution values
     * @return void
     */
    protected function outputReportHeadline($reportName, $substitutions = [])
    {
        $this->outputLine();
        $this->outputLine('========================================= REPORT =============================================');
        $this->outputLine('==  ' . $reportName, $substitutions);
        $this->outputLine('==============================================================================================');
        $this->outputLine();
    }

    /**
     * Resolve a comma separated list of workspace names to actual workspaces
     *
     * @param string $workspaces comma separated list of workspace names or '_all'
     * @return array<Workspace>
     */
    protected function resolveWorkspaces($workspaces)
    {
        if ($workspaces === '_all') {
            $workspaces = $this->workspaceRepository->findAll();
        } else {
            $workspaceNames = explode(',', $workspaces);
            $workspaces = [];

            foreach($workspaceNames as $workspaceName) {
                $workspaces[] = $this->workspaceRepository->findOneByName($workspaceName);
            }
        }

        return $workspaces;
    }

    /**
     * Helper method to display workspace information
     *
     * @param Workspace $workspace
     * @return void
     */
    protected function outputWorkspace($workspace)
    {
        $this->output('<b>Workspace:</b> %s ', [$workspace->getName()]);
    }

    /**
     * Helper method to display dimension preset information
     *
     * @param array $dimensionPreset
     * @return void
     */
    protected function outputDimensionPreset($dimensionPreset)
    {
        $dimensions = [];
        foreach ($dimensionPreset as $name => $configuration) {
            $dimensions[] = sprintf('[%s: %s]', $name, implode(',', $configuration));
        }

        $this->output('<b>Dimensions:</b> %s ', [implode($dimensions)]);
    }
}
