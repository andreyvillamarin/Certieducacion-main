<?php
// Usaremos la librería moderna chillerlan/php-qrcode que ya está en el proyecto

require_once __DIR__ . '/autoloader.php'; 
require_once __DIR__ . '/IQRCodeProvider.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;
use chillerlan\QRCode\Output\QRMarkupSVG;

class CustomQRCodeProvider implements IQRCodeProvider
{
    public function getMimeType()
    {
        return 'image/svg+xml';
    }

    public function getQRCodeImage($qrtext, $size)
    {
        $options = new QROptions([
            'outputType'             => QRMarkupSVG::class,
            'svgUseFillAttributes'   => true,
            // Pedimos a la librería que nos devuelva la imagen ya codificada en base64
            'imageBase64'            => true, 
        ]);

        return (new QRCode($options))->render($qrtext);
    }
}