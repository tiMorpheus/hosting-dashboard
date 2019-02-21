<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class ClickbankIPNSimulator {
	
    private $uri;
    private $ipn_template;
	private $params;
	private $dev_auth;
	
	
	private function prepare_params($data) {
		if ($this->ipn_template == 'reversed') {
			$this->params = array (
				"payment_status" => "Reversed",
				"mc_gross" => "-0.01",
				"parent_txn_id" => $data[0]
			);
		}
		else if($this->ipn_template == 'complaint'){
			$this->params = array (
				"case_type" => "complaint",
				"txn_id" => $data[0]
			);
		}
		else if($this->ipn_template == 'chargeback'){
			$this->params = array(
				"case_type" => "chargeback",
				"txn_id" => $data[0]
			);
		}
		else if ($this->ipn_template == 'mp_dispute') {
			$this->params = array (
				"case_type" => "dispute",
				"txn_id" => $data[0]
			);
		}
		else if ($this->ipn_template == 'mp_signup') {
			$this->params = array (
				"txn_type" => "mp_signup",
				"payer_email" => $data[0],
				"mp_id" => $data[1]
			);
		}
		
		if(!empty($this->params) & !array_key_exists('payer_email', $this->params))
			$this->params['payer_email'] = 'vitmush@gmail.com';
    }
	
	public function  __construct($uri, $ipn_template, $data, $dev_auth) {
        $this->uri = $uri;
		$this->ipn_template = $ipn_template;
        $this->prepare_params($data);
		$this->dev_auth = $dev_auth;
    }
    
    private function calc_verify_code($data) {
        $secret_key = "SECRET";
        
        $fields = array();
        foreach($data as $k => $v) {
            if($k != 'cverify') {
                $fields[] = $k;
            }
        }
        sort($fields);
        $pop = "";
        foreach($fields as $i) {
            $pop = $pop . $data[$i] . "|";
        }
        $pop = $pop . $secret_key;
        
        $calced_verify = sha1(mb_convert_encoding($pop, "UTF-8"));
        $calced_verify = strtoupper(substr($calced_verify,0,8));
        return $calced_verify;
    }
    /*private function clean_data($data) {
        if(!is_array($data)) {
            return array();
        }
        foreach($data as $k => $v) {
            if(!array_key_exists($k, $this->params)) {
                unset($data[$key]);
            }
        }
        return $data;
    }*/
    public function simulate() {		
		$post_data = array();
		$post_data['simulated_type'] = $this->ipn_template;
        $post_data['payment_date'] = date("H:i:s M d, Y T");
		$post_data = array_merge($post_data, $this->params);
        //$post_data['cverify'] = $this->calc_verify_code($post_data);
		print_r($post_data);
        
        $tmp = array();
        foreach($post_data as $k => $v) {
            $tmp[] = sprintf("%s=%s", $k, urlencode($v));
        }
        return $this->request(implode('&', $tmp));
        
    }
    private function request($post_data) {
		echo "__________________\n";
		echo "url:".$this->uri . "\n";
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $this->uri);
        curl_setopt ($ch, CURLOPT_TIMEOUT, 80);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt ($ch, CURLOPT_POST, 1);
        curl_setopt ($ch, CURLOPT_VERBOSE, 1);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		if($this->dev_auth == true)
			curl_setopt($ch, CURLOPT_USERPWD, "dev:dev");
        $doc = curl_exec($ch);
        
        if(curl_error($ch)) {
            throw new Exception("Unable to access URI");
        }
        return $doc;
    }
}
function main($argc, $argv) {
    try {
		echo "\n\nBEGIN:\n___________________\n\n";
        if($argc < 3) {
            throw new Exception("Wrong parameters!");
        }
		else if(($argv[2] == "reversed" | $argv[2] == "mp_signup") & $argc < 4) {
			throw new Exception("Wrong number of parameters");
		}
        $uri_template = $argv[1];
		$ipn_template = $argv[2];
		$params = array_slice($argv, 3);
		
		$uri = "";
		$dev_auth = false;
		if($uri_template == "stage"){
			$uri = "https://whmcs.staging.sprious.com/modules/gateways/callback/paypalbilling.php";
			//$dev_auth = true;
		}
		else if ($uri_template == "prod")
			$uri = "https://billing.blazingseollc.com/hosting/modules/gateways/callback/paypalbilling.php";
		else if($uri_template == "dev")
			$uri = "https://whmcs.devcopy.blazingseollc.com/modules/gateways/callback/paypalbilling.php";
		else if($uri_template == "devs")
			$uri = "https://whmcs.dev.sprious.com/modules/gateways/callback/paypalbilling.php";
		
        $ipn = new ClickbankIPNSimulator($uri, $ipn_template, $params, $dev_auth);
        $doc = $ipn->simulate();
        echo $doc;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
		$ipnTemplateList = "\n\t\t\treversed\n\t\t\tchargeback\n\t\t\tcomplaint\n\t\t\tmp_dispute\n\t\t\tmp_signup";
		if($argc >= 2 && $argv[1] == "help") {
			if($argc == 3){
				if($argv[2] == "reversed")
					echo "### param[0]: parent_txn_id\n";
				else if($argv[2] == "chargeback" || $argv[2] == "complaint" || 	$argv[2] == "mp_dispute")
					echo "### param[0]: txn_id";
				else if ($argv[2] == "mp_signup")
					echo "### param[0]: payer_email\n    param[1]: mp_id (B-[0-9]+) ";
			}
			else {
				echo "### Example:\n";
				echo "### php -f ipn.php dev|stage|prod ipn_template params\n";
				echo "### ipn_template could be:".$ipnTemplateList."\n";
				echo "### enter 'help ipn_template' to see help for params\n";
			}
		}
		else {
			echo "### Use command help to see help";
		}
		
		echo "\n";
    }
}


main($argc, $argv);

