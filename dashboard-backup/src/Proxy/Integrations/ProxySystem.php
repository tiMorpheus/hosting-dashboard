<?php

namespace Proxy\Integrations;

use Buzz\Browser;
use Buzz\Client\Curl;
use Proxy\Integration;
use Silex\Application;

class ProxySystem extends Integration
{

    public function portsSync($userId, $country, $category)
    {
        return $this->doRequest('ports-sync', [
            'userId'    => $userId,
            'country' => $country,
            'category' => $category,
            'userType' => 'BL'
        ]);
    }

    public function getBlocks($clean = false)
    {
        return $this->doRequest('blocks', [
            'clean' => $clean
        ]);
    }

    protected function doRequest($method, array $args = [])
    {
        $url = $this->app['config.proxyUrl'] . '/api/proxy/';

        $client = new Curl();
        $client->setVerifyHost(false);
        $client->setVerifyPeer(false);
        $client->setTimeout(30);

        $response = (new Browser($client))->submit($url . $method, array_merge($args));

        try {
            $data = json_decode($response->getContent(), true);

            if (is_null($data)) {
                throw new \Exception('Response error');
            }

            return $data;
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'message' => $response ? $response->getContent() : $e->getMessage()
            ];
        }
    }
}
