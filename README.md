# Nextrastout
To use: run `php Nextrastout.php` to start primary functionality

`php Logger.php` appends the IRC log

Make `TwilioReceiver.php` web accessible and then configure it as your Messaging request URL for one of your numbers.

There is a `config/private.ini` for config options that can't be comitted publicly. Config keys in this file are listed
below. You can keep them in private.ini or put them in the main config; the two files are merged into one object.

    admins = []
    wiki_url =
    
    banned_users = []
    cooldown_users = []
    
    sms.default_channel =
    
    twilio.account_sid =
    twilio.auth_token =
    twilio.phone_number =
    
    bitly.username =
    bitly.token =
    bitly.api_key =
    
    quotes.web_channel =
    quotes.admins = []
    
    nickserv_passwords.Nextrastout =
    
    google.api_key =
