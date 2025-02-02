<?php
namespace aliuly\worldprotect\common;
//= api-features
//: - Config shortcuts and multi-module|feature management

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

/**
 * Simple extension to the PocketMine PluginBase class
 */
abstract class BasicPlugin extends PluginBase {
	protected array $modules;
	protected SubCommandMap|null $scmdMap = null;
	protected Session|null $session;

	/**
	 * Given some defaults, this will load optional features
	 *
	 * @param string $ns - namespace used to search for classes to load
	 * @param array $mods - optional module definition
	 * @param array $defaults - default options to use for config.yml
	 * @param string $xhlp - optional help format.
	 * @return array
	 */
	protected function modConfig($ns,$mods,$defaults,$xhlp="") : array{
		if (!isset($defaults["features"])) $defaults["features"] = [];
		foreach ($mods as $i => $j) {
			$defaults["features"][$i] = $j[1];
		}
		$cfg=(new Config($this->getDataFolder()."config.yml",
									  Config::YAML,$defaults))->getAll();
		$this->modules = [];
		foreach ($cfg["features"] as $i=>$j) {
			if (!isset($mods[$i])) {
				$this->getLogger()->debug(mc::_("Unknown feature \"%1%\" ignored.",$i));
				continue;
			}
			if (!$j) continue;
			$class = $mods[$i][0];
			if (is_array($class)) {
				while (count($class) > 1) {
					// All classes before the last one are dependencies...
					$classname = $dep = array_shift($class);
					if(strpos($classname,"\\") === false) $classname = $ns."\\".$classname;
					if (isset($this->modules[$dep])) continue; // Dependancy already loaded
					if(isset($cfg[strtolower($dep)])) {
						$this->modules[$dep] = new $classname($this,$cfg[strtolower($dep)]);
					} else {
						$this->modules[$dep] = new $classname($this);
					}
				}
				// The last class in the array implements the actual feature
				$class = array_shift($class);
			}
			if(strpos($class,"\\") === false) $class = $ns."\\".$class;
			if (isset($cfg[$i]))
				$this->modules[$i] = new $class($this,$cfg[$i]);
			else
				$this->modules[$i] = new $class($this);
		}
		$c = count($this->modules);
		if ($c == 0) {
			$this->getLogger()->info(mc::_("NO features enabled"));
			return [];
		}
		$this->session = null;
		$this->getLogger()->info(mc::n(mc::_("Enabled one feature"),
													 mc::_("Enabled %1% features",$c),
													 $c));
		if ($this->scmdMap !== null && $this->scmdMap->getCommandCount() > 0) {
			$this->modules[] = new BasicHelp($this,$xhlp);
		}
		return $cfg;
	}
  /**
	 * Get module
	 * @param string $module - module to retrieve
	 * @return mixed|null
	 */
	public function getModule($str) {
		if (isset($this->modules[$str])) return $this->modules[$str];
		return null;
	}
	/**
	 * Get Modules array
	 * @return array
	 */
	public function getModules() {
		return $this->modules;
	}
	/**
	 * Save a config section to the plugins' config.yml
	 *
	 * @param string $key - section to save
	 * @param mixed $settings - settings to save
	 */
	public function cfgSave($key,$settings) {
		$cfg=new Config($this->getDataFolder()."config.yml",Config::YAML);
		$dat = $cfg->getAll();
		$dat[$key] = $settings;
		$cfg->setAll($dat);
		$cfg->save();
	}
	/**
	 * Dispatch commands using sub command table
	 */
	protected function dispatchSCmd(CommandSender $sender,Command $cmd,array $args,$data=null) {
		if ($this->scmdMap === null) {
			$sender->sendMessage(mc::_("No sub-commands available"));
			return false;
		}
		return $this->scmdMap->dispatchSCmd($sender,$cmd,$args,$data);
	}
	/** Look-up sub command map
	 * @returns SubCommandMap
	 */
	public function getSCmdMap() {
		return $this->scmdMap;
	}
	/**
	 * Register a sub command
	 * @param string $cmd - sub command
	 * @param callable $callable - callable to execute
	 * @param array $opts - additional options
	 */
	public function registerSCmd($cmd,$callable,$opts) {
		if ($this->scmdMap === null) {
			$this->scmdMap = new SubCommandMap();
		}
		$this->scmdMap->registerSCmd($cmd,$callable,$opts);
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
		if ($this->session === null) return $default;
		return $this->session->getState($label,$player,$default);
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
		if ($this->session === null) $this->session = new Session($this);
		return $this->session->setState($label,$player,$val);
	}
	/**
	 * Clears a player related state
	 *
	 * @param string $label - state variable to clear
	 * @param Player|string $player - instance of Player or their name
	 */
	public function unsetState($label,$player) {
		if ($this->session === null) return;
		$this->session->unsetState($label,$player);
	}

	/**
	 * Gets the contents of an embedded resource on the plugin file.
	 *
	 * @param string $filename
	 * @return string|null
	 */
	public function getResourceContents($filename){
		$fp = $this->getResource($filename);
		if($fp === null){
			return null;
		}
		$contents = stream_get_contents($fp);
		fclose($fp);
		return $contents;
	}
}
