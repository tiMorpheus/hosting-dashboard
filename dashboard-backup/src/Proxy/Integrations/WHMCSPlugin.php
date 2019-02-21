<?php

namespace Proxy\Integrations;

use Proxy\Integration;

class WHMCSPlugin extends Integration
{
    public function changeBillingCycle($userId, $serviceId, $billingCycle)
    {
        return $this->getRequestHandler()->doRequest('changeBillingCycle', [
            'userId' => $userId,
            'serviceId' => $serviceId,
            'billingCycle' => $billingCycle
        ]);
    }

    public function updatePackage(
        $userId,
        $whmcsProductId,
        $quantity,
        $successUrl = '',
        $failUrl = '',
        $callbackUrl = '',
        $affiliateId = null,
        $promocode = null,
        $customOptions = []
    ) {
        return $this->getRequestHandler()->doRequest('updatePackage', [
            'userId'      => $userId,
            'productId'   => $whmcsProductId,
            'quantity'    => $quantity,
            'url'         => [
                'redirect' => [
                    'success' => $successUrl,
                    'fail'    => $failUrl
                ],
                'callback' => $callbackUrl
            ],
            'affiliateId' => $affiliateId,
            'promocode'   => $promocode,
            'customOptions' => $customOptions
        ]);
    }

    public function cancelPackage($userId, $whmcsProductId)
    {
        return $this->getRequestHandler()->doRequest('cancelPackage', [
            'userId'    => $userId,
            'productId' => $whmcsProductId
        ]);
    }

    public function cancelPackageAtTheEndOfTheBilling($userId, $serviceId, $reason) {
        return $this->getRequestHandler()->doRequest('cancelPackageAtTheEndOfTheBilling', [
            'userId'    => $userId,
            'serviceId' => $serviceId,
            'reason' => $reason
        ]);
    }

    public function removeCancellationRequest($userId, $serviceId) {
        return $this->getRequestHandler()->doRequest('removeCancellationRequest', [
            'userId'    => $userId,
            'serviceId' => $serviceId
        ]);
    }

    public function hasCancelRequest($userId, $whmcsProductId) {
        return $this->getRequestHandler()->doRequest('hasCancelRequest', [
            'userId'    => $userId,
            'productId' => $whmcsProductId
        ]);
    }

    public function getPricingTiers($showHidden = false)
    {
        static $response;

        if (!$response) {
            $response = $this->getRequestHandler()->doRequest('getPricingTiers',
                array_merge($showHidden ? ['withUnpublished' => $showHidden] : []));

            if (empty($response[ 'pricing' ])) {
                return $response;
            }

            // Migration for definitions
            foreach ($response[ 'pricing' ] as $i => $product) {
                if ('rotate' == $product[ 'meta' ][ 'category' ]) {
                    $product[ 'meta' ][ 'category' ] = 'rotating';
                }

                $response[ 'pricing' ][ $i ] = $product;
            }
        }

        return $response;
    }

    public function getUserProducts($userId)
    {
        return $this->getRequestHandler()->doRequest('getUserProducts', ['userId' => $userId]);
    }




    public function getAffiliateIdCallback($callbackUrl)
    {
        return $this->getRequestHandler()->doCallbackRequest($callbackUrl, 'getAffiliate');
    }

    public function getUserWhmcsIdCallback($callbackUrl)
    {
        return $this->getRequestHandler()->doCallbackRequest($callbackUrl, 'getUserId');
    }

    public function calculateTotal($productId, $quantity, $userId = null, $promocode = null, $billingCycle)
    {
        return $this->getRequestHandler()->doRequest('calculateTotal', [
            'productId' => $productId,
            'quantity'  => $quantity,
            'userId'    => $userId,
            'promocode' => $promocode,
            'billingCycle' => $billingCycle
        ]);
    }

    public function getInvoices($userId)
    {
        return $this->getRequestHandler()->doRequest('getInvoices', ['userId' => $userId]);
    }

    /**
     * @return WHMCSPluginRequestHandler
     */
    protected function getRequestHandler()
    {
        return $this->app[ 'integration.whmcs.plugin.request_handler' ];
    }
}
