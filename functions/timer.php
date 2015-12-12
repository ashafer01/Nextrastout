//<?php

Nextrastout::dbconnect();
f::ALIAS_INIT();

$imap_check = 0;
$imap_check_interval = 60;
$imap_max_attachments = 3;

$gallery_file_base = Nextrastout::$conf->gallery->file_base;
$gallery_http_base = Nextrastout::$conf->gallery->http_base;
$gallery_upload_address = strtolower(Nextrastout::$conf->gallery->upload_address);
$gallery_tmp = Nextrastout::$conf->gallery->tmp;

while (true) {
	# Check for proc reload
	if (proc::reload_needed()) {
		log::notice('Reloading timer proc');
		proc::disable_reload();
		return proc::PROC_RERUN;
	}

	# Check for conf reload
	if (config::reload_needed()) {
		log::notice('Reloading conf');
		config::reload_all();
	}

	# Check for new SMS
	$q = Nextrastout::$db->pg_query("SELECT sms.message_sid, sms.from_number, sms.message, sms.dest_chan, phonebook.nick FROM sms FULL JOIN phonebook ON phonebook.phone_number=sms.from_number WHERE posted IS FALSE ORDER BY uts",
		'check for new sms', false);
	while ($qr = db::fetch_assoc($q)) {
		if ($qr['nick'] == null) {
			log::debug('Got SMS from unregistered number');
			$cid = f::everyoneapi($qr['from_number'], array('name'));
			if (in_array('name', $cid->missed)) {
				$from = $qr['from_number'];
			} else {
				$from = "{$cid->data->name} ({$qr['from_number']})";
			}
		} else {
			$from = $qr['nick'];
			log::debug("Got SMS from registered number ($from)");
		}
		Nextrastout::$bot_handle->say($qr['dest_chan'], "<$from> {$qr['message']}");
		$u = Nextrastout::$db->pg_query("UPDATE sms SET posted=TRUE WHERE message_sid='{$qr['message_sid']}'",
			'mark sms posted', false);
		if ($u === false) {
			log::fatal('Failed to mark SMS as posted, exiting timer proc to prevent spam');
			exit(101);
		}
	}

	# Check for email pictures
	if ($imap_check > 0) {
		$imap_check--;
	} else {
		log::trace('Checking email...');
		$imap_check = $imap_check_interval;

		$mbox = imap_open(Nextrastout::$conf->imap->mailbox, Nextrastout::$conf->imap->user, get_password('imap'));
		if ($mbox === false) {
			log::error('Failed to open IMAP connection');
		} else {
			$num_messages = imap_num_msg($mbox);
			if ($num_messages > 0) {
				log::debug("$num_messages messages in mailbox");
			} else {
				log::trace('Mailbox empty');
			}
			$already_posted = 0;
			for ($i = 1; $i <= $num_messages; $i++) {
				$attach_links = array();
				$msg = imap_fetchstructure($mbox, $i);
				$hdr = imap_headerinfo($mbox, $i);
				$message_id = dbescape($hdr->message_id);

				$q = Nextrastout::$db->pg_query("SELECT 1 FROM processed_email WHERE message_id='$message_id'", 'check already processed email', false);
				if (db::num_rows($q) > 0) {
					$already_posted++;
					continue;
				}

				$toaddr = "{$hdr->to[0]->mailbox}@{$hdr->to[0]->host}";
				if (strtolower($hdr->to[0]->mailbox) != $gallery_upload_address) {
					log::trace("Bad To address '$toaddr'");
					continue;
				}

				$fromaddr = "{$hdr->from[0]->mailbox}@{$hdr->from[0]->host}";
				$fromname = $hdr->from[0]->personal;

				$fromaddr = dbescape($fromaddr);
				$q = Nextrastout::$db->pg_query("SELECT nick FROM nick_email WHERE email='$fromaddr'");
				$from = "$fromname <$fromaddr>";
				$gallery_dir = 'unregistered';
				if ($q === false) {
					log::error('Failed to check for registered email address');
				} elseif (pg_num_rows($q) == 0) {
					log::debug("Email address '$fromaddr' is not registered");
				} else {
					$qr = pg_fetch_assoc($q);
					$from = $qr['nick'];
					$gallery_dir = $qr['nick'];
					log::info("Email address '$fromaddr' associated with nick '$from'");
				}

				$message = trim(imap_fetchbody($mbox, $i, '1.1'));

				if (property_exists($msg, 'parts')) {
					$parts = $msg->parts;
					$fpos = 2;
				} else {
					$parts = array($msg);
					$fpos = 1;
				}
				for ($j = 0; $j < count($parts); $j++) {
					$part = $parts[$j];

					# find attached images
					if ($part->type == 5) {
						log::debug("Found type 5 subtype {$part->subtype}");
						# check for number of attachments limit
						if (count($attach_links) >= $imap_max_attachments) {
							log::info("More than $imap_max_attachments images attached");
							$attach_links[] = "Limited to $imap_max_attachments pics";
							break;
						}

						# replace whitespace in filename
						$filename = preg_replace('/\s+/', '_', $part->dparameters[0]->value);

						# check file size <= 10MB
						if ($part->bytes > 10485760) {
							log::notice('Image over 10MB attached');
							$attach_links[] = "Over 10MB ($filename)";
							$fpos++;
							continue;
						}

						# get attachment
						$body = imap_fetchbody($mbox, $i, $fpos);
						switch ($part->type) {
							case 0:
							case 1:
								$data = imap_8bit($body);
								break;
							case 2:
								$data = imap_binary($body);
								break;
							case 3:
							case 5:
								$data = imap_base64($body);
								break;
							case 4:
								$data = imap_qprint($body);
								break;
							default:
								log::error("Unknown image encoding type {$part->type}");
								break;
						}
						file_put_contents($gallery_tmp, $data);

						# strip exif and upload to yakko
						try {
							$img = new Imagick($gallery_tmp);
							$img->stripImage();
							unlink($gallery_tmp);
							$ret = f::gallery_store($gallery_dir, $filename, $img->getImageBlob());
							log::debug("gallery_store returned: $ret");
							$img->clear();
							$img->destroy();
						} catch (ImagickException $e) {
							$attach_links[] = 'Bad image uploaded';
							log::error("Caught ImagickException");
						}

						# Generate output
						if ($ret !== false) {
							$ret = explode('/', $ret);
							$filename = array_pop($ret);
							$reallink = "$gallery_http_base/$gallery_dir/$filename";
							$link = f::shorten($reallink);
							if ($link === false) {
								log::error("shorten() failed");
								$link = $reallink;
							}
							$attach_links[] = $link;
							log::debug("Replying with new link $link => $gallery_http_base/$gallery_dir/$filename");
						} else {
							$attach_links[] = "Error uploading ($gallery_dir/$filename)";
							log::error("Error uploading $gallery_file_base/$gallery_dir/$filename ($ret)");
						}

						$fpos++;
					} else {
						log::trace("Found message part type {$part->type}");
					}
				} # end message parts loop

				if (count($attach_links) > 0) {
					$reply = "$from has uploaded: ";
					$reply .= implode(' | ', $attach_links);
					if ($message != null) {
						if (($eol = strpos($message, "\n")) !== false) {
							$message = substr($message, 0, $eol);
						} elseif (strlen($message) > 120) {
							$message = substr($message, 0, 120);
							$message .= '...';
						}
						$reply .= " | $message";
					}
					Nextrastout::$bot_handle->say(Nextrastout::$conf->gallery->channel, $reply);
				} else {
					log::debug("Message sent to $gallery_upload_address from $fromaddr without attachments");
				}

				$q = Nextrastout::$db->pg_query("INSERT INTO processed_email (message_id) VALUES ('$message_id')");
				imap_delete($mbox, $i);
			} # end inbox loop
			if ($already_posted > 0) {
				log::info("Already posted $already_posted messages");
			}
			imap_expunge($mbox);
			imap_close($mbox, CL_EXPUNGE);
		}
	}

	sleep(1);
}

log::debug('Reached end of timer proc');
