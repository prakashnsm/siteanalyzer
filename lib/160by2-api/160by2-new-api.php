<?php

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
	var $curlArray = [];

    /**
     * @param $username
     * @param $password
     * @return bool|string
     */
    function login($username, $password)
    {
		global $cookies;
		global $matches;
	
        $this->curl = curl_init();
        $uid = urlencode($username);
        $pwd = urlencode($password);

        curl_setopt($this->curl, CURLOPT_URL, "http://www.160by2.com/re-login");
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, "rssData=&username=8825248400&&password=tsp231087");
        curl_setopt($this->curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.5) Gecko/2008120122 Firefox/3.0.5");
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($this->curl, CURLOPT_REFERER, "http://www.160by2.com");
        curl_setopt($this->curl, CURLOPT_HEADER, true);
        curl_setopt($this->curl, CURLINFO_HEADER_OUT, true);
                
        curl_setopt($this->curl, CURLOPT_HTTPHEADER , array(
                                    "content-type: application/x-www-form-urlencoded"
                                  ));
        curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, "curlResponseHeaderCallback");
		
		$this->htmlText = curl_exec($this->curl);

        // Check if any error occured
        if (curl_errno($this->curl))
            return "access error : " . curl_error($this->curl);

        $this->refurl = trim ($matches[0][1]);
		$newUrlData = parse_url($this->refurl );
		
		$this->id = str_replace("id=", "", $newUrlData['query']);
		$this->newurl = $newUrlData['scheme'].'://'.$newUrlData['host'].'/SendSMS?'.$newUrlData['query'];
		$this->cookies = $cookies;
		curl_setopt($this->curl, CURLOPT_HTTPHEADER , array(
                                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
									'Cookie: '.$cookies[1][1].'; '.$cookies[0][1].'',									
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
			$this->curlArray[$i] = requestCurl($p, $msg);
			curl_multi_add_handle($mh, $this->curlArray[$i]);
		}
  $running = null;
  do {
    curl_multi_exec($mh, $running);
  } while($running > 0);
 
 
  foreach($this->curlArray as $i => $c) {
    $res = curl_multi_getcontent($c);
    curl_multi_remove_handle($mh, $c);
	//$data = json_decode($res);
	//$d = json_decode($data->d);
	//$data = $d->rows[0];
	/*$response[$i]->data = array(
						"user" => $users[$i+1]['username'],
						"userid" => $users[$i+1]['userid'],
						"points" => $data->EarnPaidClicks,
						"pending" => $data->PendingPaidClicks,
						"total" => $data->WorkAmount,
						"date" => $data->AssignedDate,
					);*/
	
  }
  
            // Setup to send SMS
            curl_setopt($this->curl, CURLOPT_URL, $this->way2smsHost . 'smstoss.action');
            curl_setopt($this->curl, CURLOPT_REFERER, curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL));
            curl_setopt($this->curl, CURLOPT_POST, 1);

            curl_setopt($this->curl, CURLOPT_POSTFIELDS, "ssaction=ss&Token=" . $this->jstoken . "&mobile=" . $p . "&message=" . $msg . "&button=Login");
            $contents = curl_exec($this->curl);

            //Check Message Status
            $pos = strpos($contents, 'Message has been submitted successfully');
            $res = ($pos !== false) ? true : false;
            $result[] = array('phone' => $p, 'msg' => $msg, 'result' => $res);
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
  CURLOPT_POSTFIELDS => "hid_exists=yes&fkapps=SendSMSDec19&newsUrl=&pageContext=normal&linkrs=&hidSessionId=AFFF8DB52F7B4CD601CCD9C6C981ABA1.8505&msgLen=140&maxwellapps=AFFF8DB52F7B4CD601CCD9C6C981ABA1.8505&feb2by2action=sa65sdf656fdfd&AFWVG=&HERKSI=8825248400&sendSMSMsg=1233242jkhjkhkj&newsExtnUrl=&ulCategories=28",
  CURLOPT_HTTPHEADER => array(
    "cache-control: no-cache",
    "content-type: application/x-www-form-urlencoded",
    "cookie: JSESSIONID=EE~AFFF8DB52F7B4CD601CCD9C6C981ABA1.8505;LastLoginCookie=\"12-01-2017-901-11:23-901-mozilla firefox 3.0.5-901-122.178.98.127-901-19-01-2011\"",
    "postman-token: 89cff825-bbb8-dcbf-84fc-d0be3d811180"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
	}


    /**
     * logout of current session.
     */
    function logout()
    {
        curl_setopt($this->curl, CURLOPT_URL, $this->way2smsHost . "LogOut");
        curl_setopt($this->curl, CURLOPT_REFERER, $this->refurl);
        $text = curl_exec($this->curl);
        curl_close($this->curl);
    }

}


$cookies = array();
$matches = array();

function curlResponseHeaderCallback($ch, $headerLine) {
    global $cookies;
	global $matches;
	if (preg_match('/Location: (.*)/mi', $headerLine, $match) == 1){
		$matches[] = $match;	
	}
	if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $cookie) == 1){
		$cookies[] = $cookie;
	}
	return strlen($headerLine); 
}

/**
 * Helper Function to send to sms to single/multiple people via way2sms
 * @example sendSMS160by2 ( '9000012345' , 'password' , '987654321,9876501234' , 'Hello World')
 */

function sendSMS160by2($uid, $pwd, $phone, $msg)
{
    $client = new SMS160BY2NewClient();
    $client->login($uid, $pwd);
    //echo $client->htmlText;

    
    $myfile = fopen("newfile.txt", "w") or die("Unable to open file!");

fwrite($myfile, $client->htmlText);
    
    var_dump($client);
    //$result = $client->send($phone, $msg);
    //$client->logout();
    //return $result;
}

sendSMS160by2 ( '8825248400' , 'tsp231087' , '8825248400' , 'Hello World');

?>

