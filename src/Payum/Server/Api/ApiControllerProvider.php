<?php
namespace Payum\Server\Api;

use Payum\Server\Api\Controller\GatewayController;
use Payum\Server\Api\Controller\GatewayMetaController;
use Payum\Server\Api\Controller\PaymentController;
use Payum\Server\Application;
use Payum\Server\Api\Controller\RootController;
use Silex\Application as SilexApplication;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ApiControllerProvider implements ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function register(SilexApplication $app)
    {
        $app['payum.api.controller.root'] = $app->share(function() {
            return new RootController();
        });

        $app['payum.api.controller.payment'] = $app->share(function() use ($app) {
            return new PaymentController(
                $app['payum.security.token_factory'],
                $app['payum.security.http_request_verifier'],
                $app['payum'],
                $app['api.view.order_to_json_converter'],
                $app['form.factory'],
                $app['api.view.form_to_json_converter'],
                $app['url_generator']
            );
        });

        $app['payum.api.controller.gateway'] = $app->share(function() use ($app) {
            return new GatewayController(
                $app['form.factory'],
                $app['url_generator'],
                $app['api.view.form_to_json_converter'],
                $app['payum.gateway_config_storage'],
                $app['api.view.gateway_config_to_json_converter']
            );
        });

        $app['payum.api.controller.gateway_meta'] = $app->share(function() use ($app) {
            return new GatewayMetaController(
                $app['form.factory'],
                $app['api.view.form_to_json_converter'],
                $app['payum']
            );
        });

        $app->get('/', 'payum.api.controller.root:rootAction')->bind('api_root');
        $app->get('/payments/meta', 'payum.api.controller.payment:metaAction')->bind('payment_meta');
        $app->get('/payments/{id}', 'payum.api.controller.payment:getAction')->bind('payment_get');
        $app->put('/payments/{id}', 'payum.api.controller.payment:updateAction')->bind('payment_update');
        $app->delete('/payments/{id}', 'payum.api.controller.payment:deleteAction')->bind('payment_delete');
        $app->post('/payments', 'payum.api.controller.payment:createAction')->bind('payment_create');
        $app->get('/payments', 'payum.api.controller.payment:allAction')->bind('payment_all');

        $app->get('/gateways/meta', 'payum.api.controller.gateway_meta:getAllAction')->bind('payment_factory_get_all');
        $app->get('/gateways', 'payum.api.controller.gateway:allAction')->bind('gateway_all');
        $app->get('/gateways/{name}', 'payum.api.controller.gateway:getAction')->bind('gateway_get');
        $app->delete('/gateways/{name}', 'payum.api.controller.gateway:deleteAction')->bind('gateway_delete');
        $app->post('/gateways', 'payum.api.controller.gateway:createAction')->bind('gateway_create');

        $app->before(function (Request $request, Application $app) {
            if (in_array($request->getMethod(), array('GET', 'OPTIONS', 'DELETE'))) {
                return;
            }

            if ('json' !== $request->getContentType()) {
                throw new BadRequestHttpException('The request content type is invalid. It must be application/json');
            }

            $decodedContent = json_decode($request->getContent(), true);
            if (null ===  $decodedContent) {
                throw new BadRequestHttpException('The request content is not valid json.');
            }

            $request->attributes->set('content', $decodedContent);
        });

        $app->after(function (Request $request, Response $response) use ($app) {
            if($response instanceof JsonResponse && $app['debug']) {
                $response->setEncodingOptions($response->getEncodingOptions() | JSON_PRETTY_PRINT);
            }
        });

        $app->after($app["cors"]);

        $app->error(function (\Exception $e, $code) use ($app) {
            if ('json' !== $app['request']->getContentType()) {
                return;
            }

            return new JsonResponse(array(
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stackTrace' => $e->getTraceAsString(),
            ));
        }, $priority = -100);
    }

    /**
     * {@inheritDoc}
     */
    public function boot(SilexApplication $app)
    {
    }
}