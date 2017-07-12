<?php

	namespace Brin;

	use pocketmine\plugin\PluginBase;
	use pocketmine\utils\Config;
	use pocketmine\math\Vector3;

	use pocketmine\item\Item;
	use pocketmine\inventory\Inventory;
	use pocketmine\utils\Color;
	use pocketmine\item\Armor;
	use pocketmine\item\enchantment\Enchantment;

	use pocketmine\command\Command;
	use pocketmine\command\CommandSender;

	use pocketmine\block\Block;
	use pocketmine\block\WallSign;
	use pocketmine\block\SignPost;
	use pocketmine\block\FlowerPot;

	use pocketmine\event\Listener;
	use pocketmine\event\block\BlockPlaceEvent;
	use pocketmine\event\block\BlockBreakEvent;
	use pocketmine\event\player\PlayerInteractEvent;
	use pocketmine\event\player\PlayerMoveEvent;
	use pocketmine\event\player\PlayerJoinEvent;
	use pocketmine\event\player\PlayerRespawnEvent;

	use pocketmine\Player;

	use pocketmine\network\protocol\AddItemEntityPacket;
	use pocketmine\network\protocol\AddEntityPacket;
	use pocketmine\network\protocol\RemoveEntityPacket;
	use pocketmine\entity\Entity;
	use pocketmine\entity\Item as ItemEntity;

	use pocketmine\level\particle\FlameParticle;
	use pocketmine\level\particle\LavaParticle;
	use pocketmine\level\particle\HeartParticle;
	use pocketmine\level\particle\WaterParticle;
	use pocketmine\level\particle\HappyVillagerParticle;
	use pocketmine\level\particle\AngryVillagerParticle;
	use pocketmine\level\particle\BubbleParticle;
	use pocketmine\level\particle\PortalParticle;
	use pocketmine\level\particle\EnchantParticle;


	class aAC_Main extends PluginBase implements Listener {
		private $config,
						$cases;

		public  $open = [];

		private $giveWait = [];

		public	$sin = [],
						$cos = [];

		public function onEnable() {
			$f = $this->getDataFolder();
			if(!is_dir($f))
				@mkdir($f);
			$this->saveResource('config.yml');
			$this->saveResource('cases.yml');
			$this->config   = new Config($f.'config.yml', Config::YAML);
			$this->cases    = new Config($f.'cases.yml',  Config::YAML);
			$this->giveWait = new Config($f.'giveWait.json', Config::YAML);
			$this->getServer()->getPluginManager()->registerEvents($this, $this);
		}

		public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
			
			if(count($args) == 2) {
				$name = mb_strtolower($args[0]);
				$case = mb_strtolower($args[1]);
				$case = $this->cases->get($case);
				$item = Item::get($case['item']['id'], $case['item']['damage'], 1);
				$item->setCustomName($case['name']);

				$player = $this->getServer()->getPlayer($name);
				if($player instanceof Player) {
					$player->getInventory()->addItem($item);
					$player->sendMessage($this->config->getNested('messages.given'));
				}
				else {
					$this->giveWait->set($name, $case);
					$this->giveWait->save();
				}

			}
			else
				$sender->sendMessage("Use: /aac <player> <case>");

		}

		public function onPlayerJoin(PlayerJoinEvent $event) {
			$player = $event->getPlayer();
			$name = mb_strtolower($player->getName());
			if($this->giveWait->exists($name)) {
				$case = $this->cases->get($this->giveWait->give($player->getName()));
				$this->giveWait->remove($name);
				$this->giveWait->save();
				$item = Item::get($case['item']['id'], $case['item']['damage'], 1);
				$item->setCustomName($case['name']);
				$player->getInventory()->addItem($item);
				$player->sendMessage($this->config->getNested('messages.given'));
			}
		}

		public function onPlayerRespawn(PlayerRespawnEvent $event) {
			$player = $event->getPlayer();
			$levelName = mb_strtolower($event->getRespawnPosition()->level->getName());
			foreach($this->open as $open)
				if($levelName != $open['level'])
					foreach($open['entities'] as $eid)
						$this->removeEntity($eid, false, $player);
		}

		public function onBlockPlace(BlockPlaceEvent $event) {
			$item = mb_strtolower($event->getItem()->getCustomName());
			$item = preg_replace("/§./", '', $item);
			if($this->cases->exists($item)) {
				$event->setCancelled(true);
				$player = $event->getPlayer(); $user = mb_strtolower($player->getName());
				$block = $event->getBlock();
				$case = $this->cases->get($item);
				$this->open[$user] = [
					'coords'    => [],
					'open'      => [],
					'items'     => $case['items'],
					'entities'  => [],
					'level'     => mb_strtolower($player->getLevel()->getName()),
					'particles' => false
				];
				$item = $event->getItem();
				$item->setCount($item->getCount() - 1);
				$player->getInventory()->setItemInHand($item);

				$radius = $this->config->getNested('particles.radius');
				if($radius > 0) {
					$v3 = new Vector3($player->getFloorX() + 0.5, $player->getFloorY() + 2, $player->getFloorZ() + 0.5);
					$this->open[$user]['particles'] = ($this->getServer()->getScheduler()->scheduleRepeatingTask(
							new aAC_Particles($this,
																$this->config->getNested('particles.particle', 'flame'), 
																$block->level,
																$radius,
																$v3
							),
							5
						))->getTaskId();
				}

				// REPLACE
				$x     = $player->getFloorX() - 1;
				$y     = $player->getFloorY() - 1;
				$z     = $player->getFloorZ() - 1;
				$level = $block->level;

				for($x1 = $x - 1; $x1 < $x + 4; $x1++)
					for($y1 = $y; $y1 < $y + 3; $y1++)
						for($z1 = $z - 1; $z1 < $z + 4; $z1++) {
							$v3 = new Vector3($x1, $y1, $z1);
							$saveBlock = $level->getBlock(new Vector3($x1, $y1, $z1));
							$this->open[$user]['coords'][] = $saveBlock;
							if($saveBlock instanceof WallSign or $saveBlock instanceof SignPost) {
								$tile = $level->getTile($saveBlock);
								$this->open[$user]['coords']["$x1:$y1:$z1"] = [
									$saveBlock,
									$tile->getText()
								];
							}	
							elseif($saveBlock instanceof FlowerPot) {
								$tile = $level->getTile($saveBlock);
								$this->open[$user]['coords']["$x1:$y1:$z1"] = [
									$saveBlock,
									$tile->getItem()
								];
							}
							else
								$this->open[$user]['coords']["$x1:$y1:$z1"] = $saveBlock;
						}

				$newBlock = Block::get($case['blocks']['floor']['id']);
				for($x1 = $x; $x1 < $x + 3; $x1++) {
					for($z1 = $z; $z1 < $z + 3; $z1++) {
						$v3 = new Vector3($x1, $y, $z1);
						$level->setBlock($v3, $newBlock);
					}
					$z1 = $z;
				}

				$y++;
				$air = Block::get(0);
				for($x1 = $x; $x1 < $x + 3; $x1++)
					for($z1 = $z; $z1 < $z + 3; $z1++)
						$level->setBlock(
							new Vector3($x1, $y, $z1),
							$air
						);

				$y++;
				$air = Block::get(0);
				for($x1 = $x - 1; $x1 < $x + 4; $x1++)
					for($z1 = $z - 1; $z1 < $z + 4; $z1++)
						$level->setBlock(
							new Vector3($x1, $y, $z1),
							$air
						);
				$y--;


				// Да-да-да...знаю
				// Но это работает несколько быстрее, следовательно нагрузка меньше
				$x--; $z--;
				$wall = $case['blocks']['wall']['id'];
				$level->setBlockIdAt($x, $y, $z, $wall);
				$level->setBlockIdAt($x+1, $y, $z, $wall);
				$level->setBlockIdAt($x+2, $y, $z, 54);
				$level->setBlockIdAt($x+3, $y, $z, $wall);
				$level->setBlockIdAt($x, $y, $z+1, $wall);
				$level->setBlockIdAt($x, $y, $z+2, 54);
				$level->setBlockIdAt($x, $y, $z+3, $wall);
				$level->setBlockIdAt($x+4, $y, $z, $wall);
				$level->setBlockIdAt($x+4, $y, $z+1, $wall);
				$level->setBlockIdAt($x+4, $y, $z+2, 54);
				$level->setBlockIdAt($x+4, $y, $z+3, $wall);
				$level->setBlockIdAt($x, $y, $z+4, $wall);
				$level->setBlockIdAt($x+1, $y, $z+4, $wall);
				// $level->setBlock(
				// 		new Vector3($x+2, $y, $z+4),
				// 		new Block(54, 2) // НЕ ВРАЩАЕТ
				// 	); 
				$level->setBlockIdAt($x+2, $y, $z+4, 54);
				$level->setBlockIdAt($x+3, $y, $z+4, $wall);
				$level->setBlockIdAt($x+4, $y, $z+4, $wall);

			}
		}

		public function onBlockBreak(BlockBreakEvent $event) {
			$block  = $event->getBlock();
			$coords = "{$block->x}:{$block->y}:{$block->z}";
			foreach($this->open as $open)
				if(isset($open['coords'][$coords]))
					$event->setCancelled(true);
		}

		public function onPlayerInteract(PlayerInteractEvent $event) {
			$player = $event->getPlayer(); $user = mb_strtolower($player->getName());
			if(!isset($this->open[$user]))
				return;

			$block  = $event->getBlock();
			if($block->getId() != 54)
				return;

			$event->setCancelled(true);

			$crd = "{$block->x}:{$block->y}:{$block->z}";
			if(isset($this->open[$user]['open'][$crd]))
				return;

			// SPAWN ITEM ENTITY
			$v3 = new Vector3($block->x, $block->y, $block->z);
			$itemArray = $this->open[$user]['items'][array_rand($this->open[$user]['items'])];

			$this->open[$user]['entities'][] = $this->createItemEntity($this->item($itemArray, true), $v3, $block->level);
			$item = $this->item($itemArray);
			$this->open[$user]['entities'][] = $this->createFloatingText($item->getCustomName(), $v3, $block->level);

			$v3->x += 0.5;
			$v3->y += 1.2;
			$v3->z += 0.5;
			for($i = 0; $i <= $this->config->getNested('particles.open.count', 15); $i++) {
				$scatter = $this->config->getNested('particles.open.scatter', 0.15);
				$vector3 = $v3;
				$vector3->x += $this->randomFloat(-$scatter, $scatter);
				$vector3->y += $this->randomFloat(-0.1, 0.1);
				$vector3->z += $this->randomFloat(-$scatter, $scatter);
				$block->level->addParticle(
						new LavaParticle($vector3)
					);
			}

			$player->getInventory()->addItem($item);

			$this->open[$user]['open'][$crd] = true;
			if(count($this->open[$user]['open']) == 4)
				$this->getServer()->getScheduler()->scheduleDelayedTask(new aAC_Timer($this, $player), $this->config->get('delay', 1.5) * 20);
		}

		public function createFloatingText(string $text, Vector3 $pos, $level = false, $player = false) {
			$eid = Entity::$entityCount++;
			$pk = new AddEntityPacket();
			$pk->eid = $eid;
			$pk->type = ItemEntity::NETWORK_ID;
			$pk->x = $pos->x + 0.5;
			$pk->y = $pos->y + 1.2;
			$pk->z = $pos->z + 0.6;
			$pk->speedX = 0; 
			$pk->speedY = 0; 
			$pk->speedZ = 0; 
			$pk->yaw = 0;
			$pk->pitch = 0;
			$pk->item = 0;
			$pk->meta = 0;
			$flags  = 0;
			$flags |= 1 << Entity::DATA_FLAG_INVISIBLE;
			$flags |= 1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG;
			$flags |= 1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG;
			$flags |= 1 << Entity::DATA_FLAG_IMMOBILE;
			$pk->metadata = [
				Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
				Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $text]
			];
			$this->sendPacket($pk, $level, $player);
			return $eid;
		}

		public function createItemEntity(Item $item, Vector3 $pos, $level = false, $player = false) {
			$eid = Entity::$entityCount++;
			$pk = new AddItemEntityPacket();
			$pk->eid = $eid;
			$pk->item = $item;
			$pk->x = $pos->x + 0.5;
			$pk->y = $pos->y + 1;
			$pk->z = $pos->z + 0.5;
			$pk->yaw = 0;
			$pk->pitch = 0;
			$pk->roll = 10;
			$this->sendPacket($pk, $level, $player);
			return $eid;
		}

		public function removeEntity($eid, $level = false, $player = false) {
			$pk = new RemoveEntityPacket();
			$pk->eid = $eid;
			$this->sendPacket($pk, $level, $player);
			return $eid;
		}

		private function sendPacket($packet, $level = false, $player = false) {
			if($level) {
				if(is_string($level) && !$level instanceof Level)
					$level = $this->getServer()->getLevelByName($level);
				foreach($level->getPlayers() as $player)
					$player->directDataPacket($packet);
				return;
			}
			elseif($player) {
				if(is_string($player))
					$player = $this->getServer()->getPlayer($player);
				if($player instanceof Player) {
					$player->directDataPacket($packet);
					return;
				}
			}
			$this->getLogger()->warning('Level and player are missing');
		}

		private function item($i, $empty = false) {
			if(empty($i['damage']))
				$i['damage'] = 0;
			if(empty($i['count']))
				$i['count'] = 1;
			$item = Item::get($i['id'], $i['damage'], $i['count']);
			if(!empty($i['name']))
				$item->setCustomName($i['name']);
			if($empty)
				return $item;
			if($item->isArmor())
				if(!empty($i['color'])) {
					$rgb = explode(' ', $i['color']);
					if(count($rgb) == 3)
						$item->setCustomColor(Color::getRGB($rgb[0], $rgb[1], $rgb[2]));
				}
 			if(isset($i['enchants'])) {
 				if(is_array($i['enchants'])) {
 					foreach($i['enchants'] as $ench) {
 						if(!isset($ench['level']))
 							$ench['level'] = 1;
 						$ench = Enchantment::getEnchantment($ench['id'])->setLevel($ench['level']);
 						$item->addEnchantment($ench);
 					}
 				}
 			}
 			return $item;
		}

				/**
		 * @param string $particle
		 * @param array  $coords
		 * @return bool false | Particle
		 */
		public function getParticle($particle, $coords) {
			if(is_array($coords))
				$vector3 = new Vector3($coords['x'] + $this->randomFloat(), $coords['y'] + $this->randomFloat(0.2), $coords['z'] + $this->randomFloat());
			elseif($coords instanceof Vector3)
				$vector3 = $coords;
			switch($particle) {
				case 'flame':
						$particle = new FlameParticle($vector3);
					break;
				// Лагает пиздец
				// case 'lava':
				// 		$particle = new LavaParticle($vector3);
				// 	break;
				case 'heart':
						$particle = new HeartParticle($vector3, mt_rand(1, 3));
					break;
				case 'water':
						$particle = new WaterParticle($vector3);
					break;
				case 'happy':
						$particle = new HappyVillagerParticle($vector3);
					break;
				case 'angry':
						$particle = new AngryVillagerParticle($vector3);
					break;
				case 'bubble':
						$particle = new BubbleParticle($vector3);
					break;
				case 'portal':
						$particle = new PortalParticle($vector3);
					break;
				// Не работает
				// case 'enchant':
				// 		$particle = new EnchantParticle($vector3);
				// 	break;
				default:
						$particle = new FlameParticle($vector3);
					break;
			}
			return $particle;
		}

		private function randomFloat($min = -1.2, $max = 1.2) {
			return $min + mt_rand() / mt_getrandmax() * ($max - $min);
		}

	}

?>