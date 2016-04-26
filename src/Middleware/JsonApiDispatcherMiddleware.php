<?php
namespace WoohooLabs\YinMiddlewares\Middleware;

use Interop\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use WoohooLabs\Yin\JsonApi\Exception\ExceptionFactory;
use WoohooLabs\Yin\JsonApi\Exception\ExceptionFactoryInterface;
use WoohooLabs\Yin\JsonApi\JsonApi;
use WoohooLabs\Yin\JsonApi\Request\ServerRequestInterface;
use WoohooLabs\Yin\JsonApi\Schema\Error;
use WoohooLabs\Yin\JsonApi\Document\ErrorDocument;

class JsonApiDispatcherMiddleware
{
    /**
     * @var \WoohooLabs\Yin\jsonApi\Exception\ExceptionFactoryInterface
     */
    private $exceptionFactory;

    /**
     * @var \Interop\Container\ContainerInterface
     */
    protected $container;

    /**
     * @var string
     */
    protected $handlerAttribute;

    /**
     * @param \WoohooLabs\Yin\jsonApi\Exception\ExceptionFactoryInterface $exceptionFactory
     * @param \Interop\Container\ContainerInterface $container
     * @param string $handlerAttribute
     */
    public function __construct(
        ExceptionFactoryInterface $exceptionFactory = null,
        ContainerInterface $container = null,
        $handlerAttribute = "__callable"
    ) {
        $this->exceptionFactory = $exceptionFactory !== null ? $exceptionFactory : new ExceptionFactory();
        $this->container = $container;
        $this->handlerAttribute = $handlerAttribute;
    }

    /**
     * @param \WoohooLabs\Yin\JsonApi\Request\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param callable $next
     * @return void|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $callable = $request->getAttribute($this->handlerAttribute);

        if ($callable === null) {
            return $this->getDispatchErrorResponse($response);
        }

        $jsonApi = new JsonApi($request, $response, $this->exceptionFactory);

        if (is_array($callable) && is_string($callable[0])) {
            $object = $this->container !== null ? $this->container->get($callable[0]) : new $callable[0];
            $response = $object->{$callable[1]}($jsonApi);
        } else {
            if (!is_callable($callable)) {
                $callable = $this->container->get($callable);
            }
            $response = call_user_func($callable, $jsonApi);
        }

        return $next($request, $response);
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface $response
     */
    protected function getDispatchErrorResponse(ResponseInterface $response)
    {
        return $this->getErrorDocument($this->getDispatchError())->getResponse($response);
    }

    /**
     * @return \WoohooLabs\Yin\JsonApi\Schema\Error
     */
    protected function getDispatchError()
    {
        $error = new Error();
        $error->setStatus(404);
        $error->setTitle("Resource was not not found!");

        return $error;
    }

    /**
     * @param \WoohooLabs\Yin\JsonApi\Schema\Error $error
     * @return \WoohooLabs\Yin\JsonApi\Document\ErrorDocument
     */
    protected function getErrorDocument(Error $error)
    {
        $errorDocument = new ErrorDocument();
        $errorDocument->addError($error);

        return $errorDocument;
    }
}
