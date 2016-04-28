<?php


namespace Plugin\Point\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * Class PointInfoAddStatusRepository
 * @package Plugin\Point\Repository
 */
class PointInfoAddStatusRepository extends EntityRepository
{
    /**
     * PointInfoAddStatusRepository constructor.
     * @param EntityManager $em
     * @param Mapping\ClassMetadata $class
     */
    public function __construct(EntityManager $em, Mapping\ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }
}
