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
class SMS160BY2Client
{

    var $curl;
    var $timeout = 30;
    var $jsToken;
    var $refurl;
    var $newurl;
    var $htmlText;
    var $ids;
    var $cookies;

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

        // Setup for login
        curl_setopt($this->curl, CURLOPT_URL, "http://www.160by2.com/re-login");
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, "rssData=&username=" . $uid . "&password=" . $pwd);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl, CURLOPT_MAXREDIRS, 20);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.5) Gecko/2008120122 Firefox/3.0.5");
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($this->curl, CURLOPT_REFERER, "http://www.160by2.com");
        curl_setopt($this->curl, CURLOPT_HEADER, true);
        curl_setopt($this->curl, CURLINFO_HEADER_OUT, true);
                
        curl_setopt($this->curl, CURLOPT_HTTPHEADER , array(
                                    "content-type: application/x-www-form-urlencoded"
                                  ));
        
        $this->htmlText = curl_exec($this->curl);

        // Check if any error occured
        if (curl_errno($this->curl))
            return "access error : " . curl_error($this->curl);

        // Set the home page from where we can send message
        $this->refurl = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
        
        $this->cookies = array();
        preg_match_all('/Set-Cookie:(?<cookie>\s{0,}.*)$/im', $this->htmlText, $this->cookies);
        
        $newUrlData = parse_url( $this->refurl );
        
        $this->newurl = $newUrlData['scheme'].'://'.$newUrlData['host'].'/SendSMS?'.$newUrlData['query'];
        curl_setopt($this->curl, CURLOPT_URL, $this->newurl);

        // Extract the token from the URL
        $this->jstoken = str_replace("id=", "", $newUrlData['query']);
        
        //curl_setopt($this->curl, CURLOPT_GET, 1);
        curl_setopt($this->curl, 
            CURLOPT_HTTPHEADER , array(
                "cache-control: no-cache",
                "connection: keep-alive",
                "content-type: application/x-www-form-urlencoded",
                'cookie:  '.$this->cookies['cookie'][2].';'.$this->cookies['cookie'][1],
                "host: ".$newUrlData['host']."",
                "origin: http://".$newUrlData['host']."",
                "referer: ". $this->refurl."",
                "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36"
              ));
    
        //Go to the homepage
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
        foreach ($pharr as $p) {
            // Check the mobile number
            if (strlen($p) != 10 || !is_numeric($p) || strpos($p, ".") != false) {
                $result[] = array('phone' => $p, 'msg' => $msg, 'result' => "invalid number");
                continue;
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

/**
 * Helper Function to send to sms to single/multiple people via way2sms
 * @example sendSMS160by2 ( '9000012345' , 'password' , '987654321,9876501234' , 'Hello World')
 */

function sendSMS160by2($uid, $pwd, $phone, $msg)
{
    $client = new SMS160BY2Client();
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

