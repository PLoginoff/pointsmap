<?php
// web/index.php
require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Response;
use Binfo\Silex\MobileDetectServiceProvider;

class Application extends Silex\Application
{
    use Silex\Application\TwigTrait;
}
$app          = new Application();
$app['debug'] = true;
$app['file']  = 'list.xlsx';

mb_internal_encoding('UTF-8');

$app->register(new Silex\Provider\TwigServiceProvider(),
    [
        'twig.path' => __DIR__.'/views',
    ]
);
$app->register(new MobileDetectServiceProvider());


$app['points'] = function() use ($app) {
    $parser = new VSXLSX\Parser(__DIR__.'/data/'.$app['file']);
    if (!$parser->parse()) {
        throw new Exception(implode(', ', $parser->get_errors()));
    }

    $i = 1001;
    return map(function($o) use (&$i) {
        if (!$o['network'] || !$o['city'] || !$o['fulladdress']) {
            return false;
        }

        $coors = geoCoder($o['fulladdress']);
        if (!$coors) {
            return false;
        }

        return [
            'id'        => $i++,
            'city'      => $o['city'],
            'street'    => $o['street'],
            'phone'     => $o['phone'] ?: null,
            'lat'       => (float) $coors[1],
            'lng'       => (float) $coors[0],
            'network'   => $o['network'],
            'name'      => $o['network'],
            'color'     => '#5c47b4'
        ];
    }, $parser->get_parsed());
};

$app['search'] = function() use ($app) {
    if ( isset( $_GET['search'] ) ) {
        $search = map(function($o){
            if (mb_stripos($o['network'], $_GET['search']) !== false) {
                return $o;
            } else {
                return false;
            }
        }, $app['points']); // fixme
    } else {
        $search = [];
    }
    $search = array_slice($search, 0, 25);
    return $search;
};

$app->get('/',  function () use ($app) {
    if( $app["mobile_detect"]->isMobile() && !$app["mobile_detect"]->isTablet() ){
        return $app->redirect('/mobile');
    }

    $points     = $app['points'];
    $cities     = array_unique(array_column($points, 'city'));
    $networks   = array_unique(array_column($points, 'network'));
    $search     = $app['search'];

    return $app->render('index.html.twig',
        [
            'googleKey'     => 'AIzaSyD8Es0kDvisoOlfohg7KCeGAzI8GGW79bA',
            'cities'        => $cities,
            'networks'      => $networks,
            'search'        => $search,
            'points_json'   => json_encode($points, JSON_UNESCAPED_UNICODE)
        ],
        new Response(null, 200, ['Cache-Control' => 's-maxage=3600, public']));
});

$app->get('/mobile',  function () use ($app) {
    $points     = $app['points'];
    $cities     = array_unique(array_column($points, 'city'));
    $networks   = array_unique(array_column($points, 'network'));
    $search     = $app['search'];

    return $app->render('mobile.html.twig',
        [
            'googleKey'     => 'AIzaSyD8Es0kDvisoOlfohg7KCeGAzI8GGW79bA',
            'cities'        => $cities,
            'networks'      => $networks,
            'search'        => $search,
            'points_json'   => json_encode($points, JSON_UNESCAPED_UNICODE)
        ],
        new Response(null, 200, ['Cache-Control' => 's-maxage=3600, public']));
});

$app->run();

function map($fn, $array)
{
    return array_filter(array_map($fn, $array));
}

function geoCoder($string)
{
    $url  = 'https://geocode-maps.yandex.ru/1.x/?format=json&kind=street&results=1&geocode=';
    $file = __DIR__.'/data/cache.json';

    if (!isset($GLOBALS['geocoder'])) {
        $GLOBALS['geocoder'] = (array) json_decode(file_get_contents($file));
    }
    $cache = & $GLOBALS['geocoder'];

    if (!isset($cache[$string])) {
        $response = file_get_contents($url.urlencode($string));
        $result   = json_decode($response);

        if (isset($result->response->GeoObjectCollection->featureMember[0])) {
            $point = $result->response->GeoObjectCollection->featureMember[0]->GeoObject->Point->pos;
        } else {
            $point = '';
        }

        $cache[$string] = $point;
        file_put_contents($file,
            json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    return $cache[$string] ? explode(' ', $cache[$string]) : null;
}
