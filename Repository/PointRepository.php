<?php


namespace Plugin\Point\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Plugin\Point\Helper\PointHistoryHelper\PointHistoryHelper;

/**
 * Class PointRepository
 * @package Plugin\Point\Repository
 */
class PointRepository extends EntityRepository
{
    /**
     * カスタマーIDを基準にポイントの合計を計算
     * @param int $customer_id
     * @param array $orderIds
     * @return int 保有ポイント
     */
    public function calcCurrentPoint($customer_id, array $orderIds)
    {
        if (count($orderIds) < 1) {
            return 0;
        }

        try {
            $orderStatus = new OrderStatus();
            $orderStatus->setId(8);

            // ログテーブルからポイントを計算
            $qb = $this->createQueryBuilder('p');
            $qb->select('SUM(p.plg_dynamic_point) as point_sum')
                ->where($qb->expr()->in('p.order_id', $orderIds))
                ->orWhere($qb->expr()->andX(
                    $qb->expr()->isNull('p.order_id'),
                    $qb->expr()->eq('p.customer_id', $customer_id))
                )
                ->orWhere(
                    $qb->expr()->eq('p.plg_point_type', PointHistoryHelper::STATE_USE)
                );
            // 合計ポイント
            $sum_point = $qb->getQuery()->getScalarResult();

            // 情報が取得できない場合
            if (count($sum_point) < 1) {
                return 0;
            }

            return $sum_point[0]['point_sum'];
        } catch (NoResultException $e) {
            return 0;
        }
    }

    /**
     * 仮ポイントを会員IDを基に返却
     *  - 合計値
     * @param array $orderIds
     * @return int 仮ポイント
     */
    public function calcProvisionalAddPoint(array $orderIds)
    {
        if (count($orderIds) < 1) {
            return 0;
        }

        try {
            $qb = $this->createQueryBuilder('p');
            $qb->select('SUM(p.plg_dynamic_point) as point_sum')
                ->where($qb->expr()->in('p.order_id', $orderIds))
                ->andWhere($qb->expr()->neq('p.plg_point_type', PointHistoryHelper::STATE_USE));

            $provisionalAddPoint = $qb->getQuery()->getScalarResult();

            // 仮ポイント取得判定
            if (count($provisionalAddPoint) < 1) {
                return 0;
            }

            return $provisionalAddPoint[0]['point_sum'];
        } catch (NoResultException $e) {
            return 0;
        }
    }

    /**
     * 受注に対して行われた最後の付与ポイントを取得
     * @param $order
     * @return int 付与ポイント
     */
    public function getLatestAddPointByOrder($order)
    {
        // 必要エンティティ判定
        if (empty($order)) {
            return 0;
        }

        try {
            // 受注をもとにその受注に対して行われた最後の付与ポイントを取得
            $qb = $this->createQueryBuilder('p')
                ->andWhere('p.customer_id = :customer_id')
                ->andWhere('p.order_id = :order_id')
                ->andWhere('p.plg_point_type = :point_type')
                ->setParameter('customer_id', $order->getCustomer()->getId())
                ->setParameter('order_id', $order->getId())
                ->setParameter('point_type', PointHistoryHelper::STATE_ADD)
                ->orderBy('p.plg_point_id', 'desc')
                ->setMaxResults(1);

            $addPoint = $qb->getQuery()->getResult();
            return $addPoint[0]->getPlgDynamicPoint();
        } catch (NoResultException $e) {
            return 0;
        }
    }

    /**
     * 最終利用ポイントを受注エンティティより取得
     * @param Order $order
     * @return int 利用ポイント
     */
    public function getLatestUsePoint(Order $order)
    {
        $customer = $order->getCustomer();
        if (empty($customer)) {
            return 0;
        }
        try {
            // 履歴情報をもとに現在利用ポイントを計算し取得
            $qb = $this->createQueryBuilder('p')
                //->addSelect('o')
                ->where('p.customer_id = :customerId')
                ->andWhere('p.order_id = :orderId')
                ->andWhere('p.plg_point_type = :pointType')
                ->setParameter('customerId', $order->getCustomer()->getId())
                ->setParameter('orderId', $order->getId())
                ->setParameter('pointType', PointHistoryHelper::STATE_USE)
                ->orderBy('p.plg_point_id', 'desc')
                ->setMaxResults(1);
            $max_use_point = $qb->getQuery()->getResult();

            // 取得値判定
            if (count($max_use_point) < 1) {
                return 0;
            }

            return $max_use_point[0]->getPlgDynamicPoint();
        } catch (NoResultException $e) {
            return 0;
        }
    }

    /**
     * 最終仮利用ポイントを取得
     * @param Order $order
     * @return int 仮利用ポイント
     */
    public function getLatestPreUsePoint(Order $order)
    {
        try {
            // 履歴情報をもとに現在利用ポイントを計算し取得
            $qb = $this->createQueryBuilder('p')
                ->where('p.customer_id = :customerId')
                ->andWhere('p.order_id = :orderId')
                ->andWhere('p.plg_point_type = :pointType')
                ->setParameter('customerId', $order->getCustomer()->getId())
                ->setParameter('orderId', $order->getId())
                ->setParameter('pointType', PointHistoryHelper::STATE_PRE_USE)
                ->orderBy('p.plg_point_id', 'desc')
                ->setMaxResults(1);
            $max_use_point = $qb->getQuery()->getResult();

            // 取得値判定
            if (count($max_use_point) < 1) {
                return 0;
            }

            return $max_use_point[0]->getPlgDynamicPoint();
        } catch (NoResultException $e) {
            return 0;
        }
    }
}
