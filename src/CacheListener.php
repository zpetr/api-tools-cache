<?php
namespace zPetr\Cache;

use Laminas\Cache\StorageFactory;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Headers;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;

/**
 * Since caching dynamic or not intended for caching
 * data could be worse than not caching at all,
 * instructions disabling cache should always win.
 */
class CacheListener extends AbstractListenerAggregate
{
    /**
     * @var array
     */
    protected $cacheConfig = [];

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var StorageFactory;
     */
    protected $cacheStorage;

    /**
     * @var string
     */
    protected $cachedValue;

    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], -1000);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 1000);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, [$this, 'onResponse'], -1000);
    }

    /**
     * Checks whether to handle this status code.
     *
     * @param  HttpResponse $response
     * @return boolean
     */
    public function checkStatusCode(HttpResponse $response)
    {
        // Only 200 responses are cached by default
        if (empty($this->config['http_codes_black_list'])) {
            return $response->isOk();
        }

        $statusCode = $response->getStatusCode();

        return ! in_array($statusCode, (array) $this->config['http_codes_black_list']);
    }

    /**
     * @return array
     */
    public function getCacheConfig()
    {
        return $this->cacheConfig;
    }

    /**
     * Checks whether there is a config for this HTTP method.
     *
     * @return boolean
     */
    public function hasCacheConfig()
    {
        return ! empty($this->cacheConfig);
    }

    /**
     * Get configured cache storage
     *
     * @return \Laminas\Cache\Storage
     */
    protected function getCacheStorage()
    {
        if(!isset($this->cacheStorage)){
            $this->cacheStorage = StorageFactory::factory($this->config['cache']);
        }
        return $this->cacheStorage;
    }

    /**
     * @param MvcEvent $e
     */
    public function onResponse(MvcEvent $e)
    {
        if (empty($this->config['enable']) && !$this->hasCacheConfig()) {
            return;
        }

        /* @var $response HttpResponse */
        $response = $e->getResponse();

        if (! $response instanceof HttpResponse) {
            return;
        }

        if (! $this->checkStatusCode($response)) {
            return;
        }


        /** @var $request HttpRequest */
        $request = $e->getRequest();

        /* @var $headers Headers */
        $headers = $response->getHeaders();

        $content = $response->getContent();
        if(empty($this->cachedValue)){
            $this->saveCache($content,$headers);
        }
    }

    /**
     * @param MvcEvent $e
     */
    public function onRoute(MvcEvent $e)
    {
        if (empty($this->config['enable'])) {
            return;
        }

        /* @var $request HttpRequest */
        $request = $e->getRequest();
        if (! $request instanceof HttpRequest) {
            return;
        }

        if (empty($this->config['controllers'])) {
            $this->cacheConfig = [];
            return;
        }

        if(empty($this->config['cache'])){
            $this->cacheConfig = [];
            return;
        }

        $cacheConfig = $this->config['controllers'];
        $routeMatch  = $e->getRouteMatch();

        $action      = $routeMatch->getParam('action');
        $controller  = $routeMatch->getParam('controller');
        $routeName   = $routeMatch->getMatchedRouteName();

        /*
         * Searches, case sensitive, in this very order:
         * a matching route name in config
         * if not found, a matching "controller::action" name
         * if not found, a matching "controller" name
         * if not found, a matching regex
         * if not found, a wildcard (default)
         */
        if (! empty($cacheConfig[$routeName])) {
            $controllerConfig = $cacheConfig[$routeName];
        } elseif (! empty($cacheConfig["$controller::$action"])) {
            $controllerConfig = $cacheConfig["$controller::$action"];
        } elseif (! empty($cacheConfig[$controller])) {
            $controllerConfig = $cacheConfig[$controller];
        } elseif (! empty($this->config['regex_delimiter'])) {
            foreach ($cacheConfig as $key => $config) {
                if (substr($key, 0, 1) === $this->config['regex_delimiter']) {
                    if (preg_match($key, $routeName)
                        || preg_match($key, "$controller::$action")
                        || preg_match($key, $controller)
                    ) {
                        $controllerConfig = $config;
                        break;
                    }
                }
            }
        } elseif (! empty($cacheConfig['*'])) {
            $controllerConfig = $cacheConfig['*'];
        } else {
            $this->cacheConfig = [];
            return;
        }

        $method = strtolower($request->getMethod());

        if (! empty($controllerConfig[$method])) {
            $methodConfig = $controllerConfig[$method];
        } elseif (! empty($controllerConfig['*'])) {
            $methodConfig = $controllerConfig['*'];
        } elseif (! empty($cacheConfig['*'][$method])) {
            $methodConfig = $cacheConfig['*'][$method];
        } elseif (! empty($cacheConfig['*']['*'])) {
            $methodConfig = $cacheConfig['*']['*'];
        } else {
            $this->cacheConfig = [];
            return;
        }

        $this->cacheConfig = $methodConfig;
        if(!isset($this->cacheConfig['key'])){
            $this->cacheConfig['key'] = array();
        }
        $this->cacheConfig['key'][] = $request->getRequestUri();

        $cacheValue = $this->getCache();
        if(!empty($cacheValue)){
            $e->stopPropagation(true);
            $this->cachedValue = $cacheValue;
            return $cacheValue;
        }
    }

    /**
     * @param MvcEvent $e
     */
    public function onDispatch(MvcEvent $e)
    {
        if($this->hasCacheConfig() && !empty($this->cachedValue)){
            $e->stopPropagation(true);
            $response = $e->getResponse();
            $response->setContent($this->cachedValue);
            if($headers = $this->getCacheHeaders()){
                $response->getHeaders()->addHeaders($headers);
            }
            return $response;
        }
        return;
    }

    /**
     * Sets cache config.
     *
     * @param  array $config
     * @return self
     */
    public function setCacheConfig(array $cacheConfig)
    {
        $this->cacheConfig = $cacheConfig;
        return $this;
    }

    /**
     * Sets config.
     *
     * @param  array $config
     * @return self
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
        return $this;
    }

    public function saveCache($content,$headers)
    {
        $cache = $this->getCacheStorage();
        $cacheConfig = $this->getCacheConfig();

        if(!empty($this->getKey())) {
            $cache->setItem($this->getKey(), $content);
            $cache->setItem($this->getKey() . '_HEADERS', serialize($headers->toArray()));

            if(method_exists($cache,'setTags') && !empty($cacheConfig['tag'])){
                if(!is_array($cacheConfig['tag'])){
                    $cacheConfig['tag'] = array($cacheConfig['tag']);
                }
                $cache->setTags($this->getKey(),$cacheConfig['tag']);
            }
        }
    }

    public function getCache()
    {
        $cache = $this->getCacheStorage();
        return $cache->getItem($this->getKey());
    }

    public function getCacheHeaders()
    {
        $cache = $this->getCacheStorage();
        return unserialize($cache->getItem($this->getKey().'_HEADERS'));
    }

    protected function getKey()
    {
        $config = $this->cacheConfig;
        if(!empty($config['key'])){
            return md5(str_replace(array('/','.','\\','?','%','=',','),'_',implode('_',$config['key'])));
        }
        return;
    }
}