<?php

namespace Cmp\Storage\Strategy;

use Cmp\Storage\Exception\AdapterException;
use Cmp\Storage\Exception\FileNotFoundException;
use Cmp\Storage\Exception\StorageException;
use Psr\Log\LogLevel;

/**
 * Class CallAllStrategy
 *
 * @package Cmp\Storage\Strategy
 */
class CallAllStrategy extends AbstractStorageCallStrategy
{


    public function getStrategyName()
    {
        return "CallAllStrategy";
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return bool
     */
    public function exists($path)
    {
        $fn = function ($adapter) use ($path) {
            return $adapter->exists($path);
        };

        return $this->runAllAndLog($fn);
    }


    /**
     * Read a file.
     *
     * @param string $path The path to the file.
     *
     * @throws \Cmp\Storage\Exception\FileNotFoundException
     *
     * @return string The file contents or false on failure.
     */
    public function get($path)
    {
        $fn = function ($adapter) use ($path) {
            return $adapter->get($path);
        };

        return $this->runOneAndLog($fn, $path);
    }

    /**
     * Retrieves a read-stream for a path.
     *
     * @param string $path The path to the file.
     *
     * @throws \Cmp\Storage\Exception\FileNotFoundException
     *
     * @return resource The path resource or false on failure.
     */
    public function getStream($path)
    {
        $fn = function ($adapter) use ($path) {
            return $adapter->getStream($path);
        };

        return $this->runOneAndLog($fn, $path);
    }

    /**
     * Rename a file.
     *
     * @param string $path    Path to the existing file.
     * @param string $newpath The new path of the file.
     *
     * @throws \Cmp\Storage\Exception\FileExistsException   Thrown if $newpath exists.
     * @throws \Cmp\Storage\Exception\FileNotFoundException Thrown if $path does not exist.
     *
     * @return bool True on success, false on failure.
     */
    public function rename($path, $newpath)
    {
        $fn = function ($adapter) use ($path, $newpath) {
            return $adapter->rename($path, $newpath);
        };

        return $this->runAllAndLog($fn);
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @throws \Cmp\Storage\Exception\FileNotFoundException
     *
     * @return bool True on success, false on failure.
     */
    public function delete($path)
    {
        $fn = function ($adapter) use ($path) {
            return $adapter->delete($path);
        };

        return $this->runAllAndLog($fn);
    }

    /**
     * Create a file or update if exists. It will create the missing folders.
     *
     * @param string $path     The path to the file.
     * @param string $contents The file contents.
     *
     * @return bool True on success, false on failure.
     * @throws \Cmp\Storage\Exception\InvalidPathException
     */
    public function put($path, $contents)
    {
        $fn = function ($adapter) use ($path, $contents) {
            return $adapter->put($path, $contents);
        };

        return $this->runAllAndLog($fn);
    }

    /**
     * Create a file or update if exists. It will create the missing folders.
     *
     * @param string   $path     The path to the file.
     * @param resource $resource The file handle.
     *
     * @throws \Cmp\Storage\Exception\InvalidArgumentException Thrown if $resource is not a resource.
     *
     * @return bool True on success, false on failure.
     */
    public function putStream($path, $resource)
    {
        $fn = function ($adapter) use ($path, $resource) {
            return $adapter->putStream($path, $resource);
        };

        return $this->runAllAndLog($fn);
    }


    /**
     * @param callable $fn
     *
     * @return bool
     */
    private function runAllAndLog(callable $fn)
    {
        $result = false;

        foreach ($this->getAdapters() as $adapter) {
            try {
                $result = $fn($adapter) || $result;
            } catch (\Exception $e) {
                $this->logAdapterException($adapter, $e);
            }
        }

        return $result;
    }

    /**
     * Gets one file from all the adapters
     *
     * @param callable        $fn
     *
     * @param          string $path
     *
     * @return mixed
     * @throws StorageException
     */
    private function runOneAndLog(callable $fn, $path)
    {
        $defaultException = new FileNotFoundException($path);
        foreach ($this->getAdapters() as $adapter) {
            try {
                $file = $fn($adapter);
                if ($file !== false) {
                    return $file;
                }
            } catch (\Exception $exception) {

                $defaultException = new AdapterException($path, $exception);
                $this->logAdapterException($adapter, $exception);
            }
        }

        throw $defaultException;
    }


    /**
     * @param \Cmp\Storage\AdapterInterface $adapter
     * @param \Exception                    $e
     */
    private function logAdapterException($adapter, $e)
    {
        $this->log(
            LogLevel::ERROR,
            'Adapter "'.$adapter->getName().'" fails.',
            ['exception' => $e]
        );
    }
}
