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
use pocketmine\Player;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;

use aliuly\worldprotect\common\PluginCallbackTask;

class SaveInventory extends BaseWp implements Listener {
	const TICKS = 10;
	const DEBUG = false;

	public function __construct(Plugin $plugin) {
		parent::__construct($plugin);
		$this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
	}

	public function loadInv(Player $player) {

		$nbt = $player->namedtag ?? new CompoundTag("", []);
		$inv = $nbt->getListTag("SurvivalInventory");
		if(isset($inv)) {
			$inv = $nbt->getListTag("SurvivalInventory")->getValue();
		} else {
			if(self::DEBUG) $this->owner->getServer()->geLogger()->info("[WP Inventory] SurvivalInventory Not Found");
			return;
		}

		if($inv == null) {
			// ScheduledTask on GMChange can't get players saved inventory after quit, not a problem
			if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Can't load Null Inventory. Player Quit?");
			return;
		}
		foreach($inv as $field) {
			$item = Item::get($field["id"], $field["Damage"], $field["Count"]);
			$player->getInventory()->setItem($field["Slot"], $item);
			if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Filling Slot " . $field["Slot"] . " with " . $field["id"]);
		}
		$player->getInventory()->sendContents($player);
	}

	public function saveInv(Player $player) {
		$inv = [];
		foreach($player->getInventory()->getContents() as $slot => &$item) {
			$inv[$slot] = [
				$item->getCount(),
				$slot,
				$item->getDamage(),
				$item->getId()
			];
		}

		$nbt = $player->namedtag ?? new CompoundTag("", []);
		$slots = [];
		foreach($inv as $slot) {
			$survivalSlot = new CompoundTag("", [
				new IntTag("Count", $slot[0]),
				new IntTag("Slot", $slot[1]),
				new IntTag("Damage", $slot[2]),
				new IntTag("id", $slot[3])
			]);
			$slots[] = $survivalSlot;
		}
		$nbt->setTagValue("SurvivalInventory", ListTag::class, $slots, true);
		$player->namedtag = $nbt;
		$player->save();
	}

	public function onGmChange(PlayerGameModeChangeEvent $ev) {
		$player = $ev->getPlayer();
		$newgm = $ev->getNewGamemode();
		$oldgm = $player->getGamemode();
		if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Changing GM from $oldgm to $newgm...");
		if(($newgm == 1 || $newgm == 3) && ($oldgm == 0 || $oldgm == 2)) {// We need to save inventory
			$this->saveInv($player);
			if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Saved Inventory from GM $oldgm to $newgm");
		} elseif(($newgm == 0 || $newgm == 2) && ($oldgm == 1 || $oldgm == 3)) {
			if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] GM Change - Clear Player Inventory and load SurvivalInventory...");
			$player->getInventory()->clearAll();
			// Need to restore inventory (but later!)
			$this->owner->getServer()->getScheduler()->scheduleDelayedTask(new PluginCallbackTask($this->owner, [$this, "loadInv"], [$player]), self::TICKS);
		}
	}

	public function PlayerDeath(PlayerDeathEvent $event) {
		$player = $event->getPlayer();
		// Need to restore inventory (but later!).
		$this->owner->getServer()->getScheduler()->scheduleDelayedTask(new PluginCallbackTask($this->owner, [$this, "loadInv"], [$player]), self::TICKS);
		if(self::DEBUG) $this->owner->getServer()->getLogger()->info("[WP Inventory] Reloaded SurvivalInventory on death");
	}
}
