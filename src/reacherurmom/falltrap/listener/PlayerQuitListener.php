<?php

declare(strict_types=1);

namespace reacherurmom\falltrap\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use reacherurmom\falltrap\FallTrap;

final class PlayerQuitListener implements Listener {

	public function onQuit(PlayerQuitEvent $event) : void {
		$player = $event->getPlayer();
		$builder = FallTrap::getInstance()->getBuilder($player);

		if ($builder === null) return;
		if ($builder->isRunning()) return;
		FallTrap::getInstance()->removeBuilder($player);

		foreach (FallTrap::getInstance()->getBuilders() as $builder) {
			if ($builder->isRunning()) continue;
			$builder->updateText();
		}
	}
}