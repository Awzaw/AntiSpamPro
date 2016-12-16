<?php

namespace awzaw\antispampro;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\utils\TextFormat;
use pocketmine\Server;
use awzaw\antispampro\ProfanityFilter;

class AntiSpamPro extends PluginBase implements CommandExecutor, Listener {

    private $players = [];
    public $profanityfilter;

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        if ($this->getConfig()->get("antiswearwords")) {
            $this->profanityfilter = new ProfanityFilter($this);
            $this->getLogger()->info(TEXTFORMAT::GREEN . "AntiSpamPro Swear Filter Enabled");
        }
    }

    public function onChat(PlayerChatEvent $e) {

        if (isset($this->players[spl_object_hash($e->getPlayer())]) && (time() - $this->players[spl_object_hash($e->getPlayer())]["time"] <= intval($this->getConfig()->get("delay")))) {
            $this->players[spl_object_hash($e->getPlayer())]["time"] = time();
            $this->players[spl_object_hash($e->getPlayer())]["warnings"] = $this->players[spl_object_hash($e->getPlayer())]["warnings"] + 1;

            if ($this->players[spl_object_hash($e->getPlayer())]["warnings"] === $this->getConfig()->get("warnings")) {
                $e->getPlayer()->sendMessage(TEXTFORMAT::RED . $this->getConfig()->get("lastwarning"));
                $e->setCancelled();
                return;
            }
            if ($this->players[spl_object_hash($e->getPlayer())]["warnings"] > $this->getConfig()->get("warnings")) {
                $e->setCancelled();

                $sender = $e->getPlayer();
                switch (strtolower($this->getConfig()->get("action"))) {
                    case "kick":
                        $sender->kick($this->getConfig()->get("kickmessage"));
                        break;

                    case "ban":
                        $sender->setBanned(true);
                        break;

                    case "banip":

                        $this->getServer()->getIPBans()->addBan($sender->getAddress(), $this->getConfig()->get("banmessage"), null, $sender->getName());
                        $this->getServer()->getNetwork()->blockAddress($sender->getAddress(), -1);
                        $sender->setBanned(true);

                        break;

                    case "bancid":

                        if (method_exists($this->getServer(), "getCIDBans")) {
                            $this->getServer()->getCIDBans()->addBan($sender->getClientId(), $this->getConfig()->get("banmessage"), null, $sender->getName());
                            $this->getServer()->getIPBans()->addBan($sender->getAddress(), $this->getConfig()->get("banmessage"), null, $sender->getName());
                            $this->getServer()->getNetwork()->blockAddress($sender->getAddress(), -1);
                            $sender->setBanned(true);
                        } else {

                            $this->getServer()->getIPBans()->addBan($sender->getAddress(), $this->getConfig()->get("banmessage"), null, $sender->getName());
                            $this->getServer()->getNetwork()->blockAddress($sender->getAddress(), -1);
                            $sender->setBanned(true);
                        }

                    default:
                        break;
                }

                return;
            }
            $e->getPlayer()->sendMessage(TEXTFORMAT::RED . $this->getConfig()->get("message1"));
            $e->getPlayer()->sendMessage(TEXTFORMAT::GREEN . $this->getConfig()->get("message2"));
            $e->setCancelled();
        } else {
            $this->players[spl_object_hash($e->getPlayer())] = array("time" => time(), "warnings" => 0);
            if ($this->getConfig()->get("antiswearwords") && $this->profanityfilter->hasProfanity($e->getMessage())) {
                $e->getPlayer()->sendMessage(TEXTFORMAT::RED . "No Swear Words Allowed");
                $e->setCancelled(true);
                return true;
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {

        if (!isset($args[0])) {
            if ($sender instanceof Player) {
                $sender->getPlayer()->sendMessage(TEXTFORMAT::GREEN . "Banmode: " . $this->getConfig()->get("action") . "  " . "Delay: " . $this->getConfig()->get("delay") . " seconds");
            } else {
                $this->getLogger()->info("Banmode: " . $this->getConfig()->get("action") . "  " . "Delay: " . $this->getConfig()->get("delay") . " seconds");
            }
            return true;
        }

        //if ($sender instanceof Player && !$sender->isOp()) return true;

        switch (strtolower($args[0])) {

            case "help":

                if ($sender instanceof Player) {
                    $sender->getPlayer()->sendMessage(TEXTFORMAT::YELLOW . $this->getConfig()->get("help1"));
                    $sender->getPlayer()->sendMessage(TEXTFORMAT::YELLOW . $this->getConfig()->get("help2"));
                    $sender->getPlayer()->sendMessage(TEXTFORMAT::YELLOW . $this->getConfig()->get("help3"));
                    $sender->getPlayer()->sendMessage(TEXTFORMAT::YELLOW . $this->getConfig()->get("help4"));
                } else {
                    $this->getLogger()->info($this->getConfig()->get("help1"));
                    $this->getLogger()->info($this->getConfig()->get("help2"));
                    $this->getLogger()->info($this->getConfig()->get("help3"));
                    $this->getLogger()->info($this->getConfig()->get("help4"));
                }

                return true;

            case "banip":
            case "ban":
            case "kick":
            case "bancid":
                $this->getConfig()->set("action", strtolower($args[0]));
                $this->getConfig()->save();

                if ($sender instanceof Player) {
                    $sender->getPlayer()->sendMessage(TEXTFORMAT::GREEN . $this->getConfig()->get("set" . strtolower($args[0]) . "kickmessage"));
                } else {
                    $this->getLogger()->info($this->getConfig()->get("set" . strtolower($args[0]) . "message"));
                }

                return true;


            case "set":
                if (isset($args[1]) && is_numeric($args[1]) && $args[1] <= 3 && $args[1] > 0) {
                    $this->getConfig()->set("delay", $args[1]);
                    $this->getConfig()->save();

                    if ($sender instanceof Player) {
                        $sender->getPlayer()->sendMessage(TEXTFORMAT::GREEN . $this->getConfig()->get("setdelay"));
                    } else {
                        $this->getLogger()->info($this->getConfig()->get("setdelay"));
                    }
                } else {

                    if ($sender instanceof Player) {
                        $sender->getPlayer()->sendMessage(TEXTFORMAT::RED . $this->getConfig()->get("invaliddelay"));
                    } else {
                        $this->getLogger()->info($this->getConfig()->get("invaliddelay"));
                    }
                }


                return true;

            default:

                break;
        }
    }

    public function onQuit(PlayerQuitEvent $e) {
        if (isset($this->players[spl_object_hash($e->getPlayer())])) {
            unset($this->players[spl_object_hash($e->getPlayer())]);
        }
    }

    public function getProfanityFilter() {
        return $this->profanityfilter;
    }

}
