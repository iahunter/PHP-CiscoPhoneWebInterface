<?php

namespace Metaclassing\CiscoPhoneWebInterface;

class Base
{
    // our curler helper
    protected $curler = null;
    // the IP address (or hostname i guess) of the phone
    protected $phone = '';
    protected $port = 80;
    protected $baseUri = '';
    protected $csrfToken = '';

    public function __construct($phone, $port = 80)
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

    public function getDeviceInfoPage()
    {
        // get the certificates list page
        $referer = $this->baseUri.'/CGI/Java/Serviceability?adapter=device.statistics.device';
        $url = $this->baseUri.'/CGI/Java/Serviceability?adapter=device.statistics.device';
        //echo 'Getting url '.$url.PHP_EOL;
        $html = $this->curler->get($url, $referer);

        return $html;
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
