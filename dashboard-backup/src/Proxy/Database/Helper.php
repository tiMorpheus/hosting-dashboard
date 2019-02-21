<?php

namespace Proxy\Database;

use Axelarge\ArrayTools\Arr;
use Blazing\Common\RestApiRequestHandler\Exception\BadRequestException;
use Blazing\Reseller\Api\Api;
use Blazing\Reseller\Api\Api\Entity\PackageEntity;
use ErrorException;
use Proxy\User;
use Silex\Application;

/**
 * Helper Class to interact with the DB and WHMCS
 *
 * @author mtwhe
 */
class Helper {

    protected $lastSyncResults = [];

    protected $statusMap = [
        // whmcs => api
        'active'    => PackageEntity::STATUS_ACTIVE,
        'suspended' => PackageEntity::STATUS_SUSPENDED
    ];

    public function __construct(Application $app) {
        $this->app = $app;
    }

    public function refreshUserPlans($userId = null)
    {
        /** @var User $user */
        $user = $this->app['session.user'];
        /** @var Api $api */
        $api = $this->app['api'];
        $return = [];

        if (!$userId) {
            $userId = $user->getDetails('id');
        }
        $userWhmcsId = $api->user()->getDetails($userId)['whmcsId'];

        $this->lastSyncResults = [
            'updated' => ['count' => 0, 'sets' => []],
            'added' => ['count' => 0, 'sets' => []],
            'deleted' => ['count' => 0, 'sets' => []],
            'unknown' => ['count' => 0, 'sets' => []],
            'duplicated' => ['count' => 0, 'sets' => []],
        ];

        // Extract WHMCS packages
        $whmcsProducts = $this->getWHMCSProducts();
        $products = $this->app[ 'integration.whmcs.plugin' ]->getUserProducts($userWhmcsId);
        if (empty($products[ 'userId' ])) {
            // Error
            throw new ErrorException('Unable to refresh products, response - ' . json_encode($products));
        }
        $billingPlans = [];
        $loopableProducts = $products[ 'products' ];
        foreach($loopableProducts as $product) {
            if (!in_array($product['status'], ['active', 'suspended'])) {
                continue;
            }

            // todo: product can be removed from whmcs side - handle such behaviour
            $product += $whmcsProducts[ $product[ 'productId' ] ];

            if (4 == $product['ipVersion']) {
                $found = false;
                foreach ($billingPlans as $i => $billingPlan) {
                    if ($billingPlan['country'] == $product['country'] and $billingPlan['category'] == $product['category']) {
                        $found = $billingPlan;
                        $billingPlans[$i]['quantity'] += $product['quantity'];
                    }
                }
                if (!$found) {
                    $billingPlans[] = $product;
                }
            }
            else {
                $billingPlans[] = $product;
            }
        }

        // Extract DB packages
        $data = $api->packages()->getAll(false, false, false, $userId);
        $dbPlans = $data['list'];

        // The user has no packages, update everything and bail
        if (!$billingPlans) {
            $response = $api->packages()->deleteAll('whmcs', false, $userId);

            $this->lastSyncResults[ 'deleted' ][ 'count' ] = $response[ 'count' ];
            foreach ($dbPlans as $billingPlan) {
                $this->lastSyncResults[ 'deleted' ][ 'sets' ][] = $billingPlan;
            }

            return [];
        }

        // Sync everything

        $compareSchema = [
            'ipVersion' => 'l',
            'type'      => 'l',
            'country'   => 'l',
            'category'  => 'l',
            'ext'       => 'jl',
        ];
        foreach($billingPlans as $billingPlan) {
            $return[] = $billingPlan;

            $dbIndex = null;
            foreach ($dbPlans as $i => $dbPlan) {
                foreach ($compareSchema as $key => $type) {
                    if ('l' == $type) {
                        if (!empty($billingPlan[$key]) and $billingPlan[$key] != $dbPlan[$key]) {
                            continue 2;
                        }
                    }
                    elseif ('jl' == $type) {
                        if($key === 'ext' && $billingPlan['category'] === 'block')
                            continue;

                        if (!empty($billingPlan[$key]) and array_diff($billingPlan[$key], $dbPlan[$key])) {
                            continue 2;
                        }
                    }
                }

                $dbIndex = $i;
                break;
            }

            // Insert

            if (is_null($dbIndex)) {
                $api->packages()->add(PackageEntity::construct()
                    ->setIpVersion($billingPlan[ 'ipVersion' ])
                    ->setType($billingPlan[ 'type' ])
                    ->setCountry($billingPlan[ 'country' ])
                    ->setCategory($billingPlan[ 'category' ])
                    ->setExt(Api\Entity\PackageExtEntity::construct()->setData($billingPlan[ 'ext' ] ? $billingPlan[ 'ext' ] : []))
                    ->setPorts($billingPlan[ 'quantity' ])
                    ->setStatus(Arr::getOrElse($this->statusMap, $billingPlan['status'], PackageEntity::STATUS_ACTIVE)),
                    $userId);

                $this->lastSyncResults[ 'added' ][ 'count' ]++;
                $this->lastSyncResults[ 'added' ][ 'sets' ][] = $billingPlan;
            }

            // Update
            elseif (
                // Quantity
            ($billingPlan[ 'quantity' ] != $dbPlans[ $dbIndex ][ 'ports' ]) or
                // Status
            $dbPlans[ $dbIndex ][ 'status' ] !=
                Arr::getOrElse($this->statusMap, $billingPlan['status'], PackageEntity::STATUS_ACTIVE) or
                // Ext
            $dbPlans[ $dbIndex ][ 'ext' ] != $billingPlan['ext']
            ) {
                if ('whmcs' == $dbPlans[ $dbIndex ][ 'source' ]) {
                    $api->packages()->updateById($dbPlans[$dbIndex]['id'], PackageEntity::construct()
                        ->setPorts($billingPlan[ 'quantity' ])
                        ->setExt(Api\Entity\PackageExtEntity::construct()->setData($billingPlan[ 'ext' ] ? $billingPlan[ 'ext' ] : []))
                        ->setStatus(Arr::getOrElse($this->statusMap, $billingPlan['status'], PackageEntity::STATUS_ACTIVE)),
                        $userId);
                    $this->lastSyncResults[ 'updated' ][ 'count' ]++;
                    $this->lastSyncResults[ 'updated' ][ 'sets' ][] = $billingPlan;
                }
                else {
                    $this->lastSyncResults[ 'duplicated' ][ 'count' ]++;
                    $this->lastSyncResults[ 'duplicated' ][ 'sets' ][] = $billingPlan;
                }
            }


            // Mark as processed
            if (!is_null($dbIndex)) {
                unset($dbPlans[ $dbIndex ]);
            }
        }

        // Delete
        foreach($dbPlans as $plan) {
            // Skip other sources
            if ('whmcs' != $plan['source']) {
                continue;
            }

            try {
                $api->packages()->deleteById($plan['id'], $userId);
                $this->lastSyncResults[ 'deleted' ][ 'count' ]++;
                $this->lastSyncResults[ 'deleted' ][ 'sets' ][] = $plan;
            }
            catch (BadRequestException $e) {}
        }
        return $return;
    }

    public function getLastRefreshDetails()
    {
        return $this->lastSyncResults;
    }

    public function getWHMCSProducts() {
        $whmcsProducts = [];

        $response = $this->app[ 'integration.whmcs.plugin' ]->getPricingTiers(true);

        if (empty($response[ 'pricing' ])) {
            throw new ErrorException('No pricing tiers received, response - ' . json_encode($response));
        }

        $pricingTiers = $response[ 'pricing' ];

        foreach ($pricingTiers as $product) {
            $whmcsProducts[ $product[ 'productId' ] ] = [
                'ipVersion' => $product[ 'meta' ][ 'ipVersion' ],
                'type'      => $product[ 'meta' ][ 'type' ],
                'country'   => $product[ 'meta' ][ 'country' ],
                'category'  => $product[ 'meta' ][ 'category' ],
                'ext'       => $product[ 'meta' ][ 'ext' ],
            ];
        }

        return $whmcsProducts;
    }
}
