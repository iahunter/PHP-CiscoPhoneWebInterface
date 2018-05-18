# PHP-CiscoPhoneWebInterface
Helper for dealing with the trashtastic cisco phone web interfaces

Example usage:
```
<?php
require_once(__DIR__.'/vendor/autoload.php');

// settings
$phone = '10.202.12.345';
$user = 'admin';
$pass = 'Z0mgS3cretP4ssw0rd4u!';
$certfilepath = 'radius_root_ca.cer';

// build our helper object
$cpwi = new \Metaclassing\CiscoPhoneWebInterface\Cisco8861($phone);
// set the login attempt
$success = $cpwi->login($user, $pass);

// if you want the device info page
$html = $cpwi->getDeviceInfoPage();
//echo $html . PHP_EOL;

// get the current certificates
$html = $cpwi->getCertificatePage();
//echo $html . PHP_EOL;
$x509 = file_get_contents($certfilepath);
$hasCertInstalled = $cpwi->checkCertInCiscoHtml($html, $x509);

// only install the new cert if needed
if ($hasCertInstalled) {
    echo 'cert already installed'.PHP_EOL;
} else {
    echo 'cert needs to be installed'.PHP_EOL;
    $html = $cpwi->installRootCaCert($certfilepath);
}

// Get the device network port info
$networkinfo = $cpwi->getNetworkInfo();
print_r($networkinfo); 
```
