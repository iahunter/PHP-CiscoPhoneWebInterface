<?php

namespace Metaclassing\CiscoPhoneWebInterface;

class SmartPhone extends Base
{
    // our curler helper
    protected $curler = null;
    // the IP address (or hostname i guess) of the phone
    protected $phone = '';
    protected $port = 8443;
    protected $baseUri = '';
    protected $csrfToken = '';

    public function __construct($phone, $port = 8443)
    {
        // set our variables
        $this->phone = $phone;
        $this->port = $port;
        // set some calculated variables
        $this->baseUri = 'https://'.$this->phone.':'.$this->port;

        $this->curler = new \Metaclassing\Curler\Curler();
        // enable for verbose debugging
        //curl_setopt($this->curler->curl, CURLOPT_VERBOSE, true);
    }

    public function login($username, $password)
    {
        // get / for a cookie and csrf token
        $html = $this->visitLoginAndGetCookie();
        // post the login information
        $html = $this->postLoginCreds($username, $password);
        // check the posted response for success
        $success = $this->checkLoginSuccessMessage($html);

        return $success;
    }

    protected function visitLoginAndGetCookie()
    {
        $html = $this->curler->get($this->baseUri);
        $this->updateCsrfToken($html);

        return $html;
    }

    protected function postLoginCreds($username, $password)
    {
        // try to post the login creds
        $url = $this->baseUri.'/CGI/Java/Serviceability?adapter=loginPost';
        $referer = $this->baseUri.'/CGI/Java/Serviceability?adapter=device.statistics.device';
        // this is already the right format
        $body = [
            'CSRFToken'    => $this->csrfToken,
            'username'     => $username,
            'userPassword' => $password,
        ];
        // get the response
        $html = $this->curler->post($url, $referer, $body);
        //echo 'Login response message'.$html.PHP_EOL;
        return $html;
    }

    protected function checkLoginSuccessMessage($html)
    {
        // check and make sure we logged in successfully
        $regex = '/You have successfully signed in as admin/';
        if (! preg_match($regex, $html, $hits)) {
            throw new \Exception('Authentication failure, did not see login success text in '.$html);
        }

        return true;
    }

    public function getCertificatePage()
    {
        // get the certificates list page
        $referer = $this->baseUri.'/CGI/Java/Serviceability?adapter=device.statistics.device';
        $url = $this->baseUri.'/CGI/Java/Serviceability?adapter=certificate';
        //echo 'Getting url '.$url.PHP_EOL;
        $html = $this->curler->get($url, $referer);

        return $html;
    }

    public function checkCertInCiscoHtml($html, $x509)
    {
        $stupidCiscoHtmlName = $this->buildStupidCiscoCertName($x509);
        echo 'I calculated the stupid cisco html name is '.$stupidCiscoHtmlName.PHP_EOL;
        //echo 'I got this html to search through '.PHP_EOL.$html.PHP_EOL;
        return $this->isStringInText($stupidCiscoHtmlName, $html);
    }

    protected function buildStupidCiscoCertName($x509)
    {
        // just use openssl to parse the x509 text
        $certinfo = openssl_x509_parse($x509, true);
        // suck out the subject dn pieces from the cert
        $subjects = $certinfo['subject'];
        $pieces = [];
        // loop through and convert the pieces into a simple array
        foreach ($subjects as $key => $value) {
            $pieces[] = $key.'='.$value;
        }
        // oh look fucking cisco apparently sorts the stupid DN pieces now wtf really
        asort($pieces);
        // merge all the pieces into a commaspace delimited string
        $dn = implode(', ', $pieces);
        // voila we have the worlds dumbest format
        $stupidCiscoHtmlName = str_replace('=', '&#x3D;', $dn);

        return $stupidCiscoHtmlName;
    }

    protected function isStringInText($needle, $haystack)
    {
        // only fucking find function with ass backwards logic
        $position = strpos($haystack, $needle);
        // reverse polish logic
        return $position !== false;
    }

