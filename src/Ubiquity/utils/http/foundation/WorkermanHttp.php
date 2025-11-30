<?php
namespace Ubiquity\utils\http\foundation;

use Workerman\Protocols\Http;
use Workerman\Protocols\HttpCache;

/**
 * Http instance for Workerman.
 * Ubiquity\utils\http\foundation$WorkermanHttp
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.1
 */
class WorkermanHttp extends AbstractHttp {

	private $headers = [];

	private $responseCode = 200;

	private $datas;

	public function getAllHeaders() {
		return $this->headers;
	}

	public function setDatas($datas) {
		return $this->datas;
	}

	public function header($key, $value, bool $replace = true, int $http_response_code = 0) {
		$this->headers[$key] = $value;
		if ($http_response_code != 0) {
			$this->responseCode = $http_response_code;
		}
		Http::header($key . ':' . $value, $replace, $http_response_code);
	}

	/**
	 *
	 * @return int
	 */
	public function getResponseCode() {
		return $this->responseCode;
	}

	/**
	 *
	 * @param int $responseCode
	 */
	public function setResponseCode($responseCode) {
		if ($responseCode != null) {
			$this->responseCode = $responseCode;
			if (\PHP_SAPI != 'cli') {
				return \http_response_code($responseCode);
			}
			Http::responseCode($responseCode);
			return $responseCode;
		}
		return false;
	}

	public function headersSent(?string &$file = null, ?int &$line = null) {
		return \headers_sent($file, $line);
	}

	public function getInput() {
		return $this->datas;
	}
}

