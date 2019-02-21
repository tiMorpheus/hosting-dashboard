<?php

namespace Proxy\Controller;

use Buzz\Browser;
use Proxy\Util\Util;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiController extends AbstractController
{
    public function proxyIPv4ListAction($email, $key, $country = false, $category = false)
    {
        $response = new Response('', 200, [ 'content-type'  => 'text/plain' ]);
        $response
            ->setMaxAge(60 * 60)
            ->setLastModified(new \DateTime(date('Y-m-d H:00:00')))
            ->setEtag(sha1(date('Y-m-d H:00:00')));
        $response->sendHeaders();

        try {
            $user = $this->getApi()->userManagement()->findUserByLoginOrEmail($email);
            if ($key != $user['apiKey']) {
                throw new \ErrorException('API key is invalid');
            }
        }
        catch (\ErrorException $e) {
            throw new BadRequestHttpException("Email or API key is wrong");
        }

        // Ok, user is valid one
        $proxies = $this->getApi()->ports4()->getAll(
            array_merge([], !$country ? [] : [$country]),
            array_merge([], !$category ? [] : [$category]),
            ['country' => 'asc', 'category' => 'asc', 'updated' => 'desc', 'rotated' => 'desc', 'ip' => 'asc'],
            false, false,
            $user['userId']
        )['list'];

        if ('PW' == $user['authType']) {
            $ipPostfix = ":" . $this->app['config.port.pwd'] . ":" .
                Util::toProxyLogin($user) . ":" . $user['apiKey'];
        } else {
            if($user['ipAuthType'] === 'SOCKS')
                $ipPostfix = ":" . $this->app['config.port.ip_socks'];
            else
                $ipPostfix = ":" . $this->app['config.port.ip'];
        }

        $data = [];
        foreach ($proxies as $row) {
            if (in_array($row['category'], ['rotating', 'google'])) {
                $data[] = $row['serverIp'] . ":" . $row['port'];
            } else {
                $data[] = $row['ip'] . $ipPostfix;
            }
        }

        $response->setContent(join("\n", $data));

        return $response;
    }

    public function proxyIPv6ListAction($email, $key)
    {
        try {
            $user = $this->getApi()->userManagement()->findUserByLoginOrEmail($email);
            if ($key != $user['apiKey']) {
                throw new \ErrorException('API key is invalid');
            }
        }
        catch (\ErrorException $e) {
            throw new BadRequestHttpException("Email or API key is wrong");
        }

        // Ok, user is valid one
        $proxies = $this->getApi()->ports6()->getAll()['list'];

        $response = [];
        foreach ($proxies as $row) {
            $response[] = join(':', [$row['serverIp'], $row['serverPort'], $row['login'], $user['apiKey']]);
        }

        return new Response(
            join("\n", $response),
            200,
            [
                'cache-control' => 'no-cache, must-revalidate', // HTTP/1.1
                'expires'       => 'Sat, 26 Jul 1997 05:00:00 GMT', // Date in the past
                'content-type'  => 'text/plain'
            ]
        );
    }

    protected function proxyRequest(Request $request, $url)
    {
        try {
            $browser = new Browser();
            $result = $browser->submit($url, $request->query->all(), 'get');
            $status = 500;
            if (!empty($result->getHeaders()[0]) and preg_match('~^HTTP[^\s]+\s(\d+)~i', $result->getHeaders()[0], $match)) {
                $status = (int) $match[1];
            }
            $headers = [];
            foreach ($result->getHeaders() as $header) {
                if (false !== strpos($header, ':')) {
                    list ($key, $value) = explode(':', $header);
                    $headers[$key] = $value;
                }
            }
            return new Response($result->getContent(), $status, $headers);
        }
        catch (\Exception $e) {
            return new Response($e->getMessage(), 500);
        }
    }

    public function total(Request $request) {
        $headers = [
            'Access-Control-Allow-Origin' => $this->app['config.blazing_main_site.url'],
            'Access-Control-Allow-Credentials' => 'true'
        ];

        if ($request->getMethod() == 'OPTIONS') {
            return new Response('', 200, $headers);
        }

        $planUnformed = $request->get('plan');

        $total = $this->app['app.checkout_controller']->getTotal(
            $planUnformed['country'],
            $planUnformed['category'],
            $planUnformed['amount'],
            $planUnformed['billingCycle']
        );

        $response = [
            'total' => $total['total'],
            'discount'   => $total['discount']
        ];

        return new JsonResponse($response, 200, $headers);
    }
}
