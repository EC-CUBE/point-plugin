<?php


namespace Plugin\Point\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * Class PointSnapshotRepository
 * @package Plugin\Point\Repository
 */
class PointSnapshotRepository extends EntityRepository
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
