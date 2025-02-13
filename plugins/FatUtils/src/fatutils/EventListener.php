<?php

namespace fatutils;

use fatcraft\loadbalancer\LoadBalancer;
use fatutils\ban\BanManager;
use fatutils\game\GameManager;
use fatutils\players\FatPlayer;
use fatutils\players\PlayersManager;
use fatutils\shop\ShopItem;
use fatutils\tools\schedulers\DelayedExec;
use fatutils\tools\TextFormatter;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\event\player\PlayerDeathEvent;
use fatutils\gamedata\GameDataManager;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\Plugin;
use pocketmine\block\BlockIds;
use pocketmine\level\Position;
use pocketmine\level\particle\Particle;
use fatutils\tools\particles\ParticleBuilder;
use pocketmine\event\player\PlayerItemHeldEvent;
use fatutils\permission\PermissionManager;

class EventListener implements Listener
{
	public function onLeavesDecay(LeavesDecayEvent $e)
	{
		$e->setCancelled(true);
	}

    public function onLogin(PlayerLoginEvent $e)
    {
        if (BanManager::getInstance()->isBanned($e->getPlayer()))
        {
            $l_ExpirationTimestamp = BanManager::getInstance()->getPlayerBan($e->getPlayer())->getExpirationTimestamp();
            if (!is_null($l_ExpirationTimestamp))
                $e->setKickMessage("You're banned from this server until " . date("D M j G:i:s Y", $l_ExpirationTimestamp) . ".");
            else
                $e->setKickMessage("You're definitely banned from this server.");
            $e->setCancelled(true);
        }

        FatUtils::getInstance()->getLogger()->info("[LOGIN EVENT] from " . $e->getPlayer()->getName() . "(" . $e->getPlayer()->getUniqueId()->toString() . ") => " . ($e->isCancelled() ? "CANCELLED" : "ACCEPTED"));
    }

    /**
     * @param PlayerJoinEvent $e
     * @priority LOWEST
     */
    public function onJoin(PlayerJoinEvent $e)
    {
        $p = $e->getPlayer();
        $p->getInventory()->clearAll();

		if (!GameManager::getInstance()->isWaiting())
		{
			$e->getPlayer()->kick((new TextFormatter("template.currentlyPlaying"))->asString());
			return;
		}

        if (!PlayersManager::getInstance()->fatPlayerExist($p))
            PlayersManager::getInstance()->addPlayer($p);

        FatUtils::getInstance()->getLogger()->info("Reapplying player to FatPlayer");
        PlayersManager::getInstance()->getFatPlayer($p)->setPlayer($p);

        new DelayedExec(function () use ($p)
		{
			PlayersManager::getInstance()->getFatPlayer($p)->updatePlayerNames();
		}, 1);
    }

    public function onQuit(PlayerQuitEvent $e)
    {
        $p = $e->getPlayer();

		$l_FatPlayer = PlayersManager::getInstance()->getFatPlayer($p);
		foreach ($l_FatPlayer->getSlots() as $l_ShopItem)
		{
			if ($l_ShopItem instanceof ShopItem)
			{
				$l_ShopItem->unequip();
			}
		}

        foreach (LoadBalancer::getInstance()->getServer()->getLevel(1)->getEntities() as $l_entity)
        {
            if ($l_entity instanceof Player || $l_entity->getOwningEntity() == null)
                continue;
            if ($l_entity->getOwningEntity()->getId() == $e->getPlayer()->getId())
                $l_entity->despawnFromAll();
        }

        if (GameManager::getInstance()->isWaiting())
            PlayersManager::getInstance()->removePlayer($p);
		PermissionManager::getInstance()->removePlayerPerms($l_FatPlayer);
    }

	public function onPlayerChat(PlayerChatEvent $e)
	{
		if (PlayersManager::getInstance()->fatPlayerExist($e->getPlayer()))
		{
			$l_FatPlayer = PlayersManager::getInstance()->getFatPlayer($e->getPlayer());
			if ($l_FatPlayer->isMuted())
			{
			    $e->getPlayer()->sendMessage(TextFormat::RED . "You've been muted until " . date("H:i:s", $l_FatPlayer->getMutedExpiration()));
				$e->setCancelled(true);
			} else
			{
                $color = PermissionManager::getInstance()->getFatPlayerGroupColor($l_FatPlayer);
                $e->setMessage($color . $e->getMessage());
			}
		}
	}

    /**
     * @priority MONITOR
     */
    public function onPlayerDamage(EntityDamageEvent $e)
    {
        $p = $e->getEntity();
        if ($p instanceof Player)
        {
            if (FatPlayer::$m_OptionDisplayHealth)
            {
                new DelayedExec(function () use ($p)
                {
                        PlayersManager::getInstance()->getFatPlayer($p)->updatePlayerNames();
                }, 1);
            }
        }
    }

    /**
     * @priority MONITOR
     */
    public function onPlayerRegen(EntityRegainHealthEvent $e)
    {
        $p = $e->getEntity();
        if ($p instanceof Player)
        {
            if (FatPlayer::$m_OptionDisplayHealth)
            {
                new DelayedExec(function () use ($p)
                {
                        PlayersManager::getInstance()->getFatPlayer($p)->updatePlayerNames();
                }, 1);
            }
        }
    }

