<?php

namespace fatutils\pets;

use fatutils\FatUtils;
use fatutils\players\FatPlayer;
use fatutils\players\PlayersManager;
use fatutils\shop\ShopItem;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\Config;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\event\entity\EntityDamageEvent;

/**
 * Created by IntelliJ IDEA.
 * User: Unikaz
 * Date: 17/10/2017
 * Time: 13:56
 */
class PetsManager implements Listener, CommandExecutor
{
    private static $m_Instance = null;
    /** @var TaskHandler $testTask */
    private $testTask = null;

    public static function getInstance(): PetsManager
    {
        if (is_null(self::$m_Instance))
            self::$m_Instance = new PetsManager();
        return self::$m_Instance;
    }

    private function __construct()
    {
        FatUtils::getInstance()->getServer()->getPluginManager()->registerEvents($this, FatUtils::getInstance());
        FatUtils::getInstance()->getServer()->getScheduler()->scheduleRepeatingTask(new OnTick(FatUtils::getInstance()), 2);
    }

    public function updatePets()
    {
        /** @var FatPlayer $player */
        foreach (PlayersManager::getInstance()->getFatPlayers() as $player) {
            $pet = $player->getSlot(ShopItem::SLOT_PET);
            if ($pet != null && $pet instanceof Pet) {
                $pet->updatePosition();
            }
        }
    }

    public function spawnPet(Player $player, $petType, bool $equiped = true): ?ShopItem
    {
        if (array_key_exists($petType, PetTypes::ENTITIES)) {
            $fatPlayer = PlayersManager::getInstance()->getFatPlayer($player);
            $pet = new Pet($player, "pets.qqChose", ["type" => $petType, "class" => Pet::class]);
            $pet->getCustomPet()->namedtag->Invulnerable = new ByteTag("Invulnerable", 1);
//            $pet = ShopItem::createShopItem($player, "pet.qqChose", ["type" => $petType, "class" => Pet::class]);
            if ($equiped)
                $fatPlayer->setSlot(ShopItem::SLOT_PET, $pet);
            return $pet;
        }
        echo "Unknown petType ! \n";
        return null;
    }

    /**
        * @param EntityDamageEvent $event
        * @ignoreCancelled true
        *
        * @return void
        */
    public function onEntityDamage(EntityDamageEvent $event)
    {
        if ($event->getEntity() instanceof CustomPet)
        {
            $event->setCancelled(true);
        }
    }

    /**
     * @param \pocketmine\command\CommandSender $sender
     * @param \pocketmine\command\Command $command
     * @param string $label
     * @param string[] $args
     *
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($sender instanceof \pocketmine\Player) {
            switch ($args[0]) {
                case "spawn": {
                    $this->spawnPet($sender, $args[1])->equip();
                }
                    break;
                case "kill": {
                    PlayersManager::getInstance()->getFatPlayer($sender)->getSlot(ShopItem::SLOT_PET)->unequip();
                    echo "pet killed\n";
                }
                    break;
                case "pos": {
                    /** @var Pet $pet */
                    $pet = PlayersManager::getInstance()->getFatPlayer($sender)->getSlot(ShopItem::SLOT_PET);
                    echo "->" . $pet->getCustomPet()->getLocation() . "\n";
                }
                    break;
                case "list": {
                    $nList = [];
                    $list2 = [];
                    foreach (PetTypes::ENTITIES as $k => $v) {
                        $nList[$v[0]] = $k;
                    }
                    ksort($nList);
                    foreach ($nList as $k => $v) {
                        $list2[] = "\"" . $v . "\" => [\"id\" => " . PetTypes::ENTITIES[$v][0] . ", \"height\" => " . PetTypes::ENTITIES[$v][2] . ", \"width\" => " . PetTypes::ENTITIES[$v][1] . "]";
                    }
                    print_r($list2);
                }
                    break;
                case"test": {
                    $this->testTask = FatUtils::getInstance()->getServer()->getScheduler()->scheduleRepeatingTask(new class(FatUtils::getInstance(), $sender, $args[1]) extends PluginTask
                    {
                        public $sender;
                        public $type;

                        public function __construct(Plugin $owner, $sender, $type)
                        {
                            parent::__construct($owner);
                            $this->sender = $sender;
                            $this->type = $type;
                        }

                        public function onRun(int $currentTick)
                        {
                            PetsManager::getInstance()->spawnPet($this->sender, $this->type)->equip();
                        }
                    }, 10);
                }
                    break;
                case "stop": {
                    $this->testTask->cancel();
                }
                    break;
                case "test2": {
                    for ($i = 0; $i < 4; $i++)
                        $this->spawnPet($sender, $args[1])->equip();
                }
                    break;
            }
        } else {
            echo "Commands only available as a player\n";
        }
        return true;
    }
}

//===============================================
class OnTick extends PluginTask
{
    /**
     * Actions to execute when run
     *
     * @param int $currentTick
     *
     * @return void
     */
    public function onRun(int $currentTick)
    {
        PetsManager::getInstance()->updatePets();
    }
}