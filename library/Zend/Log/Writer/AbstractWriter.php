<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Log
 */

namespace Zend\Log\Writer;

use Zend\Log\Exception;
use Zend\Log\Filter;
use Zend\Log\Formatter;
use Zend\Stdlib\ErrorHandler;

/**
 * @category   Zend
 * @package    Zend_Log
 * @subpackage Writer
 */
abstract class AbstractWriter implements WriterInterface
{
    /**
     * Filter plugins
     *
     * @var FilterPluginManager
     */
    protected $filterPlugins;
    
    /**
     * Formatter plugins
     *
     * @var FormatterPluginManager
     */
    protected $formatterPlugins;

    /**
     * Filter chain
     *
     * @var Filter\FilterInterface[]
     */
    protected $filters = array();

    /**
     * Formats the log message before writing
     *
     * @var Formatter
     */
    protected $formatter;

    /**
     * Use Zend\Stdlib\ErrorHandler to report errors during calls to write
     *
     * @var bool
     */
    protected $convertWriteErrorsToExceptions = true;

    /**
     * Error level passed to Zend\Stdlib\ErrorHandler::start for errors reported during calls to write
     *
     * @var bool
     */
    protected $errorsToExceptionsConversionLevel = E_WARNING;

    /**
     * Add a filter specific to this writer.
     *
     * @param  int|string|Filter\FilterInterface $filter
     * @param  array|null $options
     * @return AbstractWriter
     * @throws Exception\InvalidArgumentException
     */
    public function addFilter($filter, array $options = null)
    {
        if (is_int($filter)) {
            $filter = new Filter\Priority($filter);
        }

        if (is_string($filter)) {
            $filter = $this->filterPlugin($filter, $options);
        }

        if (!$filter instanceof Filter\FilterInterface) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Writer must implement %s\Filter\FilterInterface; received "%s"',
                __NAMESPACE__,
                is_object($filter) ? get_class($filter) : gettype($filter)
            ));
        }

        $this->filters[] = $filter;
        return $this;
    }

    /**
     * Get filter plugin manager
     *
     * @return FilterPluginManager
     */
    public function getFilterPluginManager()
    {
        if (null === $this->filterPlugins) {
            $this->setFilterPluginManager(new FilterPluginManager());
        }
        return $this->filterPlugins;
    }

    /**
     * Set filter plugin manager
     *
     * @param  string|FilterPluginManager $plugins
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setFilterPluginManager($plugins)
    {
        if (is_string($plugins)) {
            $plugins = new $plugins;
        }
        if (!$plugins instanceof FilterPluginManager) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Writer plugin manager must extend %s\FilterPluginManager; received %s',
                __NAMESPACE__,
                is_object($plugins) ? get_class($plugins) : gettype($plugins)
            ));
        }

        $this->filterPlugins = $plugins;
        return $this;
    }

    /**
     * Get filter instance
     *
     * @param string $name
     * @param array|null $options
     * @return Filter\FilterInterface
     */
    public function filterPlugin($name, array $options = null)
    {
        return $this->getFilterPluginManager()->get($name, $options);
    }
    
    /**
     * Get formatter plugin manager
     *
     * @return FormatterPluginManager
     */
    public function getFormatterPluginManager()
    {
        if (null === $this->formatterPlugins) {
            $this->setFormatterPluginManager(new FormatterPluginManager());
        }
        return $this->formatterPlugins;
    }
    
    /**
     * Set formatter plugin manager
     *
     * @param  string|FormatterPluginManager $plugins
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setFormatterPluginManager($plugins)
    {
        if (is_string($plugins)) {
            $plugins = new $plugins;
        }
        if (!$plugins instanceof FormatterPluginManager) {
            throw new Exception\InvalidArgumentException(sprintf(
                    'Writer plugin manager must extend %s\FormatterPluginManager; received %s',
                    __NAMESPACE__,
                    is_object($plugins) ? get_class($plugins) : gettype($plugins)
            ));
        }
    
        $this->formatterPlugins = $plugins;
        return $this;
    }
    
   
    /**
     * Get formatter instance
     *
     * @param string $name
     * @param array|null $options
     * @return Formatter\FormatterInterface
     */
    public function formatterPlugin($name, array $options = null)
    {
        return $this->getFormatterPluginManager()->get($name, $options);
    }

    /**
     * Log a message to this writer.
     *
     * @param array $event log data event
     * @return void
     */
    public function write(array $event)
    {
        foreach ($this->filters as $filter) {
            if (!$filter->filter($event)) {
                return;
            }
        }

        $errorHandlerStarted = false;

        if ($this->convertWriteErrorsToExceptions && !ErrorHandler::started()) {
            ErrorHandler::start($this->errorsToExceptionsConversionLevel);
            $errorHandlerStarted = true;
        }

        try {
            $this->doWrite($event);
        } catch (\Exception $e) {
            if ($errorHandlerStarted) {
                ErrorHandler::stop();
                $errorHandlerStarted = false;
            }
            throw $e;
        }

        if ($errorHandlerStarted) {
            $error = ErrorHandler::stop();
            $errorHandlerStarted = false;
            if ($error) {
                throw new Exception\RuntimeException("Unable to write", 0, $error);
            }
        }
    }

    /**
     * Set a new formatter for this writer
     *
     * @param  string|Formatter\FormatterInterface $formatter
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setFormatter($formatter, array $options = null)
    {
        if (is_string($formatter)) {
            $formatter = $this->formatterPlugin($formatter, $options);
        }

        if (!$formatter instanceof Formatter\FormatterInterface) {
            // This should be used instead of triggering an error, but this will require to change tests
            //throw new Exception\InvalidArgumentException(sprintf(
            //        'Formatter must implement %s\Formatter\FormatterInterface; received "%s"',
            //        __NAMESPACE__,
            //        is_object($formatter) ? get_class($formatter) : gettype($formatter)   
            //));
            trigger_error(sprintf(
                    'Formatter must implement %s\Formatter\FormatterInterface; received "%s"',
                    __NAMESPACE__,
                    is_object($formatter) ? get_class($formatter) : gettype($formatter)   
            ));
        }

        $this->formatter= $formatter;
        return $this;
    }

    /**
     * Set convert write errors to exception flag
     *
     * @param bool $ignoreWriteErrors
     */
    public function setConvertWriteErrorsToExceptions($convertErrors)
    {
        $this->convertWriteErrorsToExceptions = $convertErrors;
    }

    /**
     * Perform shutdown activities such as closing open resources
     *
     * @return void
     */
    public function shutdown()
    {}

    /**
     * Write a message to the log
     *
     * @param array $event log data event
     * @return void
     */
    abstract protected function doWrite(array $event);
}
