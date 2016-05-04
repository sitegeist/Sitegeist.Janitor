<?php
namespace Sitegeist\Janitor\Command;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Neos\Controller\CreateContentContextTrait;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;
use TYPO3\TYPO3CR\Domain\Service\ContentDimensionCombinator;
use TYPO3\TYPO3CR\Domain\Repository\WorkspaceRepository;
use Sitegeist\Janitor\TYPO3CR\Service\NodeUriService;

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
     */
    public function unusedCommand($threshold = 0, $superType = 'TYPO3.Neos:Node', $workspaces = 'live')
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
                        $this->outputLine('<b>%d.:</b>', [$nodeCounter]);
                        $this->outputLine('<b>Context path:</b> %s', [$node->getContextPath()]);
                        $this->outputWorkspace($workspace);
                        $this->outputLine();
                        $this->outputDimensionPreset($dimensionPreset);
                        $this->outputLine();
                        $this->outputLine('<b>Link:</b> %s', [
                            $this->nodeUriService->buildUriFromNode(
                                $this->getClosestDocumentNode($node)
                            )
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
    public function nodeTypesCommand($superType = 'TYPO3.Neos:Node', $filter = '', $abstract = false, $oneline = false)
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

    protected function getClosestDocumentNode(NodeInterface $node)
    {
        if ($node->getNodeType()->isOfType('TYPO.Neos:Document')) {
            return $node;
        }

        $flowQuery = new FlowQuery(array($node));
        return $flowQuery->closest('[instanceof TYPO3.Neos:Document]')->get(0);
    }

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
     * @return [type] [description]
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
