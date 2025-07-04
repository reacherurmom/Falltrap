<?php

declare(strict_types=1);

namespace reacherurmom\falltrap;

use muqsit\invmenu\InvMenuHandler;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use reacherurmom\falltrap\command\FallTrapCommand;
use reacherurmom\falltrap\handler\Builder;
use reacherurmom\falltrap\listener\PlayerInteractListener;
use reacherurmom\falltrap\listener\PlayerQuitListener;

final class FallTrap extends PluginBase {
	use SingletonTrait;

	public const MAX_DIMENSION = 100;
	public const MIN_DIMENSION = 5;

	/** @var array<string, Builder> */
	private array $builders = [];

	protected function onLoad() : void {
		self::setInstance($this);
	}

	protected function onEnable() : void {
		if (!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);
		$this->getServer()->getCommandMap()->register('FallTrap', new FallTrapCommand());

		$this->getServer()->getPluginManager()->registerEvents(new PlayerInteractListener, $this);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerQuitListener, $this);

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () : void {
			$builders = $this->builders;

			if (count($builders) === 0) return;
			$currentBuilder = $builders[array_key_first($builders)];

			if ($currentBuilder->isRunning()) return;
			$currentBuilder->start();
		}), 20);
	}

	public function getBuilders() : array {
		return $this->builders;
	}

	public function getBuilder(Player $player) : ?Builder {
		return $this->builders[$player->getXuid()] ?? null;
	}

	public function createBuilder(Player $player, Block $decoration, Vector3 $minCorner, Vector3 $maxCorner, World $world) : Builder {
		$this->builders[$player->getXuid()] = $builder = new Builder($player, $decoration, $minCorner, $maxCorner, $world);
		return $builder;
	}

	public function removeBuilder(Player $player) : void {
		if (!isset($this->builders[$player->getXuid()])) return;
		unset($this->builders[$player->getXuid()]);
	}
}