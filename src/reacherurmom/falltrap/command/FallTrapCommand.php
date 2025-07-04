<?php

declare(strict_types=1);

namespace reacherurmom\falltrap\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use reacherurmom\falltrap\FallTrap;

final class FallTrapCommand extends Command {

	public function __construct() {
		parent::__construct('falltrap');
		$this->setPermission('falltrap.permission');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void {
		if (!$sender instanceof Player) return;
		if (!$this->testPermission($sender)) return;

		if (FallTrap::getInstance()->getBuilder($sender) !== null) {
			$sender->sendMessage(TextFormat::colorize('&cYou already falltrap builder.'));
			return;
		}
		$axe = VanillaItems::GOLDEN_AXE()
			->setCustomName(TextFormat::colorize('&r&9FallTrap Builder'))
			->setLore(array_map(fn(string $line) => TextFormat::colorize('&r' . $line), ['&r', '&4Right-click to select first position.', '&4Left-click to select second position.', '&r&r', '&4Shift and left-click to create a falltrap.']))
			->setCustomBlockData(CompoundTag::create()
				->setString('fallTrapBuilder', $sender->getXuid()));

		if (!$sender->getInventory()->canAddItem($axe)) {
			$sender->sendMessage(TextFormat::colorize('&cYou don\'t have enough space in your inventory.'));
			return;
		}
		$sender->getInventory()->addItem($axe);
		$sender->sendMessage(TextFormat::colorize('&9[FallTrap] &fYou has been builder now.'));
	}
}