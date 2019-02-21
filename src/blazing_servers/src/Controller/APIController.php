<?php

namespace WHMCS\Module\Blazing\Servers\Controller;

use http\Env\Response;
use WHMCS\Module\Blazing\Servers\Events\TrialPricingOverrode;
use WHMCS\Module\Blazing\Servers\Vendor\Axelarge\ArrayTools\Arr;
use WHMCS\Module\Blazing\Servers\Vendor\Symfony\Component\HttpFoundation\Request;
use WHMCS\Module\Blazing\Servers\Logger;
use WHMCS\Module\Blazing\Servers\Vendor\WHMCS\Module\Framework\Addon;
use WHMCS\Module\Blazing\Servers\Vendor\WHMCS\Module\Framework\Helper;

class APIController extends AbstractSelfDrivenController
{
    public function getClientProductsAction($clientid, $limitstart = 0, $limitnum = 50)
    {
        require_once ROOT_DIR . '/../../../includes/customfieldfunctions.php';
        require_once ROOT_DIR . '/../../../includes/configoptionsfunctions.php';

        $limitstart = (int) $limitstart;
        $limitnum = (int) $limitnum;

        $res = Helper::conn()->select("
            SELECT
              tblhosting.*, tblproducts.name AS productname, tblproductgroups.name AS groupname,
              (SELECT CONCAT(name,'|',ipaddress,'|',hostname) FROM tblservers WHERE tblservers.id=tblhosting.server) AS serverdetails,
              (
                SELECT tblpaymentgateways.value FROM tblpaymentgateways
                WHERE tblpaymentgateways.gateway=tblhosting.paymentmethod
                  AND tblpaymentgateways.setting='name' LIMIT 1
              ) AS paymentmethodname
            FROM tblhosting
            INNER JOIN tblproducts ON tblproducts.id=tblhosting.packageid AND tblproducts.servertype IN ('virtualizor', 'EasyDCIM')
            INNER JOIN tblproductgroups ON tblproductgroups.id=tblproducts.gid
            WHERE tblhosting.userid = ?
            ORDER BY tblhosting.id DESC
            LIMIT $limitnum OFFSET $limitstart", [$clientid]);

        $response = [
            'result' => 'success',
            'products' => [
                'product' => []
            ]
        ];

        foreach ($res as $one) {
            $serverdetails = $one['serverdetails'];
            $serverdetails = explode("|", $serverdetails);
            unset($one['serverdetails']);

            $one['pid'] = $one['packageid'];
            unset($one['packageid']);

            $one['servername'] = $serverdetails[0];
            $one['serverip'] = $serverdetails[1];
            $one['serverhostname'] = $serverdetails[2];

            /** @noinspection PhpUndefinedFunctionInspection */
            $customfields = getCustomFields("product", $one['pid'], $one['id'], "on", "");
            foreach ($customfields as $customfield) {
                $customfieldsdata[] = ["id" => $customfield['id'], "name" => $customfield['name'], "value" => $customfield['value']];
            }

            $configoptionsdata = [];
            /** @noinspection PhpUndefinedFunctionInspection */
            $configoptions = getCartConfigOptions($one['pid'], "", $one['billingcycle'], $one['id']);
            foreach ($configoptions as $configoption) {
                switch ($configoption['optiontype']) {
                    case 1:
                        {
                            $type = "dropdown";
                            break;
                        }

                    case 2:
                        {
                            $type = "radio";
                            break;
                        }

                    case 3:
                        {
                            $type = "yesno";
                            break;
                        }

                    case 4:
                        {
                            $type = "quantity";
                        }
                }


                if ($configoption['optiontype'] == "3" || $configoption['optiontype'] == "4") {
                    $configoptionsdata[] = ["id" => $configoption['id'], "option" => $configoption['optionname'], "type" => $type, "value" => $configoption['selectedqty']];
                    continue;
                }

                $configoptionsdata[] = ["id" => $configoption['id'], "option" => $configoption['optionname'], "type" => $type, "value" => $configoption['selectedoption']];
            }

            $one["customfields"] = ["customfield" => $customfieldsdata];
            $one["configoptions"] = ["configoption" => $configoptionsdata];
            $response['products']['product'][] = $one;
        }

        $response['products']['product'] = array_column($response['products']['product'], null, 'id');

        /** @noinspection PhpUndefinedFunctionInspection */
        $result = run_hook('GetTrialServices', [
            'userId' => $clientid
        ]);

        $trialServices = [];
        if (!isset($result[0])) {
            Logger::notice('Trials plugin is not reachable getClientProductsAction');
        } else {
            $trialServices = array_column($result[0], null, 'id');
        }

        foreach ($trialServices as $trialService) {
            if (isset($response['products']['product'][$trialService['id']])) {
                $response['products']['product'][$trialService['id']]['trialStatus'] = $trialService->status;
            }
        }

        $response['products']['product'] = array_values($response['products']['product']);

        return $response;
    }

    public function addOrderAction(Request $request)
    {
        if ($request->getMethod() !== 'POST') {
            throw new \ErrorException('Method not allowed: ' . $request->getMethod() . '. Allowed method \'POST\'');
        }

        $params = [
            'clientid' => $request->get('clientid'),
            'pid' => $request->get('pid'),
            'billingcycle' => $request->get('billingcycle'),
            'paymentmethod' => $request->get('paymentmethod'),
            'hostname' => $request->get('hostname'),
            'rootpw' => $request->get('rootpw'),
            'promocode' => $request->get('promocode', false)
        ];

        if($request->get('configoptions')) {
            $params['configoptions'] = $request->get('configoptions');
        }

        $customOptions = [
            'trial' => $request->get('trial', false)
        ];

        logActivity((int)$customOptions['trial']);

        /** @noinspection PhpUndefinedFunctionInspection */
        $mergeParams = run_hook('BlazingServersBeforeOrderCreated', [
            'customOptions' => $customOptions,
            'billingCycle'  => $params['billingcycle'],
            'productId'     => $params['pid'],
            'userId'        => $params['clientid']
        ]);

        if(!is_array($mergeParams)) {
            $mergeParams = [[]];
        }

        TrialPricingOverrode::trackOverride();

        $response = Helper::apiResponse(
            'addOrder',
            array_merge($params, ...$mergeParams),
            'result=success');
        
        /** @noinspection PhpUndefinedFunctionInspection */
        run_hook('BlazingServersOrderCreated', [
            'customOptions' => $customOptions,
            'productId'     => $params['pid'],
            'userId'        => $params['clientid'],
            'serviceId'     => (int) $response['productids']
        ]);

        if (TrialPricingOverrode::isOverrode()) {
            $response['trial'] = true;
        } else {
            $response['trial'] = false;
            $response['pendingTrial'] = TrialPricingOverrode::isPendingTrial();
            if ($customOptions['trial']) {
                $response['trialNotAllowedReason'] = TrialPricingOverrode::getNotAllowedReason();
            }
        }

        return $response;
    }


    public function getVpsProductsAction($losAngelesGID, $newYorkNewJerseyGID , $dallasGID, $chicagoGID){


        $losAngeles = $this->getProductsAction(0,$losAngelesGID)['products']['product'];
        $nynj = $this->getProductsAction(0,$newYorkNewJerseyGID)['products']['product'];
        $dallas = $this->getProductsAction(0,$dallasGID)['products']['product'];
        $chicago = $this->getProductsAction(0,$chicagoGID)['products']['product'];

        $losAngeles = $this->getFilteredVpsLocation($losAngeles);
        $nynj = $this->getFilteredVpsLocation($nynj);
        $dallas = $this->getFilteredVpsLocation($dallas);
        $chicago = $this->getFilteredVpsLocation($chicago);



        return ['success' => true,
            'losAngeles' => $losAngeles,
            'nynj' => $nynj,
            'dallas' => $dallas,
            'chicago' => $chicago];
    }
    private function getFilteredVpsLocation($location){

        $result = [];
        foreach ($location as $los){

            $result[] = array_filter($los, function($k) {
                return $k == 'id' || $k == 'name';
            }, ARRAY_FILTER_USE_KEY);



        }
        return $result;
    }


    public function getProductsAction($pid = null, $gid = null, $clientid = null, $showhidden = false)
    {
        require_once ROOT_DIR . '/../../../includes/configoptionsfunctions.php';
        require_once ROOT_DIR . '/../../../includes/customfieldfunctions.php';

        $where = $showhidden ? '' : 'p.hidden = 0';
        $params = [];
        if ($gid) {
            $where .= ' AND p.gid = ?';
            $params[] = $gid;
        }

        if ($pid) {
            if (!is_array($pid)) {
                $pid = [$pid];
            }

            $where .= ' AND p.id IN (' . implode(',', $pid) . ')';
        }

//        $products = Helper::conn()->select("
//            SELECT *  FROM tblproducts p
//            WHERE $where
//        ", $params);


        $products = Helper::conn()->select("
            SELECT p.*, tp.msetupfee, tp.monthly, tp.quarterly, tp.semiannually, tp.annually, tp.biennially, tp.triennially FROM tblproducts p
            JOIN tblpricing tp ON p.id = tp.relid
            WHERE tp.type ='product' AND $where
        ", $params);

        /** @noinspection PhpUndefinedFunctionInspection */
        $result = run_hook('CheckIfTrialPurchaseAllowedForProducts', [
            'userId' => $clientid,
            'pids' => array_column($products, 'id')
        ]);

        $trialInfo = [];
        if (!isset($result[0])) {
            Logger::notice('Trials plugin is not reachable getProductsAction()');
        } else {
            $trialInfo = array_column($result[0], null, 'id');
        }

        foreach($products as &$product) {
            $configoptionData = [];
            /** @noinspection PhpUndefinedFunctionInspection */
            $configurableOptions = getCartConfigOptions($product['id'], "", "", "", true);
            foreach ($configurableOptions as $option) {
                $options = [];
                $optPricings = Arr::groupBy(Helper::conn()->select(
                    "SELECT
                            relid, code, msetupfee, qsetupfee, ssetupfee, asetupfee, bsetupfee, tsetupfee, monthly, quarterly, semiannually, annually, biennially, triennially
                    FROM tblpricing
                    INNER JOIN tblcurrencies ON tblcurrencies.id = tblpricing.currency
                    WHERE type = 'configoptions' AND relid IN (" . implode(',', array_column($option['options'], 'id')) . ")"
                ), 'relid');

                foreach ($option['options'] as $op) {
                    $pricing = [];

                    foreach ($optPricings[$op['id']] as $oppricing) {
                        $currcode = $oppricing['code'];
                        unset($oppricing['code']);
                        unset($oppricing['relid']);
                        $pricing[$currcode] = $oppricing;
                    }

                    $options['option'][] = array("id" => $op['id'], "name" => $op['name'], "recurring" => $op['recurring'], "pricing" => $pricing);
                }

                $configoptionData[] = array("id" => $option['id'], "name" => $option['optionname'], "type" => $option['optiontype'], "options" => $options);
            }

            $customfieldsData = [];
            /** @noinspection PhpUndefinedFunctionInspection */
            $customfields = getCustomFields("product", $product['id'], "", "", "on");
            foreach ($customfields as $field) {
                $customfieldsData[] = ["id" => $field['id'], "name" => $field['name'], "description" => $field['description'], "required" => $field['required']];
            }

            $product['customfields']['customfield'] = $customfieldsData;
            $product['configoptions']['configoption'] = $configoptionData;
            $product['isTrial'] = !!$trialInfo[$product['id']]['trial'];
            $product['trialNotAllowedReason'] = $trialInfo[$product['id']]['reasonNotAllowed'];
        }

        return [
            'result' => 'success',
            'products' => ['product' => $products]
        ];
    }

    public function getInvoicesAction($userId, $limit = 20, $page = 1, $count = false)
    {
        if ($count) {
            $select = 'COUNT(*) AS count';
            $limitPart = $orderBy = '';
        } else {
            $select = '
                inv.*,
                ii.description';
            $limitPart = " LIMIT $limit OFFSET " . $limit * ($page-1);
            $orderBy = 'ORDER BY id DESC';
        }

        $res = Helper::conn()->select("
            SELECT
                $select
            FROM `tblinvoices` inv
            INNER JOIN
                (SELECT DISTINCT
                    ii.invoiceid
                FROM tblinvoiceitems ii
                INNER JOIN tblhosting h ON ii.type = 'Hosting' AND h.id = ii.relid
                INNER JOIN tblproducts p ON p.id = h.packageid AND p.servertype IN ('virtualizor', 'EasyDCIM')
                WHERE ii.userid = ?
                ) AS proxy_inv ON proxy_inv.invoiceid = inv.id
            INNER JOIN tblinvoiceitems ii ON ii.invoiceid = inv.id
            WHERE inv.userid = ? AND ii.type IN ('Hosting')
            $orderBy
            $limitPart", [$userId, $userId]);

        return ['invoices' => is_array($res) ? $res : []];
    }

    protected function beforeExecute(Request $request)
    {
        $module = Addon::getInstanceById('blazing_servers');

        $token = $module->getConfig('apiToken');

        if (!hash_equals($token, $request->headers->get('Auth-Token', ''))) {
            throw new \Exception('Wrong Auth-Token or no Auth-Token header present');
        }
    }
}
