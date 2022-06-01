<?php
namespace aliuly\worldprotect\common;

use pocketmine\scheduler\Task;
use pocketmine\plugin\Plugin;

/**
 * Simple plugin callbacks.
 *
 * Allows the creation of simple callbacks with extra data
 * The last parameter in the callback will be the "currentTicks"
 *
 * Simply put, just do:
 *
 *    new PluginCallbackTask($plugin,[$obj,"method"],[$args])
 *
 * Pass it to the scheduler and off you go...
 */
class PluginCallbackTask extends Task{

	/** @var callable */
	protected $callable;

	/**
	 * @param Plugin   $owner
	 * @param callable $callable
	 * @param array    $args
	 */
	public function __construct(private Plugin $owner, callable $callable, protected array $args = []){
		$this->callable = $callable;
		$this->args[] = $this;
	}
	/**
	 * @return callable
	 */
	public function getCallable(): callable
    {
		return $this->callable;
	}

	public function onRun(): void
    {
		$c = $this->callable;
		$args = $this->args;
		$args[] = $this->getHandler()->getPeriod();
		$c(...$args);
	}

}
