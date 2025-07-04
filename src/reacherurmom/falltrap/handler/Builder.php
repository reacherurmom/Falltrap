<?php

declare(strict_types=1);

namespace reacherurmom\falltrap\handler;

use pocketmine\block\Block;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\SubChunk;
use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\world\utils\SubChunkExplorer;
use pocketmine\world\utils\SubChunkExplorerStatus;
use pocketmine\world\World;
use reacherurmom\falltrap\FallTrap;

final class Builder {

	private readonly SubChunkExplorer $chunkExplorer;
	private readonly FloatingTextParticle $particle;

	private readonly int $totalBlocks;

	private int $currentY;
	private int $currentBlocks;

	private bool $running = false;

	public function __construct(
		private readonly Player $player,
		private readonly Block $decoration,
		private readonly Vector3 $firstCorner,
		private readonly Vector3 $secondCorner,
		private readonly World $world
	) {
		$this->chunkExplorer = new SubChunkExplorer($this->world);
		$this->particle = new FloatingTextParticle('');

		$this->currentY = $this->secondCorner->getFloorY();
		$this->currentBlocks = 0;

		$this->totalBlocks = intval(
			(abs($this->secondCorner->x - $this->firstCorner->x) + 1) *
			(abs($this->secondCorner->y - $this->firstCorner->y) + 1) *
			(abs($this->secondCorner->z - $this->firstCorner->z) + 1)
		);

		$this->world->addParticle($this->center()->add(0, 1, 0), $this->particle);
	}

	public function getCurrentQueuePosition() : int {
		$builders = array_keys(FallTrap::getInstance()->getBuilders());
		$position = array_search($this->player->getXuid(), $builders);

		if (!is_numeric($position)) return -1;
		return $position;
	}

	public function getProgress() : float {
		return round($this->currentBlocks / $this->totalBlocks * 100, 2);
	}

	public function isRunning() : bool {
		return $this->running;
	}

	public function start() : void {
		$this->running = true;
		$this->loadChunks();
		$this->updateText();

		FallTrap::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () : void {
			if ($this->currentBlocks >= $this->totalBlocks) {
				$this->finish();
				throw new CancelTaskException;
			}

			for ($x = $this->firstCorner->getFloorX(); $x <= $this->secondCorner->getFloorX(); $x++) {
				for ($z = $this->firstCorner->getFloorZ(); $z <= $this->secondCorner->z; $z++) {
					$this->currentBlocks++;

					if (!$this->moveTo($x, $this->currentY, $z)) continue;
					$replaceBlock = VanillaBlocks::AIR();

					if ($x === $this->firstCorner->getFloorX() || $x === $this->secondCorner->getFloorX() || $z === $this->firstCorner->getFloorZ() || $z === $this->secondCorner->getFloorZ()) $replaceBlock = $this->decoration;
					$this->chunkExplorer->currentSubChunk->setBlockStateId($x & SubChunk::COORD_MASK, $this->currentY & SubChunk::COORD_MASK, $z & SubChunk::COORD_MASK, $replaceBlock->getStateId());
				}
			}
			$this->currentY--;

			$this->reloadChunks();
			$this->updateText();
		}), 2 * 20);

		if ($this->player->isOnline()) $this->player->sendMessage(TextFormat::colorize('&9[FallTrap] Your falltrap started to create.'));
	}

	public function updateText() : void {
		if ($this->running) {
			$this->particle->setTitle(TextFormat::colorize('&l&9FallTrap &r&8' . $this->getProgress() . '%'));
			$this->particle->setText(TextFormat::colorize('&7' . $this->currentBlocks . '/' . $this->totalBlocks . ' completed.'));
		} else {
			$this->particle->setTitle(TextFormat::colorize('&l&9FallTrap'));
			$this->particle->setText(TextFormat::colorize('&7Queue position &9#' . $this->getCurrentQueuePosition() . PHP_EOL . '&7Waiting to start..'));
		}
		$this->world->addParticle($this->center()->add(0, 2, 0), $this->particle);
	}

	public function finish() : void {
		FallTrap::getInstance()->removeBuilder($this->player);

		$this->particle->setInvisible();
		$this->world->addParticle($this->center()->add(0, 1, 0), $this->particle);

		if ($this->player->isOnline()) $this->player->sendMessage(TextFormat::colorize('&9[FallTrap] Your falltrap finished.'));
	}

	private function moveTo(int $x, int $y, int $z) : bool {
		return $this->chunkExplorer->moveTo($x, $y, $z) !== SubChunkExplorerStatus::INVALID;
	}

	private function center() : Vector3 {
		return new Vector3(
			($this->firstCorner->x + $this->secondCorner->x) / 2,
			$this->secondCorner->y,
			($this->firstCorner->z + $this->secondCorner->z) / 2,
		);
	}

	private function loadChunks() : void {
		for ($x = $this->firstCorner->getFloorX() >> SubChunk::COORD_BIT_SIZE; $x <= $this->secondCorner->getFloorX() >> SubChunk::COORD_BIT_SIZE; ++$x) {
			for ($z = $this->firstCorner->getFloorZ() >> SubChunk::COORD_BIT_SIZE; $z <= $this->secondCorner->getFloorZ() >> SubChunk::COORD_BIT_SIZE; ++$z) {
				$chunk = $this->world->getChunk($x, $z);

				if ($chunk === null) $this->world->loadChunk($x, $z);
			}
		}
	}

	private function reloadChunks() : void {
		for ($x = $this->firstCorner->getFloorX() >> SubChunk::COORD_BIT_SIZE; $x <= $this->secondCorner->getFloorX() >> SubChunk::COORD_BIT_SIZE; ++$x) {
			for ($z = $this->firstCorner->getFloorZ() >> SubChunk::COORD_BIT_SIZE; $z <= $this->secondCorner->getFloorZ() >> SubChunk::COORD_BIT_SIZE; ++$z) {
				$chunk = $this->world->getChunk($x, $z);

				if ($chunk === null) continue;
				$this->world->setChunk($x, $z, $chunk);

				foreach ($this->world->getChunkPlayers($x, $z) as $player) {
					$player->doChunkRequests();
				}
			}
		}
	}
}