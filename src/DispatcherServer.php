<?php

namespace Swoft\Http\Server;

use Swoft\App;
use Swoft\Core\DispatcherInterface;
use Swoft\Core\RequestContext;
use Swoft\Core\RequestHandler;
use Swoft\Http\Server\Event\HttpServerEvent;
use Swoft\Http\Message\Server\Response;
use Swoft\Http\Server\Middleware\AcceptMiddleware;
use Swoft\Http\Server\Middleware\FaviconIcoMiddleware;
use Swoft\Http\Server\Middleware\HandlerAdapterMiddleware;
use Swoft\Http\Server\Middleware\ParserMiddleware;
use Swoft\Http\Server\Middleware\PoweredByMiddleware;
use Swoft\Http\Server\Middleware\RouterMiddleware;
use Swoft\Http\Server\Middleware\UserMiddleware;
use Swoft\Http\Server\Middleware\ValidatorMiddleware;

/**
 * the dispatcher of http server
 *
 * @uses      DispatcherServer
 * @version   2017年11月24日
 * @author    stelin <phpcrazy@126.com>
 * @copyright Copyright 2010-2016 swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
class DispatcherServer implements DispatcherInterface
{
    /**
     * user defined middlewares
     *
     * @var array
     */
    private $middlewares= [];

    /**
     * handler adapter
     *
     * @var string
     */
    private $handlerAdapter = HandlerAdapterMiddleware::class;

    /**
     * do dispatcher
     *
     * @param array ...$params
     *
     * @return bool|\Psr\Http\Message\ResponseInterface
     */
    public function doDispatcher(...$params)
    {
        /**
         * @var \Swoole\Http\Request  $swooleRequest
         * @var \Swoole\Http\Response $swooleResponse
         */
        list($swooleRequest, $swooleResponse) = $params;

        try {
            // before dispatcher
            $this->beforeDispatcher($swooleRequest, $swooleResponse);

            // request middlewares
            $middlewares    = $this->requestMiddlewares();
            $request        = RequestContext::getRequest();
            $requestHandler = new RequestHandler($middlewares, $this->handlerAdapter);
            $response       = $requestHandler->handle($request);

        } catch (\Throwable $throwable) {
            // Handle by ExceptionHandler
//            $response = ExceptionHandlerManager::handle($throwable);
            var_dump($throwable->getMessage(), $throwable->getFile(), $throwable->getCode());
            $response = RequestContext::getResponse();
        } finally {
            $this->afterDispatcher($response);
        }

        return $response;
    }

    /**
     * the middlewares of request
     *
     * @return array
     */
    public function requestMiddlewares()
    {
        return array_merge($this->firstMiddleware(), $this->middlewares, $this->lastMiddleware());
    }

    /**
     * the firsted middlewares of request
     *
     * @return array
     */
    public function firstMiddleware()
    {
        return [
            FaviconIcoMiddleware::class,
            PoweredByMiddleware::class,
            ParserMiddleware::class,
            RouterMiddleware::class,
            AcceptMiddleware::class,
        ];
    }

    /**
     * the lasted middlewares of request
     *
     * @return array
     */
    public function lastMiddleware()
    {
        return [
            UserMiddleware::class,
            ValidatorMiddleware::class,
        ];
    }

    /**
     * before dispatcher
     *
     * @param \Swoole\Http\Request  $request  swoole request
     * @param \Swoole\Http\Response $response swoole response
     */
    protected function beforeDispatcher(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        // Initialize Request and Response and set to RequestContent
        RequestContext::setRequest($request);
        RequestContext::setResponse($response);

        // Trigger 'Before Request' event
        App::trigger(HttpServerEvent::BEFORE_REQUEST);
    }

    /**
     * If $response is not an instance of Response,
     * usually return by Action of Controller,
     * then the auto() method will format the result
     * and return a suitable response
     *
     * @param mixed $response
     */
    protected function afterDispatcher($response)
    {
        if (!$response instanceof Response) {
            $response = RequestContext::getResponse()->auto($response);
        }

        // Handle Response
        $response->send();

        // Trigger 'After Request' event
        App::trigger(HttpServerEvent::AFTER_REQUEST);
    }
}
