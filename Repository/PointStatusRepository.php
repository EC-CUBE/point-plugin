<?php


namespace Plugin\Point\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * Class PointStatusRepository
 * @package Plugin\Point\Repository
 */
class PointStatusRepository extends EntityRepository
{
    /**
     * PointSnapshotRepository constructor.
     * @param EntityManager $em
     * @param Mapping\ClassMetadata $class
     */
    public function __construct(EntityManager $em, Mapping\ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }
}
