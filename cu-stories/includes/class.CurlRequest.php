<?php

class CurlRequest {
	protected $_useragent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1';
	protected $_url;
	protected $_timeout;
	protected $_post;
	protected $_postFields;
	protected $_referer = "";
	protected $_session;
	protected $_webpage;
	protected $_includeHeader;
	protected $_httpHeaders = array ();
	protected $_status;
	protected $_error;
	protected $_binaryTransfer;
	protected $_customRequest;

	public $authentication = 0;
	public $auth_name = '';
	public $auth_pass = '';

	public function setCustomRequest(){
		$this->_customRequest = true;	
	}
	
	public function useAuth($use) {
		$this->authentication = 0;
		if ($use == true)
			$this->authentication = 1;
	}

	public function setName($name) {
		$this->auth_name = $name;
	}

	public function setPass($pass) {
		$this->auth_pass = $pass;
	}

	public function __construct($url = "", $timeOut = 60, $binaryTransfer = false, $includeHeader = false) {
		$this->_url = $url;
		$this->_timeout = $timeOut;

		$this->_includeHeader = $includeHeader;
		$this->_binaryTransfer = $binaryTransfer;
	}

	public function setReferer($referer) {
		$this->_referer = $referer;
	}

	public function setPost($postFields) {
		$this->_post = true;
		$this->_postFields = $postFields;
	}

	public function setUserAgent($userAgent) {
		$this->_useragent = $userAgent;
	}
	
	public function setHttpHeaders($arrHttpHeaders) {
		$this->_httpHeaders = $arrHttpHeaders;
	}
	
	public function createCurl($url = 'nul') {
		if ($url != 'nul') {
			$this->_url = $url;
		}

		$s = curl_init ();

		curl_setopt ( $s, CURLOPT_URL, $this->_url );
		curl_setopt ( $s, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt ( $s, CURLOPT_HTTPHEADER, $this->_httpHeaders );
		curl_setopt ( $s, CURLOPT_TIMEOUT, $this->_timeout );

		curl_setopt ( $s, CURLOPT_RETURNTRANSFER, true );

		if ($this->authentication == 1) {
			curl_setopt ( $s, CURLOPT_USERPWD, $this->auth_name . ':' . $this->auth_pass );
		}

		if ($this->_post) {
			curl_setopt ( $s, CURLOPT_POST, true );
			curl_setopt ( $s, CURLOPT_POSTFIELDS, $this->_postFields );
		}

		if ($this->_includeHeader) {
			curl_setopt ( $s, CURLOPT_HEADER, true );
		}

		if($this->_customRequest){
			curl_setopt ( $s, CURLOPT_CUSTOMREQUEST, "OPTIONS" );
		}
		
		curl_setopt ( $s, CURLOPT_USERAGENT, $this->_useragent );
		curl_setopt ( $s, CURLOPT_REFERER, $this->_referer );

		$this->_webpage = curl_exec ( $s );
		$this->_status = curl_getinfo ( $s, CURLINFO_HTTP_CODE );
		$this->_error = curl_errno( $s ) . " - " . curl_error( $s );
		curl_close ( $s );
	}

	public function getHttpStatus() {
		return $this->_status;
	}

	public function getError() {
		return $this->_error;
	}

	public function getContent() {
		return $this->_webpage;
	}
	
	public function getHttpHeaders() {
		return $this->_httpHeaders;
	}
}

$CurlRequest = new CurlRequest ();

?>