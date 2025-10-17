<?php
namespace RobThree\Auth\Providers\Qr;

interface IQRCodeProvider
{
    /**
     * Returns the image raw data that is the QR code for the specified text
     * @param string $qrtext
     * @param int $size
     * @return mixed
     */
    public function getQRCodeImage($qrtext, $size);

    /**
     * Returns the mimetype for the image returned by getQRCodeImage()
     * @return string
     */
    public function getMimeType();
}
