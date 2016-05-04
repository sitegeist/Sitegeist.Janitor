<?php
namespace Sitegeist\Janitor\TYPO3CR\Service;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Mvc\Routing\RouterInterface;
use TYPO3\Flow\Mvc\Routing\UriBuilder;
use TYPO3\Flow\Mvc\ActionRequest;
use TYPO3\Neos\Domain\Service\NodeShortcutResolver;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * @Flow\Scope("singleton")
 */
class NodeUriService
{
    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @Flow\Inject
     * @var NodeShortcutResolver
     */
    protected $nodeShortcutResolver;

    /**
     * @Flow\Inject
     * @var RouterInterface
     */
    protected $router;

    /**
     * Constructor
     */
    public function initializeObject() {
        putenv("FLOW_REWRITEURLS=TRUE");
        $this->initializeRouter();
    }

    /**
     * Initialize the injected router-object
     *
     * @return void
     */
    protected function initializeRouter() {
        $routesConfiguration = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_ROUTES
        );

        $this->router->setRoutesConfiguration($routesConfiguration);
    }

    /**
     * Get the URI to a given node instance
     *
     * @param NodeInterface $node
     * @param boolean $resolveShortcuts
     * @return string The rendered URI
     */
    public function buildUriFromNode(NodeInterface $node, $resolveShortcuts = TRUE) {
        if ($resolveShortcuts) {
            $resolvedNode = $this->nodeShortcutResolver->resolveShortcutTarget($node);
        } else {
            $resolvedNode = $node;
        }

        if (is_string($resolvedNode)) {
            return $resolvedNode;
        }

        if (!$resolvedNode instanceof NodeInterface) {
            throw new \Exception(sprintf('Could not resolve shortcut target for node "%s"', $node->getPath()),
                1414771137);
        }

        // create a dummy parent request
        $httpRequest = Request::create(new Uri('http://neos.io'));
        $request = new ActionRequest($httpRequest);

        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($request);

        return $uriBuilder
            ->reset()
            ->setFormat($request->getFormat())
            ->uriFor('show', array('node' => $resolvedNode), 'Frontend\Node', 'TYPO3.Neos');
    }
}
