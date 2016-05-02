<?php

namespace Plugin\Point\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Plugin\Point\Entity\PointProductRate;

/**
 * Class PointProductRateRepository
 * @package Plugin\Point\Repository
 */
class PointProductRateRepository extends EntityRepository
{
    /**
     * ポイント付与率の保存
     * @param $pointRate
     * @param $product
     * @throws NoResultException
     */
    public function savePointProductRate($pointRate, $product)
    {
        // 新規エンティティにフォーム取得値とリレーションを設定
        $pointRateEntity = new PointProductRate();
        $pointRateEntity->setPlgPointProductRate($pointRate);
        $pointRateEntity->setProduct($product);
        $pointRateEntity->setProductId($product->getId());

        try {
            // DB更新
            $em = $this->getEntityManager();
            $em->persist($product);
            $em->persist($pointRateEntity);
            $em->flush($pointRateEntity);
        } catch (NoResultException $e) {
            throw new NoResultException();
        }
    }

    /**
     * 前回保存の付与率と今回保存の付与率の値を判定
     * @param $pointRate
     * @param $productId
     * @return bool
     */
    public function isSamePoint($pointRate, $productId)
    {
        // 商品IDをもとに最終設定値を取得
        $lastPointRate = $this->getLastPointProductRateById($productId);

        return $lastPointRate === $pointRate;
    }

    /**
     * 商品IDをもとに一番最後に保存したポイント付与率を取得
     * @param $productId
     * @return null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLastPointProductRateById($productId)
    {
        // 値が取得出来ない際は処理をキャンセル
        if (empty($productId)) {
            return null;
        }

        try {
            // 商品IDをもとに最終に保存のポイント付与率を取得
            $qb = $this->createLastPointProductRateBaseQuery();
            $qb->where('pr.product_id = :productId')->setParameter('productId', $productId);

            $result = $qb->getQuery()->getOneOrNullResult();

            // データが一件もない場合処理をキャンセル
            if (is_null($result)) {
                return null;
            }

            // 最終設定ポイント付与率
            return $result->getPlgPointProductRate();
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * 最終データ取得時の共通QueryBuilder作成
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function createLastPointProductRateBaseQuery()
    {
        // 最終データ取得共通クエリビルダーを作成
        return $this->createQueryBuilder('pr')
            ->orderBy('pr.update_date', 'DESC')
            ->setMaxResults(1);
    }

    /**
     * 一番最後に保存したポイント付与率を取得
     * @return null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLastPointProductRate()
    {
        try {
            // 商品IDをもとに最終に保存のポイント付与率を取得
            $qb = $this->createLastPointProductRateBaseQuery();

            $result = $qb->getQuery()->getOneOrNullResult();

            // データが一件もない場合処理をキャンセル
            if (is_null($result)) {
                return null;
            }

            // 最終設定ポイント付与率
            return $result->getPlgPointProductRate();
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * 商品エンティティ配列をもとに商品毎ポイント付与率連想配列を返却
     * @param null $entity
     * @return array|bool|null
     * @throws NoResultException
     */
    public function getPointProductRateByEntity($entity = null)
    {
        // 値が取得出来ない際は処理をキャンセル
        if (!$entity) {
            return null;
        }

        // IDの配列を作成
        $ids = array();
        foreach ($entity as $node) {
            $ids[] = $node->getProduct()->getId();
        }

        // エラーハンドリング
        if (count($ids) < 1) {
            return false;
        }

        try {
            // 商品毎ポイント付与率取得
            $qb = $this->createQueryBuilder('pr');
            $qb->addSelect('MAX(pr.update_date) as HIDDEN max_date')
                ->add('where', $qb->expr()->in('pr.product_id', $ids))
                ->andWhere('pr.plg_point_product_rate IS NOT NULL')
                ->groupBy('pr.update_date')
                ->addGroupBy('pr.plg_point_product_rate_id');

            $result = $qb->getQuery()->getResult();

            // データが一件もない場合処理をキャンセル
            if (count($result) < 1) {
                return null;
            }

            // キー商品ID、値が付与率の連想配列を作成
            $productRates = array();
            foreach ($result as $node) {
                $productRates[$node->getProductId()] = $node->getPlgPointProductRate();
            }

            return $productRates;
        } catch (NoResultException $e) {
            throw new NoResultException();
        }
    }
}
