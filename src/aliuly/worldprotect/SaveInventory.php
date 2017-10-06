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
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Player;

use aliuly\worldprotect\common\PluginCallbackTask;

class SaveInventory extends BaseWp implements Listener{
	const TICKS = 10;
	const DEBUG = true;
	private $saveOnDeath = false;

	public function __construct(Plugin $plugin){
		parent::__construct($plugin);
		$this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
		$this->saveOnDeath = $plugin->getConfig()->getNested("features")["death-save-inv"];
	}

	public function loadInv(Player $player, $inv = null, SaveInventory $owner){
		$inv = $owner->getState($player, null);
		if($inv == null){
			// ScheduledTask on GMChange can't get players saved inventory after quit, not a problem
			if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Can't load Null Inventory. Player Quit?");
			return;
		}
		foreach($inv as $slot => $t){
			list($id, $dam, $cnt) = explode(":", $t);
			$item = Item::get($id, $dam, $cnt);
			$player->getInventory()->setItem($slot, $item);
			if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Filling Slot $slot with $id");
		}
		$player->getInventory()->sendContents($player);
	}

	public function saveInv(Player $player){
		$inv = [];
		foreach($player->getInventory()->getContents() as $slot => &$item){
			$inv[$slot] = implode(":", [$item->getId(),
				$item->getDamage(),
				$item->getCount()]);
		}
		$this->setState($player, $inv);
	}

	/**
	 * @priority LOWEST
	 */

	public function onQuit(PlayerQuitEvent $ev){
		$player = $ev->getPlayer();
		$this->loadSavedInventory($player);
	}

	public function loadSavedInventory(Player $player){
		$pgm = $player->getGamemode();
		if($pgm == 0 || $pgm == 2) return; // No need to do anything...
		// Switch gamemodes to survival/adventure so the survival inventory gets
		// saved to player.dat
		if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Loading Survival Inventory");
		$player->setGamemode(0);
		$player->getInventory()->clearAll();
		$this->loadInv($player, null, $this);
		$player->save(); // Important!!
		$this->unsetState($player);
	}

	public function onGmChange(PlayerGameModeChangeEvent $ev){
		$player = $ev->getPlayer();
		$newgm = $ev->getNewGamemode();
		$oldgm = $player->getGamemode();
		if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Changing GM from $oldgm to $newgm...");
		if(($newgm == 1 || $newgm == 3) && ($oldgm == 0 || $oldgm == 2)){// We need to save inventory
			$this->saveInv($player);
			if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Saved Inventory from GM $oldgm to $newgm");
		}elseif(($newgm == 0 || $newgm == 2) && ($oldgm == 1 || $oldgm == 3)){
			if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] GM Change - Clear Player Inventory and Reload Saved Inventory...");
			$player->getInventory()->clearAll();
			// Need to restore inventory (but later!)
			$this->owner->getServer()->getScheduler()->scheduleDelayedTask(new PluginCallbackTask($this->owner, [$this, "loadInv"], [$player, null, $this]), self::TICKS);
		}
	}

	public function onPlayerDeath(PlayerDeathEvent $event){
		if(!$this->saveOnDeath) return;
		$event->setKeepInventory(true);
	}
}
