<?php
// (C) 2012 hush2 <hushywushy@gmail.com>

define('CRLF' , "\r\n");

class Http
{
    function __construct()
    {
        $user_agents = array(
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)',
            'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.9) Gecko/2008052906 Firefox/3.0',
            'Mozilla/5.0 (Macintosh; U; Intel Mac OS X; de-de) AppleWebKit/523.10.3 (KHTML, like Gecko) Version/3.0.4 Safari/523.10',
            'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_4; de-de) AppleWebKit/533.16 (KHTML, like Gecko) Version/5.0 Safari/533.16',
            'Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like Gecko) Version/3.0 Mobile/1A498b Safari/419.3',
            'Mozilla/4.8 [en] (Windows NT 6.0; U)',
            'Opera/9.20 (Windows NT 6.0; U; en)',        
        );
        
        $this->curlopts = array(
            CURLOPT_CAINFO          => __DIR__ . '/cacert.pem',     // HTTPS
            CURLOPT_HEADER          => true,    
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 3,
            CURLOPT_CONNECTTIMEOUT  => 6,       // Connect timeout
            CURLOPT_TIMEOUT         => 12,      // CURL timeout (includes connect timeout)
            CURLOPT_ENCODING        => 'gzip',
            CURLOPT_FAILONERROR     => true,    // Error on 4xx/5xx            
            CURLOPT_USERAGENT       => array_rand($user_agents),  
        );
    }

    function get($url, $refresh)
    {
        list($response, $ci) = $refresh ? $this->do_curl($url)
                                        : $this->get_cache($url);
        if (!$response) {
            // On error: $response is false, $ci has error message
            return array($response, $ci);
        }
        $header = substr($response, 0, $ci['header_size']);
        // RFC2616 specifies CRLF as EOL terminator, but some servers use LF.
        $header = preg_replace("/(?<!\r)\n/", "\r\n", $header);

        // Get the last header. Sometimes request gets redirected through
        // different web servers which will result in more than one web server or OS
        // being detected.
        $header = explode(CRLF . CRLF, $header, $ci['redirect_count'] + 1);
        $header = array_pop($header);

        $this->gzip = stripos('content-encoding: gzip', $header) ? true : false;

        $body = substr($response, $ci['header_size']);

        if (defined('DEBUG') && defined('LOG_DIR')) {
            file_put_contents(LOG_DIR . 'http_header.txt', $header);
            file_put_contents(LOG_DIR . 'http_body.txt',   $body);
        }

        $this->total_time          = $ci['total_time'];
        $this->size_download_gzip  = $ci['size_download'];
        $this->size_download       = isset($this->gzip) ? strlen($body)
                                                        : $this->size_download_gzip;
        $this->speed_download      = $ci['speed_download']  ;
        $this->url                 = strtolower($ci['url']);   // New URL if redirected

        return array($header, $body);
    }

    function get_cache($url)
    {
        $file = glob(CACHE_DIR . urlencode($url) . '.*.cache');
        @$file = $file[0];
        if (file_exists($file)) {
            $response = file_get_contents($file);            
            preg_match('/\.(\d+).(\d+).cache$/i', $file, $matches);
            // Populate fake curl_info
            $ci['header_size']    = (int) $matches[1];
            $ci['redirect_count'] = (int) $matches[2];
            $ci['total_time']     = -1;
            $ci['size_download']  = strlen($response) - $ci['header_size'];
            $ci['speed_download'] = -1;
            $ci['url']            = $url;
            
            return array($response, $ci);

        } else {
            list($response, $ci) = $this->do_curl($url);
            return array($response, $ci);
        }
    }

    function do_curl($url)
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, $this->curlopts);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        if ($errno) {
            return array(false, curl_error($ch));
        }
        $ci = curl_getinfo($ch);
        file_put_contents(CACHE_DIR . urlencode($url) . ".{$ci['header_size']}.{$ci['redirect_count']}.cache", $response);

        if (defined('DEBUG') && defined('LOG_DIR')) {
            file_put_contents(LOG_DIR . 'RESPONSE.txt', $response);
        }
        return array($response, $ci);
    }
}
