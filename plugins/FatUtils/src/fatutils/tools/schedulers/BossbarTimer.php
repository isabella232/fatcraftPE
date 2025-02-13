<?php
/**
 * Created by IntelliJ IDEA.
 * User: Nyhven
 * Date: 12/09/2017
 * Time: 17:27
 */

namespace fatutils\tools\schedulers;


use fatutils\FatUtils;
use fatutils\tools\bossBarAPI\BossBar;
use fatutils\tools\TextFormatter;

class BossbarTimer extends DisplayableTimer
{
    private $m_BossBar = null;

    private $m_Players = null;

    public function addPlayers(array $p_Players)
    {
        $this->m_Players = $p_Players;
    }

    public function setTitle($p_Title):DisplayableTimer
    {
        if ($this->m_BossBar instanceof BossBar)
            $this->m_BossBar->setTitle($this->m_Title);
    	return parent::setTitle($p_Title);
    }

    public function start(): Timer
    {
        if (is_null($this->m_Players))
            $this->m_Players = FatUtils::getInstance()->getServer()->getOnlinePlayers();
        return parent::start();
    }

    public function _onStart()
    {
        parent::_onStart();
        $this->m_BossBar = (new BossBar())
                ->addPlayers($this->m_Players)
                ->setTitle($this->m_Title);
    }

    public function _onTick()
    {
        parent::_onTick();
        if ($this->getTickLeft() % 2)
        {
            if ($this->m_BossBar instanceof BossBar)
            {
                $timeFormat = gmdate("H:i:s", $this->getSecondLeft());

                if ($this->m_Title instanceof TextFormatter)
                {
                    $this->m_Title->addParam("time", $timeFormat); //ref to param "{time}" in translation lines
                    $this->m_BossBar->setTitle($this->m_Title);
                } else
                    $this->m_BossBar->setTitle($this->m_Title . ": " . $timeFormat);

                $this->m_BossBar->setRatio($this->getTimeSpentRatio());
            }
        }

//        if ($this->getTimeLeft() % 40)
//        {
//            if ($this->m_BossBar instanceof BossBar)
//            {
//                $type = BossEventPacket::TYPE_TEXTURE;//rand(0, 15);
//                $color = rand(0, 15);
//                echo "t: " . $type . " c: " . $color . "\n";
//                $this->m_BossBar->setType($type);
//                $this->m_BossBar->setColor($color);
//            }
//        }
    }

    public function _onStop()
    {
        if ($this->m_BossBar instanceof BossBar)
            $this->m_BossBar->remove();
        parent::_onStop();
    }

    public function cancel()
    {
        parent::cancel();
        if ($this->m_BossBar instanceof BossBar)
            $this->m_BossBar->remove();
    }


}