<?php
namespace Sitegeist\Janitor\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Routing\UriBuilder as FlowUriBuilder;

class UriBuilder extends FlowUriBuilder
{
    /**
     * @Flow\Inject
     * @var BaseUriProvider
     */
    protected $baseUriProvider;
}
