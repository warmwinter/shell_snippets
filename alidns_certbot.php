<?php

/**
 * Get your API key from https://ram.console.aliyun.com/#/user/list (RECOMMAND)
 * OR from https://usercenter.console.aliyun.com/#/manage/ak (NOT RECOMMAND)
 */
class AliDns
{
	// Exit code
	const EXIT_SUCCESS = 0;

	// PARAMS
	const ALI_NDS_URI = 'https://alidns.aliyuncs.com/';
	
	protected $accessKeyId = '';
	protected $accessKeySecret = '';
	protected $domain = '';
	protected $newDNSValue = '';

	protected $debug = true;

	public function __construct()
	{
		//
	}

	public function setDomain($domain)
	{
		$matches = [];

		if (preg_match_all("/.*\.(\S+?\.(?:com|net|gov|org)\.cn)/is", $domain, $matches)) {
			$this->domain = $matches[1][0];
		} elseif (preg_match_all("/.*\.(\S+?\.\w+)/is", $domain, $matches)) {
			$this->domain = $matches[1][0];
		}  elseif (preg_match("/^\S+?\.\w+$/is", $domain)) {
            $this->domain = $domain;
        } else {
			return false;
		}

		return true;
	}

    /*
     * Set access info
     */
	public function setAccessInfo($keyId, $secret)
	{
		$this->accessKeyId = $keyId;
		$this->accessKeySecret = $secret;
	}

	/*
	 * new DNS value
	 */
	public function setNewValue($value)
	{
		$this->newDNSValue = $value;
	}

    /**
     * Create DNS record
     */
	public function createDnsRecord()
	{
		var_dump($this-domain);
		if (empty($this-domain)) {
			return false;
		}

		$existRecords = $this->getDomainRecordList();

		if ($existRecords) {
			$this->removeDnsRecord($existRecords);
		}

		$addRet = $this->addNewDNSRecore('_acme-challenge', $this->newDNSValue);

		// var_dump($this->getDomainRecordList());

		return $addRet;
	}

	/**
	 * get DNS list for the special domain
	 * @see https://help.aliyun.com/document_detail/29776.html
	 */
	protected function getDomainRecordList()
	{
		$selfParams = [
			'Action' => 'DescribeDomainRecords',
			'DomainName' => $this->domain,
			'PageNumber' => 1,
			'PageSize' => 100,
			'RRKeyWord' => '%_acme-challenge',
			// https://help.aliyun.com/document_detail/29805.html
			'TypeKeyWord' => 'TXT',
		];

		$signature = $this->getSinagure($selfParams);

		$post = array_merge($this->getCommonParams(), $selfParams, ['Signature' => $signature]);

		$ret = $this->httpClient(self::ALI_NDS_URI .'?' .http_build_query($post));
		
		$jsonData = $this->dealHttpResponse($ret);

		if ($jsonData && isset($jsonData['DomainRecords'])
		     && isset($jsonData['DomainRecords']['Record'])
		) {
			$jsonData = $jsonData['DomainRecords']['Record'];
		} else {
			$jsonData = [];
		}

		return $jsonData;
	}

	/*
	 * Remove all match DNS record
	 * @see https://help.aliyun.com/document_detail/29773.html
	 */
	protected function removeDnsRecord(array $records)
	{
		if (empty($records)) {
			return false;
		}

		foreach ($records as $key => $value) {
			if (!isset($value['RecordId']) || empty($value['RecordId'])) {
				continue;
			}

			$selfParams = [
				'Action' => 'DeleteDomainRecord',
				'RecordId' => $value['RecordId'],
			];

			$success = false;
			$cnt = 0;

			do {
				$signature = $this->getSinagure($selfParams);

				$post = array_merge($this->getCommonParams(), $selfParams, ['Signature' => $signature]);

				$ret = $this->httpClient(self::ALI_NDS_URI .'?' .http_build_query($post));
				
				$jsonData = $this->dealHttpResponse($ret);

				if ($jsonData && isset($jsonData['RecordId'])) {
					$success = true;
				}
				$cnt++;
				sleep(1);
			} while ($success != true && $cnt < 3);
		}

		return $success;
	}

