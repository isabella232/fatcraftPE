<?php

/*
 * MSpawns (v1.5) by EvolSoft
 * Developer: EvolSoft (Flavius12)
 * Website: http://www.evolsoft.tk
 * Date: 27/12/2014 01:26 PM (UTC)
 * Copyright & License: (C) 2014-2017 EvolSoft
 * Licensed under MIT (https://github.com/EvolSoft/MSpawns/blob/master/LICENSE)
 */

namespace MSpawns\Commands;

use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use MSpawns\Main;

class SetSpawn extends PluginBase implements CommandExecutor
{

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args): bool
    {
        switch (strtolower($cmd->getName()))
        {
            case "setspawn":
                if ($sender instanceof Player){
                    if ($sender->hasPermission("mspawns.setspawn")){
                        $this->plugin->setSpawn($sender);
                        return true;
                                            } else{
                        $sender->sendMessage($this->plugin->translateColors("&", "&cYou don't have permissions to use this command"));
                        return true;
                                            }
                                    } else{
                    $sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX . "&cYou can only perform this command as a player"));
                    return true;
                                    }
                break;
        }
        return true;
    }

}
