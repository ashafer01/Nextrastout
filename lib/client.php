<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/uplink.php';
require_once __DIR__ . '/config.php';

class client {
	private $params;
	private $channels = array();
	private $joined = array();
	private $conf;
	public function __construct($params) {
		if (is_object($params)) {
			$params = (array) $params;
		}
		$this->channels = config::channels();
		$this->params = $params;
		$this->conf = config::get_instance();
	}

	public function __get($key) {
		return $this->params[$key];
	}

	public function __set($key, $value) {
		$this->params[$key] = $value;
	}

	public function init() {
		if (strlen($this->user) > 9) {
			$this->user = substr($this->user, 0, 9);
		}
		uplink::send("NICK {$this->nick}");
		uplink::send("USER {$this->user} dot dot :{$this->name}");

		if (isset($this->conf->nickserv_passwords->{$this->nick})) {
			$this->say('NickServ', 'IDENTIFY ' . $this->conf->nickserv_passwords->{$this->nick});
		}

		foreach ($this->channels as $chan) {
			$this->join($chan);
		}
	}

	public function say($to, $message) {
		uplink::send("PRIVMSG $to :$message");
	}

	public function notice($to, $message) {
		uplink::send("NOTICE $to :$message");
	}

	public function topic($channel, $topic) {
		uplink::send("TOPIC $channel :$topic");
	}

	public function update_conf_channels() {
		$this->channels = config::channels();
		foreach ($this->channels as $channel) {
			$this->join($channel);
		}
		foreach ($this->joined as $channel) {
			if (!in_array($channel, $this->channels)) {
				$this->part($channel);
			}
		}
	}

	public function join($channel) {
		if (in_array($channel, $this->joined)) {
			log::notice("Already joined to $channel, not sending JOIN");
		} else {
			log::trace("Adding $channel to joined channels");
			$this->joined[] = $channel;
			log::debug("Joining $channel");
			uplink::send("JOIN $channel");
		}
	}

	public function part($channel, $reason = 'By admin request') {
		if (($key = array_search($channel, $this->joined) !== false)) {
			log::debug("Parting channel $channel");
			uplink::send("PART $channel :$reason");
			unset($this->joined[$key]);
			if (($key = array_search($channel, $this->channels) !== false)) {
				log::info("Removing $channel from channels list");
				unset($this->channels[$key]);
			} else {
				log::trace("Channel $channel not in channels list");
			}
		} else {
			log::debug("Not joined to $channel, not sending PART");
		}
	}

	public function quit($reason) {
		uplink::send("QUIT :$reason");
	}

	public function del_channel($channel) {
		$this->channels = array_diff($this->channels, array($channel));
	}

	public function del_all_channels() {
		$this->channels = array();
	}
}
