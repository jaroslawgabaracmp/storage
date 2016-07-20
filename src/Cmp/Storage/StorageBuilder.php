<?php

namespace Cmp\Storage;

use Cmp\Storage\Date\DefaultDateProvider;
use Cmp\Storage\Exception\InvalidStorageAdapterException;
use Cmp\Storage\Exception\StorageAdapterNotFoundException;
use Cmp\Storage\Exception\ThereAreNoAdaptersAvailableException;
use Cmp\Storage\Log\DefaultLogger;
use Cmp\Storage\Log\LoggerFactory;
use Cmp\Storage\Log\StdOutputLogger;
use Cmp\Storage\Strategy\AbstractStorageCallStrategy;

use Cmp\Storage\Strategy\CallAllStrategy;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Class StorageBuilder
 *
 * @package Cmp\Storage
 */
class StorageBuilder
{

    /**
     * @var
     */
    private $strategy;
    /**
     * @var
     */
    private $logger;
    /**
     * @var array
     *
     */
    private $adapters;
    /**
     * @var array
     */
    private static $builtinAdapters = [];
    /**
     * @var bool
     */
    private static $builtInAdaptersLoaded = false;

    /**
     * StorageBuilder constructor.
     */
    public function __construct()
    {
        $this->adapters = [];
    }


    /**
     * @param AbstractStorageCallStrategy $strategy
     *
     * @return $this
     */
    public function setStrategy(AbstractStorageCallStrategy $strategy)
    {
        $this->strategy = $strategy;

        return $this;
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param       $adapter
     * @param array $config
     *
     * @return $this
     * @throws InvalidStorageAdapterException
     * @throws StorageAdapterNotFoundException
     */
    public function addAdapter($adapter, array $config = [])
    {

        if (is_string($adapter)) {
            $this->addBuiltinAdapters();
            if (!array_key_exists($adapter, self::$builtinAdapters)) {
                throw new StorageAdapterNotFoundException("Builtin storage \"$adapter\" not found");
            }
            $this->registerAdapter(self::$builtinAdapters[$adapter]);

            return $this;
        }

        if ($adapter instanceof AdapterInterface) {
            $this->registerAdapter($adapter);

            return $this;
        }

        if ($adapter instanceof FactoryAdapterInterface) {
            $this->registerAdapter($adapter->create($config));

            return $this;
        }


        throw new InvalidStorageAdapterException("Invalid storage adapter: ".get_class($adapter));
    }


    /**
     * @param                 $callStrategy
     * @param LoggerInterface $logger
     *
     * @return VirtualStorage
     * @throws ThereAreNoAdaptersAvailableException
     */
    public function build(AbstractStorageCallStrategy $callStrategy = null, LoggerInterface $logger = null)
    {

        if (!$this->hasLoadedAdapters()) {
            $this->addAdapter($this->getDefaultBuiltinAdapter());
        }

        if ($callStrategy != null) {
            $this->setStrategy($callStrategy);
        }
        if ($logger != null) {
            $this->setLogger($logger);
        }

        $strategy = $this->getStrategy();
        $strategy->addAdapters($this->adapters);
        $strategy->setLogger($this->getLogger());

        return new VirtualStorage($strategy);
    }

    /**
     * @return mixed
     */
    public function getStrategy()
    {
        if ($this->strategy == null) {
            return $this->getDefaultCallStrategy();
        }

        return $this->strategy;
    }


    /**
     * @return mixed
     */
    public function getLogger()
    {
        if ($this->logger == null) {
            return $this->getDefaultLogger();
        }

        return $this->logger;
    }


    /**
     * @param AdapterInterface $adapter
     */
    private function registerAdapter(AdapterInterface $adapter)
    {
        if ($this->logger && $adapter instanceof LoggerAwareInterface) {
            $adapter->setLogger($this->logger);
        }
        $this->adapters[] = $adapter;
    }

    /**
     * @return bool
     */
    public function hasLoadedAdapters()
    {
        return !empty($this->adapters);
    }

    /**
     * @return $this
     */
    private function addBuiltinAdapters()
    {

        if (!self::$builtInAdaptersLoaded) {
            self::$builtInAdaptersLoaded = true;
            foreach (glob(__DIR__.DIRECTORY_SEPARATOR."Adapter".DIRECTORY_SEPARATOR."*.php") as $adapterFileName) {
                $className = __NAMESPACE__.'\\'."Adapter".'\\'.basename($adapterFileName, ".php");
                try {
                    $class = new $className;
                    self::$builtinAdapters[$class->getName()] = $class;
                } catch (\Exception $e) {
                    $this->getLogger()->info('Impossible start "'.$className.'" client');
                }
            }
        }

        return $this;
    }


    /**
     * @return AbstractStorageCallStrategy
     */
    private function getDefaultCallStrategy()
    {
        return new CallAllStrategy();
    }

    private function getDefaultLogger()
    {
        return LoggerFactory::create();
    }

    private function getDefaultBuiltinAdapter()
    {
        return 'FileSystem';
    }
}
