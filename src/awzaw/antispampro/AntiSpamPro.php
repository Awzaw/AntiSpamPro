<?php

namespace awzaw\antispampro;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

class AntiSpamPro extends PluginBase implements CommandExecutor, Listener {

    private $players = [];
    public $profanityfilter;

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        if ($this->getConfig()->get("antiswearwords") || $this->getConfig()->get("antirudenames")) {
            $this->saveResource("swearwords.yml", false);
            $this->profanityfilter = new ProfanityFilter($this);
            $this->getLogger()->info(TEXTFORMAT::GREEN . "AntiSpamPro Swear Filter Enabled");
        }
    }

    public function onChat(PlayerChatEvent $e) {
        if ($e->isCancelled() || ($player = $e->getPlayer())->isClosed() || $player->hasPermission("asp.bypass")) return;
        if (isset($this->players[spl_object_hash($player)]) && (time() - $this->players[spl_object_hash($player)]["time"] <= intval($this->getConfig()->get("delay")))) {
            $this->players[spl_object_hash($player)]["time"] = time();
            $this->players[spl_object_hash($player)]["warnings"] = $this->players[spl_object_hash($player)]["warnings"] + 1;

            if ($this->players[spl_object_hash($player)]["warnings"] === $this->getConfig()->get("warnings")) {
				$player->sendMessage(TEXTFORMAT::RED . $this->getConfig()->get("lastwarning"));
                $e->setCancelled();
                return;
            }
            if ($this->players[spl_object_hash($player)]["warnings"] > $this->getConfig()->get("warnings")) {
                $e->setCancelled();

                switch (strtolower($this->getConfig()->get("action"))) {
                    case "kick":
						$player->kick($this->getConfig()->get("kickmessage"));
                        break;

                    case "ban":
						$player->setBanned(true);
                        break;

                    case "banip":

                        $this->getServer()->getIPBans()->addBan($player->getAddress(), $this->getConfig()->get("banmessage"), null, $player->getName());
                        $this->getServer()->getNetwork()->blockAddress($player->getAddress(), -1);
						$player->setBanned(true);

                        break;

                    default:
                        break;
                }

                return;
            }
			$player->sendMessage(TEXTFORMAT::RED . $this->getConfig()->get("message1"));
			$player->sendMessage(TEXTFORMAT::GREEN . $this->getConfig()->get("message2"));
            $e->setCancelled();
        } else {
            $this->players[spl_object_hash($player)] = array("time" => time(), "warnings" => 0);
            if ($this->getConfig()->get("antiswearwords") && $this->profanityfilter->hasProfanity($e->getMessage())) {
				$player->sendMessage(TEXTFORMAT::RED . $this->getConfig()->get("swearmessage"));
                $e->setCancelled(true);
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
        if (!isset($args[0])) {
            if ($sender instanceof Player) {
                $sender->getPlayer()->sendMessage(TEXTFORMAT::GREEN . "Banmode: " . $this->getConfig()->get("action") . "  " . "Delay: " . $this->getConfig()->get("delay") . " seconds");
            } else {
                $this->getLogger()->info("Banmode: " . $this->getConfig()->get("action") . "  " . "Delay: " . $this->getConfig()->get("delay") . " seconds");
            }
            return true;
        }

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
        return false;
    }

    /**
     * @param PlayerCommandPreprocessEvent $event
     *
     * @priority LOWEST
     */

    public function onPlayerCommand(PlayerCommandPreprocessEvent $event) {
        if ($event->isCancelled() || $event->getPlayer()->isClosed()) return;
        if (($sender = $event->getPlayer())->hasPermission("asp.bypass")) return;
        $message = $event->getMessage();
        if ($message[0] != "/") {
            return;
        }
        if (isset($this->players[spl_object_hash($sender)]) && (time() - $this->players[spl_object_hash($sender)]["time"] <= intval($this->getConfig()->get("delay")))) {
            $this->players[spl_object_hash($sender)]["time"] = time();
            $this->players[spl_object_hash($sender)]["warnings"] = $this->players[spl_object_hash($sender)]["warnings"] + 1;

            if ($this->players[spl_object_hash($sender)]["warnings"] === $this->getConfig()->get("warnings")) {
                $sender->sendMessage(TEXTFORMAT::RED . $this->getConfig()->get("lastwarning"));
                $event->setCancelled(true);
                return;
            }
            if ($this->players[spl_object_hash($sender)]["warnings"] > $this->getConfig()->get("warnings")) {
                $event->setCancelled(true);

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

                    default:
                        break;
                }
                return;
            }
            $sender->sendMessage(TEXTFORMAT::RED . $this->getConfig()->get("message1"));
            $sender->sendMessage(TEXTFORMAT::GREEN . $this->getConfig()->get("message2"));
            $event->setCancelled();
        } else {
            $this->players[spl_object_hash($sender)] = array("time" => time(), "warnings" => 0);
        }
    }

    public function onQuit(PlayerQuitEvent $e)
    {
        if (isset($this->players[spl_object_hash($e->getPlayer())])) {
            unset($this->players[spl_object_hash($e->getPlayer())]);
        }
    }

    public function onLogin(PlayerLoginEvent $e)
    {
		if ($e->isCancelled() || $e->getPlayer()->isClosed()) return;
        if ($this->getConfig()->get("antirudenames") && $this->profanityfilter->hasProfanity($e->getPlayer()->getName())) {
            $e->getPlayer()->kick("No Rude Names Allowed");
            $e->setCancelled(true);
        }
    }

    public function getProfanityFilter()
    {
        return $this->profanityfilter;
    }
}
