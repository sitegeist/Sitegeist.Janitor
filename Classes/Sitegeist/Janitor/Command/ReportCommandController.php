<?php
namespace Sitegeist\Janitor\Command;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;
use TYPO3\TYPO3CR\Domain\Service\ContentDimensionCombinator;
use TYPO3\TYPO3CR\Domain\Repository\WorkspaceRepository;
use TYPO3\Neos\Controller\CreateContentContextTrait;
use TYPO3\Eel\FlowQuery\FlowQuery;

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

        $this->outputLine();
        $this->outputLine('===================== REPORT =====================');
        $this->outputLine();

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
