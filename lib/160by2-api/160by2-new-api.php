<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

include_once('160by2-html-parser.php');
/**
 * SMS160BY2Client
 * @author Prakash Vaithilingam
 * @mail prakash.nsm@gmail.com
 * @category SMS
 * Please use this code on your own risk. The author is no way responsible for the outcome arising out of this
 * Good Luck!
 **/
class SMS160BY2NewClient
{

    var $curl;
    var $timeout = 30;
    var $refurl;
    var $newurl;
    var $htmlText;
    var $ids;
    var $id;
	var $cookies;
	var $matches;
	var $curlArray = [];

    /**
     * @param $username
     * @param $password
     * @return bool|string
     */
    function login($username, $password)
    {
        $this->curl = curl_init();
        $uid = urlencode($username);
        $pwd = urlencode($password);

        curl_setopt($this->curl, CURLOPT_URL, "http://www.160by2.com/re-login");
        curl_setopt($this->curl, CURLOPT_POST, true);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, "rssData=&username=$uid&&password=$pwd");
        curl_setopt($this->curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.5) Gecko/2008120122 Firefox/3.0.5");
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($this->curl, CURLOPT_REFERER, "http://www.160by2.com");
        curl_setopt($this->curl, CURLOPT_HEADER, true);
        curl_setopt($this->curl, CURLINFO_HEADER_OUT, true);
                
        curl_setopt($this->curl, CURLOPT_HTTPHEADER , array("content-type: application/x-www-form-urlencoded"));
        curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, array($this, 'curlResponseHeaderCallback')); //"curlResponseHeaderCallback");
		
		$this->htmlText = curl_exec($this->curl);

        // Check if any error occured
        if (curl_errno($this->curl))
            return "access error : " . curl_error($this->curl);
		
        $this->refurl = trim ($this->matches[0][1]);
		$newUrlData = parse_url($this->refurl );
		
		$this->id = str_replace("id=", "", $newUrlData['query']);
		$this->newurl = $newUrlData['scheme'].'://'.$newUrlData['host'].'/SendSMS?'.$newUrlData['query'];
		curl_setopt($this->curl, CURLOPT_HTTPHEADER , array(
                                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
									'Cookie: '.$this->cookies[1][1].'; '.$this->cookies[0][1].'',									
									'Host: www.160by2.com',
									'Upgrade-Insecure-Requests: 1',
									'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36',
									'Referer: http://www.160by2.com/Main.action?id='.$this->id,
									'Connection: keep-alive',
									'Pragma: no-cache',
									'Cache-Control: no-cache'
                                  ));
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_POST, false);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, "");
		curl_setopt($this->curl, CURLOPT_URL, $this->newurl);
		
		$this->htmlText = curl_exec($this->curl);

        
        $parser = new SMS160BY2HTMLParser();
        $parser->load($this->htmlText);
        $this->ids = $parser->getIds();
        return true;
    }


	
	function curlResponseHeaderCallback($ch, $headerLine) {
		if (preg_match('/Location: (.*)/mi', $headerLine, $match) == 1){
			$this->matches[] = $match;	
		}
		if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $cookie) == 1){
			$this->cookies[] = $cookie;
		}
		return strlen($headerLine); 
	}

    /**
     * @param $phone
     * @param $msg
     * @return array
     */
    function send($phone, $msg)
    {
        $result = array();

        // Check the message
        if (trim($msg) == "" || strlen($msg) == 0)
            return "invalid message";

        // Take only the first 140 characters of the message
        $msg = substr($msg, 0, 140);
        // Store the numbers from the string to an array
        $pharr = explode(",", $phone);

        // Send SMS to each number
        foreach ($pharr as $i=>$p) {
            // Check the mobile number
            if (strlen($p) != 10 || !is_numeric($p) || strpos($p, ".") != false) {
                $result[] = array('phone' => $p, 'msg' => $msg, 'result' => "invalid number");
                continue;
            }

			$response = [];
			$mh = curl_multi_init();
			$response["".$p] = (object)[];
			$this->curlArray[$i] = $this->requestCurl($p, $msg);
			curl_multi_add_handle($mh, $this->curlArray[$i]);
		}

		$running = null;
		do {
			curl_multi_exec($mh, $running);
		} while($running > 0);
		 
		 
		foreach($this->curlArray as $i => $c) {
			$res = curl_multi_getcontent($c);
			curl_multi_remove_handle($mh, $c);

			$pos = strpos($res, 'successfully');
			$val = ($pos !== false) ? true : false;
			$result[] = array('phone' => $pharr[$i], 'msg' => $msg, 'result' => "".$val, 'response' => "".$res);			
		}
        return $result;
    }
	
	function requestCurl($p, $msg){
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => "http://www.160by2.com/SendSMSDec19",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => 'hid_exists=yes&fkapps=SendSMSDec19&newsUrl=&pageContext=normal&linkrs=&hidSessionId='.$this->id.'&msgLen=140&maxwellapps='.$this->id.'&feb2by2action=sa65sdf656fdfd&'.$this->ids[0].'=&'.$this->ids[1].'='.$p.'&sendSMSMsg='.$msg.'&newsExtnUrl=&ulCategories=28',
		  CURLOPT_HTTPHEADER => array(
			"cache-control: no-cache",
			"content-type: application/x-www-form-urlencoded",
			'Cookie: '.$this->cookies[1][1].'; '.$this->cookies[0][1].''
		  ),
		));
		return $curl;
	}


    /**
     * logout of current session.
     */
    function logout()
    {
        curl_setopt($this->curl, CURLOPT_URL, "http://www.160by2.com/Logout");
        curl_setopt($this->curl, CURLOPT_REFERER, $this->refurl);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, array(
			'Cookie: '.$this->cookies[1][1].'; '.$this->cookies[0][1].''
		  ));
        $text = curl_exec($this->curl);
        curl_close($this->curl);
    }

}

/**
 * Helper Function to send to sms to single/multiple people via 16by2
 * @example sendSMS160by2 ( '9043218400' , 'password' , '8825248400,9043218400' , 'Hello World')
 */

function sendSMS160by2($uid, $pwd, $phone, $msg)
{
    $client = new SMS160BY2NewClient();
    $client->login($uid, $pwd);
    $result = $client->send($phone, $msg);
    $client->logout();
    return $result;
}

?>

