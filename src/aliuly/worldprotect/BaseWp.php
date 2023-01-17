<?php
namespace aliuly\worldprotect;

use aliuly\worldprotect\common\BasicCli;
use pocketmine\plugin\PluginBase;

abstract class BaseWp extends BasicCli {
	protected array $wcfg;

	public function __construct(PluginBase $owner) {
		parent::__construct($owner);
		$this->wcfg = [];
	}
	//
	// Config look-up cache
	//
	public function setCfg($world,$value) {
		$this->wcfg[$world] = $value;
	}
	public function unsetCfg($world) {
		if (isset($this->wcfg[$world])) unset($this->wcfg[$world]);
	}
	public function getCfg($world,$default) {
		if (isset($this->wcfg[$world])) return $this->wcfg[$world];
		return $default;
	}
}
