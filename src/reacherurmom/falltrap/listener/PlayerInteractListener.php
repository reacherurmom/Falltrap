<?php

declare(strict_types=1);

namespace reacherurmom\falltrap\listener;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;
use reacherurmom\falltrap\FallTrap;

final class PlayerInteractListener implements Listener {

	/** @var array<string, array<string, Position>> */
	private array $positions = [];

	public function onInteract(PlayerInteractEvent $event) : void {
		$action = $event->getAction();
		$block = $event->getBlock();
		$player = $event->getPlayer();
		$item = $event->getItem();

		if (!$item->hasCustomBlockData()) return;
		if ($item->getCustomBlockData()->getTag('fallTrapBuilder') === null) return;
		$owner = $item->getCustomBlockData()->getString('fallTrapBuilder');
		$event->cancel();

		if ($owner !== $player->getXuid()) return;

		if ($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			$this->positions[$player->getXuid()][0] = $block->getPosition();
			$player->sendMessage(TextFormat::colorize('&4[FallTrap Builder] &fYou has been select first position.'));
			return;
		}

		if ($player->isSneaking()) {
			$positions = $this->positions[$player->getXuid()] ?? [];

			if (count($positions) === 2) {
				[$firstPos, $secondPos] = $positions;
				[$minPos, $maxPos] = [
					new Vector3(
						min($firstPos->getX(), $secondPos->getX()),
						1,
						min($firstPos->getZ(), $secondPos->getZ())
					),
					new Vector3(
						max($firstPos->getX(), $secondPos->getX()),
						max($firstPos->getY(), $secondPos->getY()),
						max($firstPos->getZ(), $secondPos->getZ())
					)
				];

				[$width, $depth] = [(int) abs($minPos->getX() - $maxPos->getX()) + 1, (int) abs($minPos->getZ() - $maxPos->getZ()) + 1];

				if ($width < FallTrap::MIN_DIMENSION || $width > FallTrap::MAX_DIMENSION || $depth < FallTrap::MIN_DIMENSION || $depth > FallTrap::MAX_DIMENSION) {
					$player->sendMessage(TextFormat::colorize('&4[FallTrap Builder] &cArea size invalid.'));
					return;
				}
				$this->selectWool($player, $minPos, $maxPos, $player->getWorld());
				return;
			}
		}
		$this->positions[$player->getXuid()][1] = $block->getPosition();
		$player->sendMessage(TextFormat::colorize('&4[FallTrap Builder] &fYou has been select second position.'));
	}

	private function selectWool(Player $player, Vector3 $firstPos, Vector3 $secondPos, World $world) : void {
		$menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
		$menu->setName(TextFormat::colorize('&9Select wool for your falltrap'));
		$menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($player, $firstPos, $secondPos, $world) : void {
			$item = $transaction->getItemClicked();

			$builder = FallTrap::getInstance()->createBuilder($player, $item->getBlock(), $firstPos, $secondPos, $player->getWorld());
			$builder->updateText();

			$player->sendMessage(TextFormat::colorize('&9[FallTrap] &fYou has been joined in queue to create falltrap.'));

			foreach ($player->getInventory()->getContents() as $item) {
				if ($item->hasCustomBlockData() && $item->getCustomBlockData()->getTag('fallTrapBuilder') !== null) $player->getInventory()->removeItem($item);
			}

			unset($this->positions[$player->getXuid()]);
		}));

		$menu->getInventory()->setContents(
			array_map(
				fn(DyeColor $dyeColor) => VanillaBlocks::WOOL()
					->setColor($dyeColor)
					->asItem(),
				array_values(DyeColor::getAll())
			)
		);

		$menu->send($player);
	}
}