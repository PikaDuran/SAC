<?php
// src/helpers/fiel_factory.php
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;

if (!function_exists('create_fiel')) {
    /**
     * Crea una instancia de Fiel usando los contenidos de los archivos y la contraseña
     * @param string $certificatePath Ruta absoluta al archivo .cer
     * @param string $keyPath Ruta absoluta al archivo .key
     * @param string $password Contraseña de la llave
     * @return Fiel
     */
    function create_fiel(string $certificatePath, string $keyPath, string $password): Fiel
    {
        $certificateContents = file_get_contents($certificatePath);
        $keyContents = file_get_contents($keyPath);
        return Fiel::create($certificateContents, $keyContents, $password);
    }
}
