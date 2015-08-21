<?php
require_once 'functions.php';

class client {
	private $params;
	private $channels;
	public function __construct($params) {
		if (is_object($params)) {
			$params = (array) $params;
		}
		$this->channels = array();
		unset($params['channels']);
		$this->params = $params;
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

		$this->sent_nickserv_ident = false;
		if (isset(ExtraServ::$conf->nickserv_passwords->{$this->nick})) {
			$this->say('NickServ', 'IDENTIFY ' . ExtraServ::$conf->nickserv_passwords->{$this->nick});
			$this->sent_nickserv_ident = true;
		}

		var_dump($this);

		foreach ($this->channels as $chan) {
			$this->join($chan);
		}
	}

	public function send($line) {
		ExtraServ::usend($this->nick, $line);
	}

	public function say($to, $message) {
		ExtraServ::usend($this->nick, "PRIVMSG $to :$message");
	}

	public function notice($to, $message) {
		ExtraServ::usend($this->nick, "NOTICE $to :$message");
	}

	public function update_conf_channels() {
		$conf_channels = config::channels();
		foreach ($conf_channels as $channel) {
			$this->join($channel);
		}
		foreach ($this->channels as $channel) {
			if (!in_array($channel, $conf_channels)) {
				$this->part($channel);
			}
		}
	}

	public function join($channel) {
		if (!in_array($channel, $this->channels)) {
			log::debug("Joining $channel");
			ExtraServ::sjoin($this->nick, $channel);
			$this->channels[] = $channel;
			if ($this->sent_nickserv_ident === true) {
				$this->say('ChanServ', "OP $channel");
			} else {
				log::debug("Not sending OP request for $channel");
			}
		} else {
			log::debug("Already joined to $channel");
		}
	}

	public function part($channel, $reason = 'By admin request') {
		if (($key = array_search($channel, $this->channels) !== false)) {
			log::debug("Parting channel $channel");
			$this->send("PART $channel :$reason");
			unset($this->channels[$key]);
		} else {
			log::debug("Not joined to $channel");
		}
	}

	public function quit($reason) {
		ExtraServ::usend($this->nick, "QUIT :$reason");
	}

	public function del_channel($channel) {
		$this->channels = array_diff($this->channels, array($channel));
	}

	public function del_all_channels() {
		$this->channels = array();
	}
}
