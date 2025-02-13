<?php
namespace slapper\entities;

use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\Player;

class SlapperHuman extends Human {

	public function __construct(Level $level, CompoundTag $nbt) {
		parent::__construct($level, $nbt);
		if(!isset($this->namedtag->NameVisibility)) {
			$this->namedtag->NameVisibility = new IntTag("NameVisibility", 2);
		}
		switch ($this->namedtag->NameVisibility->getValue()) {
			case 0:
				$this->setNameTagVisible(false);
				$this->setNameTagAlwaysVisible(false);
				break;
			case 1:
				$this->setNameTagVisible(true);
				$this->setNameTagAlwaysVisible(false);
				break;
			case 2:
				$this->setNameTagVisible(true);
				$this->setNameTagAlwaysVisible(true);
				break;
			default:
				$this->setNameTagVisible(true);
				$this->setNameTagAlwaysVisible(true);
				break;
		}
		if($this->namedtag->getFloat("Scale") === null) {
            $this->getDataPropertyManager()->setFloat(self::DATA_SCALE, 1.0);
		}
		else
    		$this->getDataPropertyManager()->setFloat(self::DATA_SCALE, $this->namedtag->getFloat("Scale"));

	}

	public function saveNBT() {
		parent::saveNBT();
		$visibility = 0;
		if($this->isNameTagVisible()) {
			$visibility = 1;
			if($this->isNameTagAlwaysVisible()) {
				$visibility = 2;
			}
		}
		$scale = $this->getDataPropertyManager()->getFloat(Entity::DATA_SCALE);
		$this->namedtag->SetInt("NameVisibility", $visibility);
		$this->namedtag->setFloat("Scale", $scale);
	}

	public function spawnTo(Player $player) {
		if(!isset($this->hasSpawned[$player->getLoaderId()])) {
			parent::spawnTo($player);

			$this->sendData($player, [self::DATA_NAMETAG => [self::DATA_TYPE_STRING, $this->getDisplayName($player)]]);

			if(isset($this->namedtag["MenuName"]) and $this->namedtag["MenuName"] !== "") {
				$player->getServer()->updatePlayerListData($this->getUniqueId(), $this->getId(), $this->namedtag["MenuName"], $this->skin, [$player]);
			}
		}
	}

	public function getDisplayName(Player $player) {
		$vars = [
			"{name}" => $player->getName(),
			"{display_name}" => $player->getName(),
			"{nametag}" => $player->getNameTag()
		];
		return str_replace(array_keys($vars), array_values($vars), $this->getNameTag());
	}

}
