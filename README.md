# AntiSpamPro

Antispam plugin with configurable delay, profanity filter (block swear words), automatic actions (kick, ban) and commands to change settings on the fly in console or in game
ASP 1.1 now also detects command spam.

/asp - display the current AntiSpamPro settings

/asp kick - kick spammers

/asp ban - ban spammers

/asp banip - banip and ban spammers

/asp bancid - ban, banip and bancid spammers (if available), 
 
asp set {1, 2 or 3} - change the allowed delay between chat to 1, 2 or 3 seconds


You need the permission asp to use any of the commands. There is no spam bypass permission.
The delay setting in config.yml can be set freely.

To configure other plugins to use the AntiSpamPro profanity filter, use this is onEnable():

`$this->antispampro = $this->getServer()->getPluginManager()->getPlugin("AntiSpamPro");`

`if (!$this->antispampro) {`

`$this->getLogger()->info("Unable to find AntiSpamPro");`

`}`


and then use this to check for swear words:


`if ($this->antispampro && $this->antispampro->getProfanityFilter()->hasProfanity($wordtocheck)) {.......}`



KUDOS and thanks to
https://github.com/mofodojodino/ProfanityFilter
and https://github.com/fastwebmedia/Profanity-Filter
