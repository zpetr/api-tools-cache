<?php
namespace zPetr\Cache;

use Interop\Container\ContainerInterface;

class CacheListenerFactory
{
    /**
     * Factory for producing an CacheListener.
     *
     * Duck-types on the $container type to allow usage with
     * laminas-servicemanager versions 2.5+ and 3.0+.
     *
     * @param  ContainerInterface $container
     * @return CacheListener
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = [];
        if ($container->has('config')) {
            $config = $container->get('config');
            if (isset($config['zpetr-api-tools-cache'])) {
                $config = $config['zpetr-api-tools-cache'];
            }
        }

        $cacheListener = new CacheListener();
        $cacheListener->setConfig($config);

        return $cacheListener;
    }
}