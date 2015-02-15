<?php

class pseudoclient {
	private $params;
	private $channels;
	public function __construct($params) {
		if (is_object($params)) {
			$params = (array) $params;
		}
		$this->channels = array();
		foreach ($params['channels'] as $chan) {
			if ($chan == null)
				continue;
			$this->channels[] = $chan;
		}
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
		$serv = ExtraServ::$hostname;
		$ts = time();
		if (strlen($this->user) > 9) {
			$this->user = substr($this->user, 0, 9);
		}
		uplink::send("NICK {$this->nick} 1 $ts {$this->mode} {$this->user} {$this->host} $serv 0 :{$this->name}");
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

	public function join($channel) {
		ExtraServ::sjoin($this->nick, $channel);
		if (!in_array($channel, $this->channels)) {
			$this->channels[] = $channel;
		}
	}

	public function kill($nick, $reason = null) {
		$serv = ExtraServ::$hostname;
		ExtraServ::usend($this->nick, "KILL $nick :$serv!{$this->host}!{$this->user}!{$this->nick} <$reason>");
		log::info('Deleting nick for KILL');
		f::delete_nick($nick);
	}

	public function quit($reason) {
		ExtraServ::usend($this->nick, "QUIT :$reason");
	}

	public function del_channel($channel) {
		$this->channels = array_diff($this->channels, array($channel));
	}
}
