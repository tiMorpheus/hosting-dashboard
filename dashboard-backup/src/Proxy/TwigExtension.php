<?php

namespace Proxy;

use Axelarge\ArrayTools\Arr;
use Proxy\Util\Util;
use Silex\Application;
use Symfony\Component\Routing\Generator\UrlGenerator;

class TwigExtension extends \Twig_Extension
{
    /**
     * @var Application
     */
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('humanizeProxyName', [$this->app['util.formatter'], 'humanizeProxyName']),
            new \Twig_SimpleFilter('shareVars', function($vars) {
                $return = '';

                foreach ($vars as $key => $data) {
                    // Key must be non numeric
                    if (!ctype_digit($key)) {
                        $return .= "<div id=\"$key\">" . json_encode($data) . '</div>';
                    }
                }

                return trim ($return) ?
                    '<div class="hidden" style="display: none">' . $return . '</div>' :
                    '';
            }, ['is_safe' => ['html']]),
            new \Twig_SimpleFilter('unique', function(array $array) { return array_unique($array); }),
            new \Twig_SimpleFilter('pluck', function(array $array, $value, $key = null) { return Arr::pluck($array, $value, $key); }),
        ];
    }

    public function getFunctions()
    {
        $fn = [
            'asset_path' => function($file, $extendedInfo = false) {
                $rootDir = realpath(__DIR__ . "/../../web");
                $assetsDir = "$rootDir/assets";

                $filePath = ltrim($file, '/');

                $found = false;
                foreach ([
                    "$assetsDir/theme/{$this->app['config.view.theme']}/$filePath",
                    "$assetsDir/theme/default/$filePath",
                    "$assetsDir/$filePath",
                ] as $path) {
                    if (is_file($path)) {
                        $found = $path;
                        break;
                    }
                }

                // If file not found, just return it as it passed
                if (!$extendedInfo) {
                    return !$found ? $file : realpath($found);
                }

                if ($found) {
                    $found = realpath($found);
                }

                return [
                    'exist' => !!$found,
                    'rootDir' => $rootDir,
                    'filePath' => $found,
                    'filePathRelative' => $found ?
                        str_replace(str_replace('\\', '/', $rootDir) . '/', '', str_replace('\\', '/', $found)) :
                        false
                ];
            },
            'generate_uid' => function($length = 8) {
                return 'uid' . substr(strrev(uniqid()), 0, $length);
            }
        ];

        $announcement = $this->app['app.announcement'];

        $container = [
            new \Twig_SimpleFunction('asset', function($file) use ($fn) {
                $info = $fn['asset_path']($file, true);

                // If file not found, just return it as it passed
                if (!$info['exist']) {
                    return $file;
                }

                $date = filemtime($info['filePath']);

                // Unknown error, don't handle it
                if (!$date) {
                    return $file;
                }

                return $this->app['url_generator']->generate('root') . $info['filePathRelative'] . '?v=' . md5($date);
            }),
            new \Twig_SimpleFunction('url_remote', function($route, array $parameters = []) {
                return ltrim($this->app['url_generator']->generate($route, $parameters, UrlGenerator::RELATIVE_PATH), './');
            }),
            new \Twig_SimpleFunction('announcement_tmpldata', function() use ($announcement) {
                if ($announcement->prepare() && (($class = $announcement->getClass()) !== null)) {
                    return $class->getTemplateData();
                }

                return [];
            }),
        ];

        foreach ($fn as $key => $cb) {
            $container[] = new \Twig_SimpleFunction($key, $cb);
        }

        return $container;
    }
}