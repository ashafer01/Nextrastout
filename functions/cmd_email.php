//<?php

log::trace('entered f::cmd_email()');
list($_CMD, $_ARG, $_i) = $_ARGV;

$nick = dbescape($_i['hostmask']->nick);

$help = '!email <your email> : Associate an email address with your username | !email -v : View your currently stored addresses | !email -d : Delete all stored addresses | !email -d <email> : Delete a single stored address';

$args = explode(' ', $_ARG);
$arg0 = array_shift($args);
switch ($arg0) {
	case null:
		$say = $help;
		break;
	case '-v':
		$q = Nextrastout::$db->pg_query("SELECT email FROM nick_email WHERE nick='$nick'");
		if ($q === false) {
			$say = 'Query failed';
		} elseif (pg_num_rows($q) == 0) {
			$say = "No stored email addresses for $nick";
		} else {
			$emails = array();
			while ($qr = pg_fetch_assoc($q)) {
				$emails[] = $qr['email'];
			}
			$say = "$nick: " . implode(' | ', $emails);
		}
		break;
	case '-d':
		$email = array_shift($args);
		if ($email == null) {
			# delete all addresses
			$q = Nextrastout::$db->pg_query("DELETE FROM nick_email WHERE nick='$nick'");
			if ($q === false) {
				$say = 'Query failed';
			} else {
				$n = pg_affected_rows($q);
				if ($n == 1) {
					$addresses = 'address';
				} else {
					$addresses = 'addresses';
				}
				$say = "Deleted $n email $addresses for $nick";
			} 
		} elseif (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
			# delete one address
			$email = dbescape($email);
			$q = Nextrastout::$db->pg_query("DELETE FROM nick_email WHERE nick='$nick' AND email='$email'");
			if ($q === false) {
				$say = 'Query failed';
			} elseif (pg_affected_rows($q) == 0) {
				$say = "Email $email not associated with $nick";
			} else {
				$say = "Deleted $email for $nick";
			}
		} else {
			$say = "Invalid email address | $help";
		}
		break;
	default:
		if (filter_var($arg0, FILTER_VALIDATE_EMAIL) !== false) {
			# add new email
			$email = dbescape($arg0);
			$q = Nextrastout::$db->pg_query("SELECT nick FROM nick_email WHERE email='$email'");
			if ($q === false) {
				$say = 'Query failed';
			} elseif (pg_num_rows($q) == 0) {
				# actually add the new address
				$q = Nextrastout::$db->pg_query("INSERT INTO nick_email (nick,email) VALUES ('$nick','$email')");
				if ($q === false) {
					$say = "$nick: Failed to store new address";
				} else {
					$say = "$nick: Stored new email address";
				}
			} else {
				$qr = pg_fetch_assoc($q);
				$say = "$nick: Email address $email already associated with nick {$qr['nick']}";
			}
		} else {
			$say = "Invalid email address | $help";
		}
		break;
}

$_i['handle']->say($_i['reply_to'], $say);
