<?php

namespace Plugin\Point\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class PointUse
 * @package Plugin\Point\Entity
 */
class PointUse extends \Eccube\Entity\AbstractEntity
{
    /**
     * @var integer
     */
    private $plg_use_point;

    /**
     * Set plg_use_point
     *
     * @param integer $plg_use_point
     * @return Point
     */
    public function setPlgUsePoint($plg_use_point)
    {
        $this->plg_use_point = $plg_use_point;

        return $this;
    }

    /**
     * Get plg_use_point
     *
     * @return integer
     */
    public function getPlgUsePoint()
    {
        return $this->plg_use_point;
    }
}
