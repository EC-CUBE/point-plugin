<?php


namespace Plugin\Point\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class PointInfoAddStatus
 * @package Plugin\Point\Entity
 */
class PointInfoAddStatus extends \Eccube\Entity\AbstractEntity
{
    const ADD_STATUS_FIX = 0;
    const ADD_STATUS_NON_FIX = 1;
    /**
     * @var integer
     */
    private $plg_point_info_add_status_id;
    /**
     * @var integer
     */
    private $plg_point_info_id;
    /**
     * @var \Plugin\Point\Entity\PointInfo
     */
    private $PointInfo;
    /**
     * @var smallint
     */
    private $plg_point_info_add_status;
    /**
     * @var smallint
     */
    private $plg_point_info_add_trigger_type;
    /**
     * @var timestamp
     */
    private $create_date;
    /**
     * @var timestamp
     */
    private $update_date;

    public function __construct()
    {
        $this->PointInfo = new ArrayCollection();
    }

    /**
     * Set plg_point_info_add_status_id
     *
     * @param integer $plg_point_info_add_status_id
     * @return PointInfoAddStatus
     */
    public function setPlgPointInfoAddStatusId($plg_point_info_add_status_id)
    {
        $this->plg_point_info_add_status_id = $plg_point_info_add_status_id;

        return $this;
    }

    /**
     * Get plg_point_info_add_status_id
     *
     * @return integer
     */
    public function getPlgPointInfoAddStatusId()
    {
        return $this->plg_point_info_add_status_id;
    }

    /**
     * Set plg_point_info_id
     *
     * @param integer $plg_point_info_id
     * @return PointInfoAddStatus
     */
    public function setPlgPointInfoId($plg_point_info_id)
    {
        $this->plg_point_info_id = $plg_point_info_id;

        return $this;
    }

    /**
     * Get plg_point_info_id
     *
     * @return integer
     */
    public function getPlgPointInfoId()
    {
        return $this->plg_point_info_id;
    }

    /**
     * Set PointInfo
     *
     * @param \Plugin\Point\Entity\PointInfo $pointInfo
     * @return PointInfoAddStatus
     */
    public function setPointInfo(\Plugin\Point\Entity\PointInfo $pointInfo)
    {
        $this->PointInfo = $pointInfo;

        return $this;
    }

    /**
     * Get PointInfo
     *
     * @return \Plugin\Point\Entity\PointInfo
     */
    public function getPointInfo()
    {
        return $this->PointInfo;
    }

    /**
     * Set plg_point_info_add_status
     *
     * @param  smallint $plg_point_info_add_status
     * @return PointInfoAddStatus
     */
    public function setPlgPointInfoAddStatus($plg_point_info_add_status)
    {
        $this->plg_point_info_add_status = $plg_point_info_add_status;

        return $this;
    }

    /**
     * Get plg_point_info_add_status
     *
     * @return smallint
     */
    public function getPlgPointInfoAddStatus()
    {
        return $this->plg_point_info_add_status;
    }

    /**
     * Set plg_point_info_add_trigger_type
     *
     * @param  smallint $plg_point_info_add_trigger_type
     * @return PointInfoAddStatus
     */
    public function setPlgPointInfoAddTriggerType($plg_point_info_add_trigger_type)
    {
        $this->plg_point_info_add_trigger_type = $plg_point_info_add_trigger_type;

        return $this;
    }

    /**
     * Get plg_point_info_add_trigger_type
     *
     * @return smallint
     */
    public function getPlgPointInfoAddTriggerType()
    {
        return $this->plg_point_info_add_trigger_type;
    }

    /**
     * Set create_date
     *
     * @param timestamp $create_date
     * @return PointInfo
     */
    public function setCreateDate($create_date)
    {
        $this->create_date = $create_date;

        return $this;
    }

    /**
     * Get create_date
     *
     * @return timstamp
     */
    public function getCreateDate()
    {
        return $this->create_date;
    }

    /**
     * Set update_date
     *
     * @param timestamp $update_date
     * @return PointInfo
     */
    public function setUpdateDate($update_date)
    {
        $this->update_date = $update_date;

        return $this;
    }

    /**
     * Get update_date
     *
     * @return timstamp
     */
    public function getUpdateDate()
    {
        return $this->update_date;
    }
}
