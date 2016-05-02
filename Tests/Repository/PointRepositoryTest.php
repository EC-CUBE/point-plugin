<?php

namespace Eccube\Tests\Repository;

use Eccube\Application;
use Eccube\Tests\EccubeTestCase;
use Plugin\Point\Entity\Point;
use Plugin\Point\Helper\PointHistoryHelper\PointHistoryHelper;
/**
 * Class PointInfoRepositoryTest
 *
 * @package Eccube\Tests\Repository
 */
class PointRepositoryTest extends EccubeTestCase
{
    public function testGetCalculateCurrentPointByCustomerId(){
        $this->markTestSkipped();

        $this->deleteAllPoint();
        $Point = $this->insertPoint(PointHistoryHelper::STATE_CURRENT);
        $sumPoint = $this->app['eccube.plugin.point.repository.point']->getCalculateCurrentPointByCustomerId($Point->getCustomer()->getId());
        $this->expected = 100;
        $this->actual = $sumPoint;
        $this->verify();
    }

    public function testGetAllProvisionalAddPoint(){
        $this->markTestSkipped();

        $this->deleteAllPoint();
        $Point = $this->insertPoint(PointHistoryHelper::STATE_PRE_ADD);
        $sumPoint = $this->app['eccube.plugin.point.repository.point']->getAllProvisionalAddPoint($Point->getCustomer()->getId());
        $this->expected = 100;
        $this->actual = $sumPoint;
        $this->verify();
    }

    public function testGetProvisionalAddPointByOrder(){
        $this->markTestSkipped();

        $Point = $this->insertPoint(PointHistoryHelper::STATE_PRE_ADD);
        $point = $this->app['eccube.plugin.point.repository.point']->getProvisionalAddPointByOrder($Point->getOrder());
        $this->expected = 100;
        $this->actual = $point;
        $this->verify();
    }

    public function testGetLastAddPointByOrder(){
        $this->markTestSkipped();

        $Point = $this->insertPoint(PointHistoryHelper::STATE_PRE_ADD);
        $point = $this->app['eccube.plugin.point.repository.point']->getLastAddPointByOrder($Point->getOrder());
        $this->expected = 100;
        $this->actual = $point;
        $this->verify();
    }

    public function testGetLastManualPointByCustomer(){
        $this->markTestSkipped();

        $Point = $this->insertPoint(PointHistoryHelper::STATE_CURRENT);
        $point = $this->app['eccube.plugin.point.repository.point']->getLastManualPointByCustomer($Point->getCustomer());
        $this->expected = 100;
        $this->actual = $point;
        $this->verify();
    }

    public function testGetLastAdjustUsePoint(){
        $this->markTestSkipped();

        $Point = $this->insertPoint(PointHistoryHelper::STATE_USE);
        $point = $this->app['eccube.plugin.point.repository.point']->getLastAdjustUsePoint($Point->getOrder());
        $this->expected = 100;
        $this->actual = $point;
        $this->verify();
    }

    public function testGetLastPreUsePoint(){
        $this->markTestSkipped();

        $Point = $this->insertPoint(PointHistoryHelper::STATE_PRE_USE);
        $point = $this->app['eccube.plugin.point.repository.point']->getLastPreUsePoint($Point->getOrder());
        $this->expected = 100;
        $this->actual = $point;
        $this->verify();
    }

    public function testIsLastProvisionalFix(){
        $this->markTestSkipped();

        $Point = $this->insertPoint(PointHistoryHelper::STATE_ADD);
        $pointType = $this->app['eccube.plugin.point.repository.point']->isLastProvisionalFix($Point->getOrder());
        $this->expected = (PointHistoryHelper::STATE_ADD);
        $this->actual = $pointType;
        $this->verify();
    }

    public function deleteAllPoint(){
        $q = $this->app['orm.em']->createQuery('delete from Plugin\Point\Entity\Point p where p.plg_point_id > 1');
        $numDeleted = $q->execute();
    }

    public function insertPoint($type){
        $Point = $this->createPoint($type);
        $this->app['orm.em']->persist($Point);
        $this->app['orm.em']->flush();
        return $Point;
    }

    public function createPoint($type){
        $Customer = $this->createCustomer();
        $Order = $this->createOrder($Customer);
        $Point = new Point();
        $Point
            ->setCustomer($Customer)
            ->setPlgDynamicPoint(100)
            ->setPlgPointType($type)
            ->setOrder($Order);
        return $Point;
    }

}