	/*
	 * Add new DNS
	 * @see https://help.aliyun.com/document_detail/29772.html
	 */
    protected function addNewDNSRecore($name, $value)
    {
    	$selfParams = [
			'Action' => 'AddDomainRecord',
			'DomainName' => $this->domain,
			'RR' => $name,
			// https://help.aliyun.com/document_detail/29805.html
			'Type' => 'TXT',
			'Value' => $value,
		];

		$success = false;
		$cnt = 0;

		do {

			$signature = $this->getSinagure($selfParams);

			$post = array_merge($this->getCommonParams(), $selfParams, ['Signature' => $signature]);

			$ret = $this->httpClient(self::ALI_NDS_URI .'?' .http_build_query($post));
			
			$jsonData = $this->dealHttpResponse($ret);

			if ($jsonData && isset($jsonData['RecordId'])) {
				$success = true;
			}

			$cnt++;
			sleep(1);
		} while ($success != true && $cnt < 3);

		return $success;
    }

    /*
     * Decode json data
     */
	protected function dealHttpResponse(array $response)
	{
		if (empty($response)) {
			return [];
		}

		list($httpStatus, $content) = $response;

		if ($this->debug == true && $httpStatus != '200') {
			var_dump(json_decode($content, true));
			return [];
		}

		$decode = json_decode($content, true);

		if (!$decode) {
			return false;
		}

		return $decode;
	}

	/**
	 * Common parameters 
	 * @see https://help.aliyun.com/document_detail/29745.html
	 */
	protected function getCommonParams($force = false)
	{
		static $commonParams = null;

		if ($commonParams !== null && $force != true) {
			return $commonParams;
		}


		$commonParams = [
			'Format' => 'json',
			'Version' => '2015-01-09',
			'AccessKeyId' => $this->accessKeyId,
			'SignatureMethod' => 'HMAC-SHA1',
			'Timestamp' => gmdate("Y-m-d\TH:i:s\Z", time()),
			'SignatureVersion' => '1.0',
			'SignatureNonce' => substr(md5(microtime(true)), 1, rand(7,15)),
		];

		return $commonParams;
	}

	/**
	 * Signature method 
	 * Need add Signature Key to Array
	 * @see https://help.aliyun.com/document_detail/29747.html
	 */
	protected function getSinagure($urlParams = [])
	{
		$allParams = array_merge($this->getCommonParams(true), $urlParams);
		ksort($allParams);
		
		$canonicalizedStr = $this->CanonicalizedParams($allParams);
		// $signStr = 'GET&%2F&' .str_replace('/', '%2F', $canonicalizedStr);
		$signStr = 'GET&%2F&' . rawurlencode($canonicalizedStr);

		$sign = hash_hmac('sha1', $signStr, $this->accessKeySecret .'&', true); 

		return base64_encode($sign);
	}

	protected function CanonicalizedParams($params = [])
	{
		$str = '';
		foreach ($params as $key => $value) {
			$str .= $str ? '&' : '';
			$str .= rawurlencode($key) . '=' . rawurlencode($value); // = => %3D
		}

		return $str;
	}

	protected function httpClient($url, $post = [], $cookie = '', $timeout = 5)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		if($post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		if($cookie) {
			curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		}
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		$data = curl_exec($ch);
		$status = curl_getinfo($ch);
		$errno = curl_errno($ch);
		$err = curl_error($ch);
		curl_close($ch);
		if($errno) { // || $status['http_code'] != 200
			return [];
		} else {
			$GLOBALS['filesockheader'] = substr($data, 0, $status['header_size']);
			$data = substr($data, $status['header_size']);
			return [$status['http_code'], $data];
		}
	}
}

if (PHP_SAPI != 'cli') {
	exit(255);
}

if ($argc < 3) {
	exit(127);
}

$aliDns = new AliDns();
$aliDns->setDomain($argv[1]);
$aliDns->setAccessInfo('LTAIYpLRLKsPsI0l', 'tKVnZevhK4f9GBFzojZqfXxTUQSBMX');
$aliDns->setNewValue($argv[2]);
if ($aliDns->createDnsRecord()) {
	exit(0);
} else {
	exit(255);
}

