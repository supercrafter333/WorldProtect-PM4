<?php
//= module:death-save-inv
//: Restored saved s mode inventory contents after death.
//: Survival mode players will keep inventory when they die ((if gm-save-inv is activated or not)
//: Creative mode players will have saved smode inventory on death (if gm-save-inv is activated)
//: otherwise they will have an empty inventory

namespace aliuly\worldprotect;

use pocketmine\plugin\PluginBase as Plugin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\item\Item;
use pocketmine\Player;
use aliuly\worldprotect\GmSaveInv;

class DeathSaveInv extends BaseWp implements Listener {

    public function __construct(Plugin $plugin) {
        parent::__construct($plugin);
        $this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
    }

    /**
     * @priority LOWEST
     */

    public function PlayerDeath(PlayerDeathEvent $event) {
        $event->setDrops([]);
        $player = $event->getPlayer();
        if($player->getGamemode() == Player::SURVIVAL || $player->getGamemode() == Player::ADVENTURE) {
            $inv = [];
            foreach($player->getInventory()->getContents() as $slot => &$item) {
                $inv[$slot] = implode(":", [$item->getId(),
                    $item->getDamage(),
                    $item->getCount()]);
            }
            $this->setState($player, $inv);
            if(GmSaveInv::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Death Inventory] Saved Inventory on S Mode Death...");
        }
        $player->teleport($player->getSpawn());
    }

    public function PlayerRespawn(PlayerRespawnEvent $event) {
        if(GmSaveInv::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Death Inventory] Respawning...");

        $player = $event->getPlayer();
        $inv = $this->getState($player, null);
        if($inv != null) {
            $player->getInventory()->clearAll();
            foreach($inv as $slot => $t) {
                list($id, $dam, $cnt) = explode(":", $t);
                $item = Item::get($id, $dam, $cnt);
                $player->getInventory()->setItem($slot, $item);
                if(GmSaveInv::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Death Inventory] Filling Slot $slot with $id");
            }
            $this->unsetState($player);
        }
    }
}
