<?php

	namespace Brin;

	use pocketmine\scheduler\PluginTask;

	use pocketmine\math\Vector3;

	class aAC_Particles extends PluginTask {

		public function __construct($plugin, $particle, $level, $radius, $center) {
			parent::__construct($plugin);
			$this->level    = $level;
			$this->radius   = (float) $radius;
			$this->center   = $center;
			$this->particle = $particle;
		}

		public function onRun($tick) {
			$y      = $this->center->y;
			$radius = $this->radius;
			for($i = 0; $i < 361; $i += 1.1) {
				$x = $this->center->x + ($radius * cos($i));
				$z = $this->center->z + ($radius * sin($i));
				$particle = $this->getOwner()->getParticle($this->particle, new Vector3($x, $y, $z));
				$this->level->addParticle($particle);
			}
		}

	}

?>