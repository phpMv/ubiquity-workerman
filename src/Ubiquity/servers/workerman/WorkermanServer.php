<?php
namespace Ubiquity\servers\workerman;

use Ubiquity\utils\base\MimeType;
use Workerman\Protocols\Http;
use Workerman\Protocols\HttpCache;
use Workerman\Worker;
use Workerman\Connection\ConnectionInterface;
use Ubiquity\utils\http\foundation\WorkermanHttp;

/**
 * Ubiquity\servers\workerman$WorkermanServer
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.4
 */
class WorkermanServer {

	/**
	 *
	 * @var \Workerman\Worker
	 */
	private $server;

	private $httpInstance;

	private $config;

	private $basedir;

	private $options;

	private $wCount;

	public $onWorkerStart;

	/**
	 *
	 * @return int
	 */
	private function getPid(): int {
		$file = $this->getPidFile();
		if (! \file_exists($file)) {
			return 0;
		}
		$pid = (int) \file_get_contents($file);
		if (! $pid) {
			$this->removePidFile();
			return 0;
		}
		return $pid;
	}

	/**
	 * Get pid file.
	 *
	 * @return string
	 */
	private function getPidFile(): string {
		return Worker::$pidfile;
	}

	/**
	 * Remove the pid file.
	 */
	private function removePidFile(): void {
		$file = $this->getPidFile();
		if (\file_exists($file)) {
			\unlink($file);
		}
	}

	/**
	 * Configure the created server.
	 */
	private function configure($http) {
		$http->set($this->options);
	}

	public function init($config, $basedir) {
		$this->config = $config;
		$this->basedir = $basedir;
		$this->httpInstance = new WorkermanHttp();
		\Ubiquity\controllers\Startup::init($config);
		\Ubiquity\controllers\Startup::setHttpInstance($this->httpInstance);
	}

	/**
	 * Get Workerman configuration option value.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function getOption(string $key) {
		$option = $this->options[$key];
		if (! $option) {
			throw new \InvalidArgumentException(sprintf('Parameter not found: %s', $key));
		}
		return $option;
	}

	public function setOptions($options = []) {
		$this->options = $options;
	}

	public function run($host, $port, $options = []) {
		$this->setOptions($options);
		$this->server = new Worker("http://$host:$port", $this->options);
		$this->server->count = $this->wCount ?? 4;
		if (isset($this->onWorkerStart)) {
			$this->server->onWorkerStart = $this->onWorkerStart;
		}
		$this->server->onMessage = function ($connection, $datas) {
			return $this->handle($connection, $datas);
		};
		Worker::runAll();
	}

	protected function handle(ConnectionInterface $connection, $datas) {
		Http::header('Date: ' . \gmdate('D, d M Y H:i:s') . ' GMT');
		$_GET['c'] = '';
		$uriInfos = \Ubiquity\utils\http\URequest::parseURI($_SERVER['REQUEST_URI'], $this->basedir);
		$uri = $uriInfos['uri'];
		if ($uriInfos['isAction']) {
			$_GET['c'] = $uri;
		} else {
			if ($uriInfos['file']) {
				$file=$this->basedir .'/'. $uri;
				$mime = MimeType::getFileMimeType($file);
				Http::header('Content-Type: ' . $mime . '; charset=utf-8', true);
				return $connection->send(\file_get_contents($file));
			} else {
				Http::header('Content-Type: text/plain; charset=utf-8', true, 404);
				return $connection->send($uri . ' not found!');
			}
			return;
		}

		$this->httpInstance->setDatas($datas);
		\ob_start();
		\Ubiquity\controllers\StartupAsync::forward($_GET['c']);
		return $connection->send(\ob_get_clean());
	}

	/**
	 * Sets the worker count
	 *
	 * @param int $wCount
	 */
	public function setWCount($wCount) {
		$this->wCount = $wCount;
	}

	public function setDefaultCount($multi = 1) {
		$this->wCount = ((int) \shell_exec('nproc') ?? 4) * $multi;
	}

	public function daemonize($value = true) {
		Worker::$daemonize = $value;
	}
}

