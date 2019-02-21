<?php

namespace Proxy\Util;

use Blazing\Reseller\Api\Api;
use Silex\Application;

class Helper
{
    protected $app;

    public function __construct(Application $app = null)
    {
        $this->app = $app;
    }

    public function getUserPorts($country, $category, $limit, $offset = 0)
    {
        $details = $this->app['session.user']->getDetails();

        /** @var Api $api */
        $api = $this->app['api'];
        $result = $api->ports4()->getAll($country, $category,
            ['country' => 'asc', 'category' => 'asc', 'updated' => 'desc', 'rotated' => 'desc', 'ip' => 'asc'], $limit, $offset);
        $ports = &$result['list'];

        foreach($ports as $index => $port) {
            if($port['category'] === 'rotating') {
                $ports[$index]['connectionIp'] = $port['serverIp'];
            } elseif($port['ip']) {
                $ports[$index]['connectionIp'] = $port['ip'];
            } else {
                $ports[$index]['connectionIp'] = '';
            }

            if($port['category'] === 'rotating') {
                $ports[$index]['connectionPort'] = $port['port'];
            } elseif($details[ 'authType' ] === 'PW') {
                $ports[$index]['connectionPort'] = $this->app['config.port.pwd'];
            } else {
                if($details['ipAuthType'] === 'SOCKS')
                    $ports[$index]['connectionPort'] = $this->app['config.port.ip_socks'];
                else {
                    $ports[$index]['connectionPort'] = $this->app['config.port.ip'];
                }
            }

            if($port['category'] === 'rotating') {
                $ports[$index]['rotationInfo'] = $port['rotationTime'];
            } elseif ($port['category'] == 'mapple') {
                $ports[$index]['rotationInfo'] = 'Never';
            } elseif ($details['rotate_30']) {
                $ports[$index]['rotationInfo'] = '30 Days';
            } else {
                $ports[$index]['rotationInfo'] = 'Never';
            }

            $ports[$index]['humanizedProxyName'] = $this->app['util.formatter']->humanizeProxyName($port); //'test';
        }

        return $result;
    }

    public function sendEmail($to, $subject, $text, $from = 'support@blazingseollc.com', $method = 'auto', $replyTo = null)
    {
        $from OR $from = 'support@blazingseollc.com';
        $userInBilling = false;
        $sent = false;

        if($method === 'auto' || $method === 'whmcs') {
            try {
                $userDetails = $this->app[ 'integration.whmcs.api' ]->getClientDetailsByEmail($to);
                if (!empty($userDetails[ 'userid' ])) {
                    $userInBilling = $userDetails[ 'userid' ];
                }
                //print_r($userDetails);
                //exit();
                if ($userInBilling) {
                    $result = $this->app[ 'integration.whmcs.api' ]->api('SendEmail', [
                        'id'            => $userInBilling,
                        'customsubject' => $subject,
                        'custommessage' => $text,

                        // Dummy type
                        'customtype' => 'general',
                    ]);


                    $sent = (!empty($result[ 'result' ]) and 'success' == $result[ 'result' ]);
                }
            }
            catch (ErrorException $e) {
                if (!empty($this->app['logs'])) {
                    $this->app['logs']->warn('Sending email, exception: ' . $e->getMessage(), ['exception' => $e]);
                }
            }
        }

        if (!$sent && $method !== 'whmcs') {
            if (!empty($this->app['logs'])) {
                $this->app['logs']->notice('Sending email, using the default mailer for ' . $to);
            }

            $text = strip_tags($text);
            $sent = mail($to, $subject, $text, join("\r\n", [
                "From: $from",
                "Reply-To: " . ($replyTo ? $replyTo : $from),
            ]));
        }

        if (!empty($this->app['logs'])) {
            $this->app['logs']->debug("Email \"$subject\" has " . ($sent ? '' : 'not ') . 'been sent', [
                'email'   => $to,
                'subject' => $subject,
                'text'    => $text,
                'from'    => $from
            ]);
        }

        return $sent;
    }
}
