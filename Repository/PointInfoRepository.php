<?php
/*
* This file is part of EC-CUBE
*
* Copyright(c) 2000-2016 LOCKON CO.,LTD. All Rights Reserved.
* http://www.lockon.co.jp/
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\Point\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

/**
 * Class PointInfoRepository
 * @package Plugin\Point\Repository
 */
class PointInfoRepository extends EntityRepository
{
    /** @var \Eccube\Application */
    protected $app;

    /**
     * PointInfoRepository constructor.
     * @param EntityManager $em
     * @param \Doctrine\ORM\Mapping\ClassMetadata $class
     */
    public function __construct(EntityManager $em, \Doctrine\ORM\Mapping\ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->app = \Eccube\Application::getInstance();
    }

    /**
     * ポイント基本情報を保存
     *  - 受注ステータス・ユーザー設定不可項目をダミーとして追加
     * @param \Plugin\Point\Entity\PointInfo $pointInfo
     * @return bool
     * @throws NoResultException
     */
    public function save(\Plugin\Point\Entity\PointInfo $pointInfo)
    {
        try {
            //保存処理(登録)
            $em = $this->getEntityManager();
            $em->persist($pointInfo);
            $em->flush();

            return true;
        } catch (NoResultException $e) {
            throw new NoResultException();
        }
    }

    /**
     * ポイント機能基本設定情報で最後に設定した内容を取得
     * @return mixed|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLastInsertData()
    {
        try {
            // アソシエーションデータを含む最終データ取得のために親データの最終IDを取得
            $qb = $this->createQueryBuilder('pi')
                ->orderBy('pi.create_date', 'DESC')
                ->setMaxResults(1);


            $result = $qb->getQuery()->getOneOrNullResult();

            // エラー判定
            if (is_null($result)) {
                return null;
            }

            return $result;
        } catch (NoResultException $e) {
            return null;
        }
    }
}
