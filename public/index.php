<?php

// (C) 2014 prakashsts <prakash.nsm@gmail.com>

set_include_path('../' . PATH_SEPARATOR . get_include_path());

require 'vendor/Slim/Slim.php';
require 'vendor/TwigView.php';

require 'lib/http.php';
require 'lib/site.php';

require 'lib/way2sms-api.php';

define('DATA_DIR', '../data/');
define('LOG_DIR',  '../logs/');
define('CACHE_DIR','../cache/');

define('DEBUG', getenv('APPLICATION_ENV'));

TwigView::$twigDirectory =  'vendor/Twig';
$twigview = new TwigView();

$app = new Slim(array('templates.path' => '../views',
                      'debug' => true,
                      'view'  => $twigview)
);

// Add some functions that will be usable inside Twig
twig_add_function(array($twigview, 'is_float', 'var_dump'));

$app->get('/', function () use ($app)
{
    $data['url']        = 'http://'. get_random_url();
    $data['check_url']  = "http://{$_SERVER['SERVER_NAME']}/check?url=";
    $data['show_try']   = true;

    $app->render('index.twig', $data);
});

$app->get('/sms', function () use ($app)
{
    $data['url']        = 'http://'. get_random_url();
    $data['check_url']  = "http://{$_SERVER['SERVER_NAME']}/check?url=";
    $data['show_try']   = true;

    $app->render('sms.twig', $data);
});

$app->post('/sendSMS', function () use ($app)
{
    $data = sendSMS(0);
	$app->render('sms.twig', $data);
});

$app->post('/sendSMSMyAccount', function () use ($app)
{
    $data = sendSMS(1);
	$app->render('sms.twig', $data);
});

$app->post('/api/sendSMS', function () use ($app)
{
    $data = sendSMS(0);
	$response->withHeader('Content-Type', 'application/json');
	$response->write(json_encode($data));
});

$app->post('/api/sendSMSMyAccount', function () use ($app)
{
    $data = sendSMS(1);
	$app->response->withHeader('Content-Type', 'application/json');
	$app->response->write(json_encode($data));
});

function sendSMS($i){
	$step = false;
	$user = getenv('WAY2SMS_ACCONT_USER');
	$pass = getenv('WAY2SMS_ACCONT_PASS');
		
	if(($i==0 && isset($_REQUEST['user']) && isset($_REQUEST['pwd'])) || $i==1) {
		$step = true;
		$user = ($i==0) ? $_REQUEST['user'] : getenv('WAY2SMS_ACCONT_USER');
		$pass = ($i==0) ? $_REQUEST['pwd'] : getenv('WAY2SMS_ACCONT_PASS');
	}
	
	$data['show_success']   = false;    
	if ( $step && isset($_REQUEST['mobNo']) && isset($_REQUEST['smsMessage'])) {
	    $res = sendWay2SMS($user, $pass, $_REQUEST['mobNo'], $_REQUEST['smsMessage']);
	    if (is_array($res))
		$data['show_success'] =  $res[0]['result'] ? true : false;
	}
	
	if(!$data['show_success']){
		$data['message'] = "Your message didn't send. please try again.";
	}
	return $data;
}

$app->get('/check', function () use ($app)
{
    $url = trim($app->request()->params('url'));

    if ($url === 'random') {
        $url = get_random_url();
    }

    // Normalize URL to include "http://" prefix.
    $url2 = parse_url($url);
    if (!isset($url2['scheme'])) {
        $url = "http://{$url2['path']}";
    }

    $data['url'] = $url;

    $refresh = $app->request()->params('refresh') === '1' ? true : false;

    $http = new Http();
    list($header, $body) = $http->get($url, $refresh);
    if (!$header) {
        $app->error(new Exception($body));
    }

    $xml_data = array(
        'Server Information'       => 'servers.xml',
        'Content Delivery Network' => 'cdn.xml',
        'Advertising'              => 'ads.xml',
        'Analytics and Tracking'   => 'trackings.xml',
        'CMS'                      => 'cms.xml',
		'Web Portals'              => 'portals.xml',
		'CSS Frameworks'           => 'css.xml',
        'Chart Libraries'          => 'charts.xml',
        'Frameworks'               => 'frameworks.xml',
        'Javascript Libraries'     => 'js.xml',
        'Javascript Frameworks'    => 'jsframeworks.xml',
        'Widgets'                  => 'widgets.xml',
        'Audio/Video'              => 'av.xml',
        'Aggregation'              => 'aggregation.xml',
        'Payments'                 => 'payments.xml',
        'Document Info'            => 'document.xml',
        'Meta Tags'                => 'meta.xml',
    );

    $site    = new Site($header, $body);
    $results = array();

    $mt = microtime(true);

    foreach ($xml_data as $title => $xml) {
        $result = $site->analyze(DATA_DIR . $xml);
        if (!empty($result)) {
            $results[$title] = $result;
        }
    }

    $data['analysis_time']       = sprintf("%.02f", microtime(true) - $mt);
    $data['total_time']          = sprintf("%.02f", $http->total_time);
    $data['speed_download']      = format_filesize($http->speed_download);
    $data['size_download_gzip']  = format_filesize($http->size_download_gzip);
    $data['size_download_gzip2'] = number_format($http->size_download_gzip);
    $data['size_download']       = format_filesize($http->size_download);
    $data['size_download2']      = number_format($http->size_download);
    $data['results']             = $results;
    $data['http']                = $http;
    $data['check_url']           = "http://{$_SERVER['SERVER_NAME']}/check?url=";

    $app->render('index.twig', $data);

});

// Get a random URL from Alexa's Top 1000 Sites.
function get_random_url()
{
    $file = file(DATA_DIR . '1k.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return $file[array_rand($file)];
}


// Not used.
function get_random_url_db()
{
    $mysqli = new mysqli("localhost", "site_analyzer_user_1", "site_analyzer_user_pass", "site_analyzer_top_sites");
    if ($mysqli->connect_errno) {
        throw new Exception("Connect failed: %s\n" . $mysqli->connect_error);
    }
    if ($result = $mysqli->query("SELECT url FROM sites ORDER BY RAND() LIMIT 1")) {
        $url =  $result->fetch_object()->url;
        $result->close();
    }
    $mysqli->close();
    return $url;
}

$app->error(function (Exception $e) use ($app)
{
    $data['message']   = $e->getMessage();
    $data['url']       = trim($app->request()->params('url'));
    $data['check_url'] = "http://{$_SERVER['SERVER_NAME']}/check?url=";

    $app->render('index.twig', $data);
});

//\\//\\//\\
$app->run();
//\\//\\//\\

function twig_add_function($funcs) {
    $twigview = array_shift($funcs);
    foreach ($funcs as $func) {
        $env = $twigview->getEnvironment();
        $env->addFunction($func, new Twig_Function_Function($func));
    }
}

function format_filesize($size) {
    if ($size < 0) {
        return 0;
    }
    $sizes = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
    return $size ? round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $sizes[$i] : '0 Bytes';
}
