<?php

namespace Proxy;

use Axelarge\ArrayTools\Arr;
use Blazing\Logger\Logger;
use Buzz\Browser;
use Buzz\Client\Curl;
use Buzz\Exception\InvalidArgumentException;
use Buzz\Exception\RequestException;
use ErrorException;
use Silex\Application;

class Integration
{
    protected $app;

    protected $connect = [
        'timeout'      => 15,
        'retry'        => 10,
        'verifyHost'   => false,
        'verifyPeer'   => false,
        'maxRedirects' => 5,
        'waitOnFail'   => 1
    ];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function fromJsonString($string)
    {
        $data = @json_decode($string, true);

        return !is_null($data) ? $data : ['result' => 'error', 'error' => true, 'message' => $string];
    }

    protected function doCurl($url, array $args = [], $method = 'POST', array $headers = []) {
        $client = new Curl();
        $client->setVerifyHost(false);
        $client->setVerifyPeer(false);
        $client->setTimeout($this->connect['timeout']);

        $response = (new Browser($client))->submit($url, $args, $method, $headers);

        if (!empty($this->app['logs'])) {
            $this->app['logs']->debug(get_class($this) . " \"$method\" response", [
                'parameters' => Arr::except($args, ['password']),
                'response' => $response->getContent(),
                'url' => $url
            ]);
        }

        return $response->getContent();
    }

    protected function doCurlJson($url, array $args = [], $method = 'POST', array $headers = [])
    {
        $client = new Curl();
        $browser = new Browser($client);
        $browser->getClient()->setTimeout($this->connect['timeout']);
        $browser->getClient()->setVerifyHost($this->connect['verifyHost']);
        $browser->getClient()->setVerifyPeer($this->connect['verifyPeer']);
        $browser->getClient()->setMaxRedirects($this->connect['maxRedirects']);
        $tries = $this->connect['retry'];

        $handlerClass = str_replace('RequestHandler', '', preg_replace('~^.*?([a-zA-Z0-9_]+)$~', '$1', get_class($this)));
        $urlPath = $this->getRequestPath($url, $args);

        if (!empty($this->app['logs'])) {
            $logger = $this->app['logs'];
            if ($logger instanceof Logger) {
                $args += $logger->prepareMasterRequestParameter();
            }
        }

        try {
            while (true) {
                try {
                    /** @var \Buzz\Message\Response $response */
                    $response = $browser->submit($url, $args, $method, $headers);
                    $content = $response->getContent();

                    if (200 !== $response->getStatusCode() or '{' != substr($content, 0, 1)) {
                        throw new RequestException(sprintf('Error %s: %s', $response->getStatusCode(), substr($content, 0, 5000)));
                    }

                    break;
                }
                catch (RequestException $e) {
                    $tries--;

                    // Stop trying
                    if ($tries <= 0) {
                        // Rethrow an exception
                        throw $e;
                    }

                    if (!empty($this->app['logs'])) {
                        $this->app['logs']->warn("$handlerClass API: Request exception $method:$urlPath", [
                            'error'      => $e->getMessage(),
                            'triesLeft'  => $tries,
                            'parameters' => $args,
                            'response'   => !empty($response) ? substr($response->getContent(), 0, 5000) : null,
                            'url'        => $url,
                        ]);
                    }

                    sleep($this->connect['waitOnFail']);
                }
            }

            if (empty($content)) {
                throw new ErrorException('Empty content in response');
            }

            $data = @json_decode($content, true);
            if (!$data) {
                throw new ErrorException('No data in response, content: ' . $content);
            }

            $this->checkJsonResponse($data);
        }
        catch (\Exception $e) {
            if (!empty($this->app['logs'])) {
                $this->app['logs']->err("$handlerClass API: Request error $method:$urlPath", [
                    'error'      => $e->getMessage(),
                    'triesLeft'  => $tries,
                    'parameters' => $args,
                    'response'   => !empty($response) ? substr($response->getContent(), 0, 5000) : null,
                    'url'        => $url
                ]);
            }

            $class = RequestException::class;
            if (!empty($data) and !empty($data['alert'])) {
                $class = InvalidArgumentException::class;
            }

            throw new $class("$handlerClass $method:$urlPath request error: " . $e->getMessage());
        }

        if (!empty($this->app['logs'])) {
            $this->app['logs']->debug("$handlerClass API: Response $method:$urlPath", [
                'parameters' => $args,
                'response'   => $data,
                'url'        => $url
            ]);
        }

        return $data;
    }

    protected function getRequestPath($url, array $args = [])
    {
        return parse_url($url, PHP_URL_PATH);
    }

    /**
     * @param array $data
     * @throws \Exception
     * @return void
     */
    protected function checkJsonResponse(array $data)
    {

    }
}