<?php
namespace Sitegeist\Janitor\Service;

use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Http\BaseUriProvider as FlowBaseUriProvider;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class BaseUriProvider
 * @package Sitegeist\Janitor\Service
 */
class BaseUriProvider extends  FlowBaseUriProvider
{
    /**
     * Return a fake base uri to be used for rendering relative urls in cli request
     *
     * @return UriInterface
     */
    public function getConfiguredBaseUriOrFallbackToCurrentRequest(ServerRequestInterface $fallbackRequest = null): UriInterface
    {
        return new Uri('https://domain.tld');
    }
}
