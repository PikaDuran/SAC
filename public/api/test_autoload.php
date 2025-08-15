<?php
require_once __DIR__ . '/../../vendor/autoload.php';
if (class_exists('PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel')) {
    echo 'OK: Fiel cargada';
} else {
    http: //localhost/SAC/public/api/test_autoload.php
    echo 'ERROR: Fiel NO cargada';
}
