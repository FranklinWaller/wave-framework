<?php
namespace Wave\Framework\Application;

use Wave\Framework\Application\Interfaces\ControllerInterface;

/**
 * Created by PhpStorm.
 * User: daghostman
 * Date: 15/08/14
 * Time: 23:15
 */

class Core implements \Serializable, \Iterator, \Countable
{
    protected $controllers;
    private $ioc;
    protected $debug = false;

    /**
     * Setups the required properties
     */
    public function __construct($name = 'application')
    {
        ob_start();
        $this->ioc = new IoC();
        $this->controllers = new \SplQueue();

    }

    /**
     * Registers a callback to a specific pattern for
     *  later invocation. Context gets injected in to every
     *  method's constructor.
     *
     * @param $pattern string The URI pattern
     * @param $method string|array The method/s to which the route should respond
     * @param $callback callback The callback
     * @param $conditions array The current context
     * @param $handler string
     *
     * @return Core
     * @throws \InvalidArgumentException
     */
    public function controller(
        $pattern,
        $method,
        $callback,
        array $conditions = array(),
        $handler = '\Wave\Framework\Application\Controller'
    ) {
        $controller = $this->ioc->resolve($handler);

        if (!$controller instanceof ControllerInterface) {
            throw new \InvalidArgumentException("Invalid Controller handler specified");
        }

        $controller->setPattern($pattern)
            ->action($callback)
            ->via($method)
            ->conditions($conditions);

        $this->controllers->enqueue($controller);

        return $this;
    }

    /**
     * Get the number of defined Controller
     *
     * @return int The number of defined Controller
     */
    public function numControllers()
    {
        return count($this->controllers);
    }

    /**
     * Invoke all Controller registered to the specified pattern
     *
     * @param $uri string The URI of the request
     * @param $method string The method of the request
     * @param $data array Data to pass to the controller
     *
     * @throws \Exception
     */
    public function invoke($uri, $method, $data = array())
    {
        $this->controllers->setIteratorMode(
            \SplQueue::IT_MODE_FIFO | \SplQueue::IT_MODE_KEEP
        );

        $this->rewind();
        while ($this->valid()) {
            /**
             * @var \Wave\Framework\Application\Interfaces\ControllerInterface
             */
            $controller = $this->current();


            if ($controller->match($uri) && $controller->supportsHTTP($method)) {
                $controller->invoke($data);
            }

            $this->next();
        }
    }

    /**
     * Clears all registered controllers
     *
     * @return $this
     */
    public function clearControllers()
    {
        $this->controllers = new \SplQueue();

        return $this;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return $this->controllers->current();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->controllers->next();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->controllers->key();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->controllers->valid();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->controllers->rewind();
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        $container = new \SplQueue();

        $this->rewind();
        while ($this->valid()) {
            $controller = $this->current();

            if ($controller->isSerializeable()) {
                $container->enqueue($controller);
            }

            $this->next();
        }

        return serialize($container);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized <p>
     *                           The string representation of the object.
     *                           </p>
     *
     * @return void
     */
    public function unserialize($serialized)
    {
        $this->ioc = new IoC();
        $this->controllers = unserialize($serialized);
    }

    /**
     * Turns on debugging
     *
     * @return $this
     */
    public function debug()
    {
        $this->debug = true;

        return $this;
    }

    /**
     * @param \Wave\Framework\Http\Request $request
     * @param \Wave\Framework\Http\Response $response
     * @param array $data Custom data to pass to the controller
     *
     * @throws \Exception
     */
    public function run($request, $response = null, $data = array())
    {

        try {
            $this->invoke($request->uri(), $request->method(), $data);
        } catch (\Exception $e) {
            if ($this->debug) {
                ob_clean();

                echo sprintf(
                    "Error occurred: \"%s\"(%s) in %s:%s \n\r\n\r%s \n\r",
                    $e->getMessage(),
                    $e->getCode(),
                    $e->getFile(),
                    $e->getLine(),
                    print_r($e->getTraceAsString(), true)
                );
            }

            /**
             * @codeCoverageIgnore
             */
            if ($response) {
                $response->internalError();
                $response->header('Content-Type: text/plain');
                $response->send();
            }

            echo ob_get_clean();
        }
    }

    public function count()
    {
        return count($this->controllers);
    }
}