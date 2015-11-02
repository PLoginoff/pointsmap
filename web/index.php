<?php

// web/index.php
require_once __DIR__ . '/../vendor/autoload.php';

class Application extends Silex\Application
{
    use Silex\Application\TwigTrait;
}

$app = new Application();
$app['debug'] = true;
$app['file']  = 'list.xlsx';

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

$app->get('/gmap', function () use ($app) {
    $parser = new VSXLSX\Parser(__DIR__ . '/data/' . $app['file']);
    if ( ! $parser->parse() ) {
        throw new Exception(implode(', ', $parser->get_errors()));
    }

    
    $data = array_map(function($o){
        if ( ! ($o['id'] + $o['lat']) ) {
            return;
        }
        $o['lat'] = (float) $o['lat'];
        $o['lng'] = (float) $o['lng'];
        return $o;
    }, $parser->get_parsed());
    
/*
    [1] => Array
        (
            [id] => 240
            [name] => ТТС-6
            [city] => Альметьевск
            [address] => Строителей 2а
            [phone] => 88553392120
            [website] => renault.tts.ru
            [lng] => 52.269702000000002
            [lat] => 54.91093
*/        

    $cities = array_unique(array_column($data, 'city'));
    $points = $data;

    return $app->render('gmap.html.twig', [
        'googleKey'     => 'AIzaSyD8Es0kDvisoOlfohg7KCeGAzI8GGW79bA',
        'cities'        => $cities,
        'points'        => $points,
        'points_json'   => json_encode($points, JSON_UNESCAPED_UNICODE)
    ]);
});

$app->run();