    public function getDeviceInfoPage()
    {
        // get the certificates list page
        $referer = $this->baseUri.'/CGI/Java/Serviceability?adapter=device.statistics.device';
        $url = $this->baseUri.'/CGI/Java/Serviceability?adapter=device.statistics.device';
        //echo 'Getting url '.$url.PHP_EOL;
        $html = $this->curler->get($url, $referer);

        return $html;
    }

    public function installRootCaCert($rootCaFilePath)
    {
        // get the certificate install page
        $html = $this->getRootCertInstallPage();
        $html = $this->installNewRootCert($rootCaFilePath);
        // TODO: write a html parser to regex for a success message or throw an exception
        return $html;
    }

    protected function getRootCertInstallPage()
    {
        // get the certificates list page first so we can get a csrf token
        $html = $this->getCertificatePage();
        $this->updateCsrfToken($html);
        // get the certificates install page by sending a fucking post
        $referer = $this->baseUri.'/CGI/Java/Serviceability?adapter=certificate';
        $url = $this->baseUri.'/CGI/Java/Serviceability?adapter=install_rootcert';
        $body = [
            'CSRFToken'  => $this->csrfToken,
        ];
        $html = $this->curler->post($url, $referer, $body);
        $this->updateCsrfToken($html);

        return $html;
    }

    protected function installNewRootCert($rootCaFilePath)
    {
        // TODO: write a check to make sure the file exists and is maybe a single x509 cert in pem format or throw exception
        // new referer is old url
        $referer = $this->baseUri.'/CGI/Java/Serviceability?adapter=install_rootcert';
        $url = $this->baseUri.'/CGI/Java/Serviceability?adapter=upload_rootca';
        // build our curl file object
        $uploadFileType = 'application/x-pem';
        $uploadFileName = 'radiusroot.cer';
        $uploadFile = curl_file_create($rootCaFilePath, $uploadFileType, $uploadFileName);
        // calculate the posted body
        $body = [
            'CSRFToken'  => $this->csrfToken,
            'rootca'     => $uploadFile,
        ];
        // post the new certificate
        $html = $this->curler->post($url, $referer, $body);

        return $html;
    }

    protected function updateCSRFToken($html)
    {
        // parse out the csrf token from the form
        $regex = '/CSRFToken" value="(.+==)/';
        if (preg_match($regex, $html, $hits)) {
            $this->csrfToken = $hits[1];
        } else {
            throw new \Exception('CSRF Token Exception, could not find CSRF token in '.$html);
        }

        return true;
    }

    public function getDeviceStatisticsPortNetwork()
    {
        // get the certificates list page
        $referer = $this->baseUri.'/CGI/Java/Serviceability?adapter=device.statistics.device';
        $url = $this->baseUri.'/CGI/Java/Serviceability?adapter=device.statistics.port.network';
        //echo 'Getting url '.$url.PHP_EOL;
        $html = $this->curler->get($url, $referer);

        return $html;
    }

    public function getNetworkInfo()
    {
        $html = $this->getDeviceStatisticsPortNetwork();
        //echo $html . PHP_EOL;

        $fields = ['CDP Neighbor device ID',
                    'CDP Neighbor port',
                    'CDP Neighbor IP address',
                    'LLDP Neighbor device ID',
                    'LLDP Neighbor IP address',
                    'LLDP Neighbor port',
                    'Port information',
                    ];

        $results = [];
        foreach ($fields as $field) {
            $results[$field] = $this->parseHTMLFieldFromTable($html, $field);
        }

        return $results;
    }

    protected function parseHTMLFieldFromTable($html, $fieldname)
    {
        $regex = '/'.$fieldname.'<\/B><\/TD><td width=20><\/TD><TD><B>(.+?)<\/B><\/TD><\/TR>/';

        if (! preg_match($regex, $html, $hits)) {
            throw new \Exception('Could not parse '.$fieldname.' in HTML');
        }
        if (strpos($hits[1], '>') === false) {
            return html_entity_decode($hits[1]);
        }
    }
}
