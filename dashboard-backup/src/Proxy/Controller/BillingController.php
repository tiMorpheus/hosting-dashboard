<?php

namespace Proxy\Controller;

class BillingController  extends AbstractController
{

    public function getInvoices(Request $request) {

//        $limit = 0; $offset = 0;

//
//        if(($page = $request->get('page')) && ctype_digit($page)) {
//            $limit = $this->config['portsPerPage'] + 1;
//            $offset = $this->config['portsPerPage'] * ($page);
//        }
//
//        $data = $this->app[ 'integration.whmcs.api' ]->getInvoices($this->getUser()->getDetails('whmcsId'), $offset);
//


        $invoices = $this->app['integration.whmcs.plugin.servers']->getInvoicesByUserId($this->getUser()->getDetails('whmcsId'), 10 ,$request->get('page'));


        return new JsonResponse($invoices['invoices']);
    }

    public function index()
    {
//        $data = $this->app[ 'integration.whmcs.api' ]->getInvoices($this->getUser()->getDetails('whmcsId'));
        $invoices = $this->app['integration.whmcs.plugin.servers']->getInvoicesByUserId($this->getUser()->getDetails('whmcsId'), 10 );
        $totalInvoicesCountArr = $this->app['integration.whmcs.plugin.servers']->getInvoicesByUserId($this->getUser()->getDetails('whmcsId'), 10 ,1, true);



//        $data1 = $this->app[ 'integration.whmcs.api' ]->getInvoices($this->getUser()->getDetails('whmcsId'), 0 , 100);
//
//        $data1 = $data1['invoices']['invoice'];
//
//
//
//        $data_id = array();
//        foreach ($data1 as $key=>$arr ){
//
//            $data_id[$key] = $arr['id'];
//
//        }
//
////        for ($i=0; $i <100000; $i++){
////            $data_tmp = $data1;
//
//            array_multisort($data_id, SORT_DESC, $data1);
////        }
//
//



        if(empty($invoices['invoices'])){
            return $this->app['twig']->render('billing/index.html.twig', [
                'invoices' => null,
                'numreturned' => 0,
                'total' => 0
            ]);
        }
//        var_dump($this->getUser()->getDetails('whmcsId'));

//        echo "<pre>";
//        print_r($invoices);
//        echo "</pre>";



        $total = $totalInvoicesCountArr['invoices'][0]['count'];
//        $numreturned = $data['numreturned'];


//        $i = 0;



        if ($total == 0) {
            return $this->app['twig']->render('billing/index.html.twig', [
                'invoices' => null,
                'numreturned' => 0,
                'total' => 0

            ]);
        }



        return $this->app['twig']->render('billing/index.html.twig', [
            'invoices' => $invoices['invoices'],
            'numreturned' => count($invoices['invoices']),
            'total' => $total
        ]);
    }

}
