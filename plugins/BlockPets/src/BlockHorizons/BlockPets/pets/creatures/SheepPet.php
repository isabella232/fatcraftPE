<?php

declare(strict_types = 1);

namespace BlockHorizons\BlockPets\pets\creatures;

use BlockHorizons\BlockPets\pets\WalkingPet;

class SheepPet extends WalkingPet {

	public $height = 1.2;
	public $width = 0.8;

	public $name = "Sheep Pet";
	public $networkId = 13;

	public function generateCustomPetData(): void {
		$randomColour = random_int(0, 15);
        $this->getDataPropertyManager()->setByte(self::DATA_COLOUR, $randomColour);
    }

	/**
	 * @return bool
	 */
	public function doTickAction(): bool {
		if(!strtolower($this->getPetName()) === "jeb_") {
			return false;
		}
		if($this->getDataProperty(self::DATA_COLOUR) === 15) {
			$colour = 1;
		} else {
			$colour = $this->getDataProperty(self::DATA_COLOUR);
			$colour++;
		}
        $this->getDataPropertyManager()->setByte(self::DATA_COLOUR, $colour);
		return true;
	}
}