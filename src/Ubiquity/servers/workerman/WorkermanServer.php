<?php
namespace Ubiquity\servers\workerman;

use Workerman\Protocols\Http;
use Workerman\Protocols\HttpCache;
use Workerman\Worker;
use Ubiquity\utils\http\foundation\WorkermanHttp;

class WorkermanServer {

	/**
	 * @var \Workerman\Worker
	 */
	private $server;

	private $httpInstance;

	private $config;

	private $basedir;

	private $options;
	
	private $events=[];
	
	private $wCount;

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
	
	private function addEvents($http){
		foreach ($this->events as $event=>$callback) {
			$http->on($event,$callback);
		}
	}

	public function init($config, $basedir) {
		$this->config = $config;
		$this->basedir = $basedir;
		$this->httpInstance=new WorkermanHttp();
		\Ubiquity\controllers\Startup::init($config);
	}

	/**
	 * Get swoole configuration option value.
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

	}

	public function run($host, $port, $options = []) {
		$this->server=new Worker("http://$host:$port",$options);
		$this->server->count=$this->wCount??4;
		$this->server->onMessage =function($connection,$datas){
			$this->handle($connection,$datas);
		};
		Worker::runAll();
	}
	

	protected function handle(ConnectionInterface $connection,$datas) {
		$request->get['c'] = '';
		$uri = \ltrim(\urldecode(\parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
		if ($uri == null || ! \file_exists($this->basedir . '/../' . $uri)) {
			$request->get['c'] = $uri;
		} else {
			Http::header('Content-Type', HttpCache::$header['accept'] ?? 'text/html; charset=utf-8');
			$connection->send(\file_get_contents($this->basedir . '/../' . $uri));
			return;
		}

		$this->httpInstance->setDatas($datas);
		\ob_start();
		\Ubiquity\controllers\Startup::setHttpInstance($this->httpInstance);
		\Ubiquity\controllers\Startup::forward($request->get['c']);
		$connection->send(\ob_get_clean());
	}
	/**
	 * Sets the worker count
	 * @param int $wCount
	 */
	public function setWCount($wCount) {
		$this->wCount = $wCount;
	}
	
	public function setDefaultCount(){
		$this->wCount= (int) \shell_exec('nproc') ??4;
	}

}

