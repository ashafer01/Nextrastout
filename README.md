# Nextrastout

* `php Nextrastout.php` starts primary functionality
* `php Logger.php` appends the IRC log
* Make `web/` web accessible at `/nes`
    * Configure `https://example.net/nes/TwilioReceiver.php` as a Twilio messaging request URI
    * `/nes/QuoteViewer.php` and `/nes/AllKarma.php` are simple UI's for viewing data much too large for an IRC message

There is a `config/private.ini` for config options that can't be comitted publicly. Config keys in this file are listed
below. You can keep them in private.ini or put them in the main config; the two files are merged into one object.

    admins = []
    wiki_url =
    web_channel = 
    
    banned_users = []
    cooldown_users = []
    
    sms.default_channel =
    
    twilio.account_sid =
    twilio.auth_token =
    twilio.phone_number =
    
    bitly.username =
    bitly.token =
    bitly.api_key =
    
    quotes.admins = []
    
    nickserv_passwords.Nextrastout =
    
    google.api_key =
