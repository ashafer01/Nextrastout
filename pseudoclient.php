<?php

class pseudoclient {
	private $params;
	private $channels;
	public function __construct($params) {
		$this->channels = $params['channels'];
		unset($params['channels']);
		$this->params = $params;
	}

	public function __get($key) {
		return $this->params[$key];
	}

	private function __set($key, $value) {
		$this->params[$key] = $value;
	}

	public function init() {
		$host = ExtraServ::$hostname;
		$ts = time();
		uplink::send("NICK {$this->nick} 1 $ts {$this->mode} {$this->user} $host $host 0 :{$this->name}");
		foreach ($this->channels as $chan) {
			$this->join($chan);
		}
	}

	public function say($to, $message) {
		ExtraServ::usend($this->nick, "PRIVMSG $to :$message");
	}

	public function join($channel) {
		ExtraServ::sjoin($this->nick, $channel);
		if (!in_array($channel, $this->channels)) {
			$this->channels[] = $channel;
		}
	}

	public function del_channel($channel) {
		$this->channels = array_diff($this->channels, array($channel));
	}
}
