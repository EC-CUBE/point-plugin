<?php

namespace Eccube\Tests\Repository;

use Eccube\Application;
use Eccube\Tests\EccubeTestCase;

/**
 * Class PointInfoRepositoryTest
 *
 * @package Eccube\Tests\Repository
 */
class PointInfoRepositoryTest extends EccubeTestCase
{
    public function testGetLastInsertData()
    {
        $PointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();
        // インストール時に初期データが投入されるため, null ではなく オブジェクトが返却されることを確認する.
        $this->assertNotNull($PointInfo);;
    }
}
