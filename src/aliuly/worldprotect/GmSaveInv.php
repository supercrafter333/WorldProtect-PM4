<?php
//= module:gm-save-inv
//: Will save inventory contents when switching gamemodes.
//:
//: This is useful for when you have per world game modes so that
//: players going from a survival world to a creative world and back
//: do not lose their inventory.

namespace aliuly\worldprotect;

use pocketmine\plugin\PluginBase as Plugin;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\Player;

use aliuly\worldprotect\common\PluginCallbackTask;

class GmSaveInv extends BaseWp implements Listener {
    const TICKS = 10;
    const DEBUG = false;

    public function __construct(Plugin $plugin) {
        parent::__construct($plugin);
        $this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
    }

    public function loadInv($playername, $inv = null, GmSaveInv $owner) {
        $player = $this->owner->getServer()->getPlayerExact($playername);
        if(!($player instanceof Player)) {
            if(SELF::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Not a player!!");
            // ScheduledTask on GMChange can't find player after quit, not a problem
            return;
        }
        $inv = $owner->getState($player, null);
        if($inv == null) {
            if(SELF::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Can't lLad Null Inventory");
            return;
        }
        foreach($inv as $slot => $t) {
            list($id, $dam, $cnt) = explode(":", $t);
            $item = Item::get($id, $dam, $cnt);
            $player->getInventory()->setItem($slot, $item);
            if(SELF::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Filling Slot $slot with $id");
        }
        $player->getInventory()->sendContents($player);
    }

    public function saveInv($player) {
        $inv = [];
        foreach($player->getInventory()->getContents() as $slot => &$item) {
            $inv[$slot] = implode(":", [$item->getId(),
                $item->getDamage(),
                $item->getCount()]);
        }
        $this->setState($player, $inv);
    }

    /**
     * @priority LOWEST
     */
    public function onQuit(PlayerQuitEvent $ev) {
        $player = $ev->getPlayer();
        $sgm = $this->owner->getServer()->getGamemode();
        if($sgm == 1 || $sgm == 3) return; // Don't do much...
        $pgm = $player->getGamemode();
        if($pgm == 0 || $pgm == 2) return; // No need to do anything...

        // Switch gamemodes to survival/adventure so the inventory gets
        // saved...
        if(SELF::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] QUIT: setting to mode $sgm");
        $player->setGamemode($sgm);
        $player->getInventory()->clearAll();
        $this->loadInv($player->getName(), null, $this);
        $player->save(); // Important!!
        $this->unsetState($player);
    }

    public function onGmChange(PlayerGameModeChangeEvent $ev) {
        $player = $ev->getPlayer();
        $newgm = $ev->getNewGamemode();
        $oldgm = $player->getGamemode();
        if(SELF::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Changing GM from $oldgm to $newgm...");
        if(($newgm == 1 || $newgm == 3) && ($oldgm == 0 || $oldgm == 2)) {
            // We need to save inventory
            $this->saveInv($player);
            $player->getInventory()->clearAll();
            if(SELF::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Saved and Cleared Current Inventory from GM $oldgm to $newgm");
        }
        if(($newgm == 0 || $newgm == 2) && ($oldgm == 1 || $oldgm == 3)) {
            if(SELF::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] GM Change - Clear and Reload Saved Inventory...");
            $player->getInventory()->clearAll();
            // Need to restore inventory (but later!)
            $this->owner->getServer()->getScheduler()->scheduleDelayedTask(new PluginCallbackTask($this->owner, [$this, "loadInv"], [$player->getName(), null, $this]), self::TICKS);
        }
    }

    public function PlayerDeath(PlayerDeathEvent $event) {
        $player = $event->getPlayer();
        if($player->getGamemode() == Player::CREATIVE || $player->getGamemode() == Player::SPECTATOR) {
            $this->unsetState($player);
            if(GmSaveInv::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Death Inventory] Clear Saved Inventory on C Mode Death...");
        }
    }
}
