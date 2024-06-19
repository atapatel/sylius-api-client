<?php

declare(strict_types=1);

/*
 * This software may be modified and distributed under the terms
 * of the MIT license. See the LICENSE file for details.
 */

namespace FAPI\Sylius\Http;

use Http\Client\Common\Plugin;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Configure an HTTP client.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ClientConfigurator
{
    /**
     * @var string
     */
    private $endpoint = 'https://sylius.com/api/v1';

    /**
     * @var UriFactory
     */
    private $uriFactory;

    /**
     * This is the client we use for actually sending the requests.
     *
     * @var HttpClient
     */
    private $httpClient;

    /**
     * This is the client wrapping the $httpClient.
     *
     * @var PluginClient
     */
    private $configuredClient;

    /**
     * @var Plugin[]
     */
    private $prependPlugins = [];

    /**
     * @var Plugin[]
     */
    private $appendPlugins = [];

    /**
     * True if we should create a new Plugin client at next request.
     *
     * @var bool
     */
    private $configurationModified = true;

    public function __construct(ClientInterface $httpClient = null, UriFactoryInterface $uriFactory = null)
    {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->uriFactory = $uriFactory ?? Psr17FactoryDiscovery::findUriFactory();
    }

    public function createConfiguredClient(): ClientInterface
    {
        if ($this->configurationModified) {
            $this->configurationModified = false;
            $plugins = $this->prependPlugins;
            $plugins[] = new Plugin\AddHostPlugin($this->uriFactory->createUri($this->endpoint));
            $plugins[] = new Plugin\HeaderDefaultsPlugin(
                [
                    'User-Agent' => 'FriendsOfApi/sylius (https://github.com/FriendsOfApi/sylius)',
                    'Content-type' => 'application/json',
                ]
            );

            $this->configuredClient = new PluginClient($this->httpClient, \array_merge($plugins, $this->appendPlugins));
        }

        return $this->configuredClient;
    }

    public function setEndpoint(string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function appendPlugin(Plugin ...$plugin): void
    {
        $this->configurationModified = true;
        foreach ($plugin as $p) {
            $this->appendPlugins[] = $p;
        }
    }

    public function prependPlugin(Plugin ...$plugin): void
    {
        $this->configurationModified = true;
        $plugin = \array_reverse($plugin);
        foreach ($plugin as $p) {
            \array_unshift($this->prependPlugins, $p);
        }
    }

    /**
     * Remove a plugin by its fully qualified class name (FQCN).
     */
    public function removePlugin(string $fqcn): void
    {
        foreach ($this->prependPlugins as $idx => $plugin) {
            if ($plugin instanceof $fqcn) {
                unset($this->prependPlugins[$idx]);
                $this->configurationModified = true;
            }
        }

        foreach ($this->appendPlugins as $idx => $plugin) {
            if ($plugin instanceof $fqcn) {
                unset($this->appendPlugins[$idx]);
                $this->configurationModified = true;
            }
        }
    }
}