    public function playerDeathEvent(PlayerDeathEvent $p_Event)
    {
        $l_Player = $p_Event->getEntity();
        $l_Killer = (!is_null($l_Player->getLastDamageCause()) ? $l_Player->getLastDamageCause()->getEntity() : null);
        if ($l_Killer instanceof Player)
            GameDataManager::getInstance()->recordKill($l_Killer->getUniqueId(), $l_Player->getName());

        GameDataManager::getInstance()->recordDeath($l_Player->getUniqueId(), $l_Killer==null?"":$l_Killer->getName());
    }

    public function onPlayerTransfert(\pocketmine\event\player\PlayerTransferEvent $p_Event)
    {
        \SalmonDE\StatsPE\Base::getInstance()->getDataProvider()->savePlayer($p_Event->getPlayer());
    }

    const bedrockViewDistance = 10; // ~ the redius
    public function onInvisibleBedrockHeld(PlayerItemHeldEvent $event)
    {
        if($event->getItem()->getId() == BlockIds::INVISIBLE_BEDROCK && !array_key_exists($event->getPlayer()->getUniqueId()->toString(), InvisibleBlockTask::$playerInvisibleBedrockTasks)) {
            InvisibleBlockTask::$playerInvisibleBedrockTasks[$event->getPlayer()->getUniqueId()->toString()] =
                FatUtils::getInstance()->getServer()->getScheduler()->scheduleRepeatingTask(new InvisibleBlockTask(FatUtils::getInstance(), $event), 20);
        }
    }

    public function onChat(PlayerChatEvent $event): void
    {
        $message = $event->getMessage();
        $originalWords = explode(" ", $message);
        $words = explode(" ", strtolower($message));
        $iterator = 0;
        foreach ($words as $word)
        {
            switch ($word)
            {
                case "abruti":
                case "abrutis":
                case "baise":
                case "bez":
                case "baize":
                case "batard":
                case "batar":
                case "bâtard":
                case "bite":
                case "chier":
                case "chié":
                case "chi":
                case "con":
                case "connard":
                case "conard":
                case "conar":
                case "connasse":
                case "conasse":
                case "conas":
                case "cul":
                case "couille":
                case "couye":
                case "couy":
                case "couill":
                case "encule":
                case "enculé":
                case "enfoiré":
                case "enfoire":
                case "fdp":
                case "gueule":
                case "guele":
                case "gueulle":
                case "merde":
                case "merd":
                case "merdé":
                case "nique":
                case "nik":
                case "niqué":
                case "niké":
                case "ntm":
                case "pd":
                case "poufiasse":
                case "poufias":
                case "pute":
                case "putain":
                case "putin":
                case "suce":
                case "salaud":
                case "salo":
                case "salop":
                case "salope":
                case "sallope":
                case "salloppe":
                case "tencule":
                case "tg":
                case "ass":
                case "asshole":
                case "bastard":
                case "bitch":
                case "cok":
                case "cock":
                case "cocksucker":
                case "dick":
                case "dik":
                case "fuck":
                case "fuk":
                case "fucker":
                case "fucking":
                case "kys":
                case "mofo":
                case "motherfucker":
                case "shit":
                case "twat":
                case "cabron":
                case "puta":
                    for ($i = 0; $i < strlen($word); $i++)
                        $word[$i] = '*';
                    $originalWords[$iterator] = $word;
                    break;
                default:
                    break;
            }
            $iterator++;
        }
        $event->setMessage(implode(" ", $originalWords));
    }
}

class InvisibleBlockTask extends \pocketmine\scheduler\PluginTask
{
    public $event;
    public static $playerInvisibleBedrockTasks = [];

    public function __construct(Plugin $owner, PlayerItemHeldEvent $event)
    {
        parent::__construct($owner);
        $this->event = $event;
        FatUtils::getInstance()->getLogger()->info("construct task");
    }

    /**
     * Actions to execute when run
     *
     * @param int $currentTick
     *
     * @return void
     */
    public function onRun(int $currentTick)
    {
        if($this->event->getPlayer()->getInventory()->getItemInHand()->getId() == BlockIds::INVISIBLE_BEDROCK) {
            $player = $this->event->getPlayer();
            FatUtils::getInstance()->getLogger()->info("onRun " . $player->getName());
            for ($x = -EventListener::bedrockViewDistance; $x < EventListener::bedrockViewDistance; $x++) {
                for ($y = -EventListener::bedrockViewDistance; $y < EventListener::bedrockViewDistance; $y++) {
                    for ($z = -EventListener::bedrockViewDistance; $z < EventListener::bedrockViewDistance; $z++) {
                        $block = $player->level->getBlock(Position::fromObject($player->add($x, $y, $z), $player->level));
                        if($block->getId() == BlockIds::INVISIBLE_BEDROCK)
                        {
                            ParticleBuilder::fromParticleId(Particle::TYPE_REDSTONE)->playForPlayer(Position::fromObject($block->add(0.5, 0.5, 0.5), $player->level), $player);
                            FatUtils::getInstance()->getLogger()->info("display particle");
                        }
                    }
                }
            }
        }
        else
        {
            /** @var PluginTask $task */
            $task = InvisibleBlockTask::$playerInvisibleBedrockTasks[$this->event->getPlayer()->getUniqueId()->toString()];
            FatUtils::getInstance()->getServer()->getScheduler()->cancelTask($task->getTaskId());
            unset(InvisibleBlockTask::$playerInvisibleBedrockTasks[$this->event->getPlayer()->getUniqueId()->toString()]);
            FatUtils::getInstance()->getLogger()->info("destroy task");
        }
    }
}
