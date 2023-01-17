<?php
//= api-features
//: - Player session and state management

namespace aliuly\worldprotect\common;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

use aliuly\worldprotect\common\MPMU;

/**
 * Basic Session Manager functionality
 */
class Session implements Listener {
  protected array $state;
  /**
   * @param PluginBase $plugin - plugin that owns this session
   */
  public function __construct(protected PluginBase $plugin) {
    $this->plugin->getServer()->getPluginManager()->registerEvents($this,$this->plugin);
    $this->state = [];
  }
  /**
	 * Handle player quit events.  Free's data used by the state tracking
	 * code.
   *
   * @param PlayerQuitEvent $ev - Quit event
	 */
	public function onPlayerQuit(PlayerQuitEvent $ev) {
		$n = MPMU::iName($ev->getPlayer());
		if (isset($this->state[$n])) unset($this->state[$n]);
	}
  /**
	 * Get a player state for the desired module/$label.
	 *
	 * @param string $label - state variable to get
	 * @param Player|string $player - Player instance or name
	 * @param mixed $default - default value to return is no state found
	 * @return mixed
	 */
	public function getState($label,$player,$default) {
    $player = MPMU::iName($player);
		if (!isset($this->state[$player])) return $default;
		if (!isset($this->state[$player][$label])) return $default;
		return $this->state[$player][$label];
	}
	/**
	 * Set a player related state
	 *
	 * @param string $label - state variable to set
	 * @param Player|string $player - player instance or their name
	 * @param mixed $val - value to set
	 * @return mixed
	 */
	public function setState($label,$player,$val) {
    $player = MPMU::iName($player);
		if (!isset($this->state[$player])) $this->state[$player] = [];
		$this->state[$player][$label] = $val;
		return $val;
	}
	/**
	 * Clears a player related state
	 *
	 * @param string $label - state variable to clear
	 * @param Player|string $player - intance of Player or their name
	 */
	public function unsetState($label,$player) {
    $player = MPMU::iName($player);
		if (!isset($this->state[$player])) return;
		if (!isset($this->state[$player][$label])) return;
		unset($this->state[$player][$label]);
	}

}
