<?php
namespace Sitegeist\Janitor\NodeTypePostprocessor;

use Neos\ContentRepository\NodeTypePostprocessor\NodeTypePostprocessorInterface;
use Neos\ContentRepository\Domain\Model\NodeType;

/**
 * Add default labels and translations to nodetypes and properties
 */
class IntegratorHelpMessagePostprocessor implements NodeTypePostprocessorInterface
{

    /**
     * @param NodeType $nodeType (uninitialized) The node type to process
     * @param array $configuration input configuration
     * @param array $options The processor options
     * @return void
     */
    public function process(NodeType $nodeType, array &$configuration, array $options): void
    {
        if (isset($configuration['ui'])) {
            $configuration['ui']['help']['message'] = 'NodeType: ' . $nodeType->getName();
        }

        foreach ($configuration['properties'] as $propertyName => &$propertyConfiguration) {
            if (isset($propertyConfiguration['ui']) && isset($propertyConfiguration['type'])) {
                $propertyConfiguration['ui']['help']['message'] = 'property: ' . $propertyName .' ,type: ' . $propertyConfiguration['type'];
            }
        }
    }
}
