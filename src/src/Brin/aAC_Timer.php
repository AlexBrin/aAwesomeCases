<?php

	namespace Brin;

	use pocketmine\scheduler\PluginTask;

	use pocketmine\math\Vector3;

	use pocketmine\nbt\tag\CompoundTag;
	use pocketmine\nbt\tag\IntTag;
	use pocketmine\nbt\tag\StringTag;
	use pocketmine\nbt\tag\ShortTag;

	use pocketmine\item\Item;

	use pocketmine\tile\Tile;
	use pocketmine\tile\Sign;

	class aAC_Timer extends PluginTask {

		public function __construct($plugin, $player) {
			parent::__construct($plugin);
			$this->player = $player;
		}

		public function onRun($tick) {
			$user = mb_strtolower($this->player->getName());
			$blocks    = $this->getOwner()->open[$user]['coords'];
			$entities  = $this->getOwner()->open[$user]['entities'];
			$particles = $this->getOwner()->open[$user]['particles'];
			unset($this->getOwner()->open[$user]);

			$level = $this->player->getLevel();
			foreach($blocks as $coords => $block) {
				if(is_array($block)) {
					$v3 = new Vector3($block[0]->x, $block[0]->y, $block[0]->z);
					$level->setBlock($v3, $block[0]);
					if($block[1] instanceof Item) {
						$nbt = new CompoundTag("", [
								new StringTag("id", Tile::FLOWER_POT),
								new IntTag("x", $v3->x),
								new IntTag("y", $v3->y),
								new IntTag("z", $v3->z),
								new ShortTag("item", $block[1]->getId()),
								new IntTag("mData", $block[1]->getDamage())
							]);
						Tile::createTile(Tile::FLOWER_POT, $level, $nbt);
					}
					else {
						$nbt = new CompoundTag("", [
								"id" => new StringTag("id", Tile::SIGN),
								"x" => new IntTag("x", $v3->x),
								"x" => new IntTag("y", $v3->y),
								"x" => new IntTag("z", $v3->z),
								"Text1" => new StringTag("Text1", $block[1][0]),
								"Text2" => new StringTag("Text2", $block[1][1]),
								"Text3" => new StringTag("Text3", $block[1][2]),
								"Text4" => new StringTag("Text4", $block[1][3])
							]);
						Tile::createTile(Tile::SIGN, $level, $nbt);
						
					}
				}
				else
					$level->setBlock(new Vector3($block->x, $block->y, $block->z), $block);
			}

			foreach($entities as $entity)
				$this->getOwner()->removeEntity($entity, $level);

			if($particles !== false)
				$this->getOwner()->getServer()->getScheduler()->cancelTask($particles);
		}

	}

?>