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
     * @param $customerId
     * @return bool|null
     */
    public function getCalculateCurrentPointByCustomerId($customerId)
    {
        // ログテーブルから抽出するステータス
        $needStatus = array();
        $needStatus[] = PointHistoryHelper::STATE_ADD;      // 加算ポイント
        $needStatus[] = PointHistoryHelper::STATE_CURRENT;  // 手動ポイント
        $needStatus[] = PointHistoryHelper::STATE_USE;      // 利用ポイント

        try {
            $orderStatus = new OrderStatus();
            $orderStatus->setId(8);

            // ログテーブルからポイントを計算
            $qb = $this->createQueryBuilder('p');
            $qb->select('SUM(p.plg_dynamic_point) as point_sum')
                ->add('where', $qb->expr()->in('p.plg_point_type', $needStatus))
                ->andWhere('p.customer_id = :customer_id')
                ->setParameter('customer_id', $customerId);

            // 合計ポイント
            $sum_point = $qb->getQuery()->getScalarResult();

            // 情報が取得できない場合
            if (count($sum_point) < 1) {
                return false;
            }

            // 値がマイナスになった場合@todo 将来拡張
            if ($sum_point[0]['point_sum'] < 0) {
                return false;
            }

            return $sum_point[0]['point_sum'];
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * 仮ポイントを会員IDを基に返却
     *  - 合計値
     * @param $customer_id
     * @return array|bool|null
     */
    public function getAllProvisionalAddPoint($customer_id)
    {
        try {
            // 会員IDをもとに仮付与ポイントを計算
            $qb = $this->createQueryBuilder('p')
                ->select('SUM(p.plg_dynamic_point) as point_sum')
                ->andWhere('p.plg_point_type = :pointType')
                ->andWhere('p.customer_id = :customer_id')
                ->setParameter('pointType', PointHistoryHelper::STATE_PRE_ADD)
                ->setParameter('customer_id', $customer_id);

            $provisionalAddPoint = $qb->getQuery()->getScalarResult();

            // 仮ポイント取得判定
            if (count($provisionalAddPoint) < 1) {
                return false;
            }

            $provisionalAddPoint = $provisionalAddPoint[0]['point_sum'];

            // 仮ポイントがマイナスになった場合はエラー表示
            if ($provisionalAddPoint < 0) {
                return false;
            }

            return $provisionalAddPoint;
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * 仮ポイントレコードを受注情報を基に返却
     * @param $order
     * @return array|bool|null
     */
    public function getProvisionalAddPointByOrder($order)
    {
        // 必要エンティティ判定
        if (empty($order)) {
            return false;
        }

        try {
            // 受注をもとに仮付与ポイントを計算
            $qb = $this->createQueryBuilder('p')
                ->andWhere('p.plg_point_type = :pointType')
                ->andWhere('p.customer_id = :customer_id')
                ->andWhere('p.order_id = :order_id')
                ->setParameter('pointType', PointHistoryHelper::STATE_PRE_ADD)
                ->setParameter('customer_id', $order->getCustomer()->getId())
                ->setParameter('order_id', $order->getId())
                ->orderBy('p.plg_point_id', 'desc')
                ->setMaxResults(1);

            $provisionalAddPoint = $qb->getQuery()->getResult();

            // 仮ポイント取得判定
            if (count($provisionalAddPoint) < 1) {
                return false;
            }

            $provisionalAddPoint = $provisionalAddPoint[0]->getPlgDynamicPoint();

            // 仮ポイントがマイナスになった場合はエラー表示
            if ($provisionalAddPoint < 0) {
                return false;
            }

            return $provisionalAddPoint;
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * 仮ポイントを受注情報をもとに取得
     *  -   一番最後に設定された値を取得
     * @param $order
     * @return array|bool|null
     */
    public function getLastAddPointByOrder($order)
    {
        // 必要エンティティ判定
        if (empty($order)) {
            return false;
        }

        // 必要ステータス
        $needStatus = array();
        $needStatus[] = PointHistoryHelper::STATE_ADD;
        $needStatus[] = PointHistoryHelper::STATE_PRE_ADD;

        try {
            // 会員IDをもとに仮付与ポイントを計算
            $qb = $this->createQueryBuilder('p');
            $qb->add('where', $qb->expr()->in('p.plg_point_type', $needStatus))
                ->andWhere('p.customer_id = :customer_id')
                ->andWhere('p.order_id = :order_id')
                ->orderBy('p.create_date', 'desc')
                ->setParameter('customer_id', $order->getCustomer()->getId())
                ->setParameter('order_id', $order->getId())
                ->setMaxResults(1);

            $lastAddPoint = $qb->getQuery()->getResult();

            // 仮ポイント取得判定
            if (count($lastAddPoint) < 1) {
                return false;
            }

            $lastAddPoint = $lastAddPoint[0]->getPlgDynamicPoint();

            // 仮ポイントがマイナスになった場合はエラー表示
            /*
            if ($lastAddPoint < 0) {
                return false;
            }
            */

            return $lastAddPoint;
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * 最終保存手動保存保有ポイントを取得
     * @param $customer
     * @return array|bool|null
     */
    public function getLastManualPointByCustomer($customer)
    {
        // 必要エンティティ判定
        if (empty($customer)) {
            return false;
        }

        try {
            // 会員IDをもとに保有ポイントを計算
            $qb = $this->createQueryBuilder('p')
                ->andWhere('p.customer_id = :customer_id')
                ->andWhere('p.plg_point_type = :plg_point_type')
                ->orderBy('p.create_date', 'desc')
                ->setParameter('customer_id', $customer->getId())
                ->setParameter('plg_point_type', PointHistoryHelper::STATE_CURRENT)
                ->setMaxResults(1);

            $manualPoint = $qb->getQuery()->getResult();

            // 仮ポイント取得判定
            if (count($manualPoint) < 1) {
                return false;
            }


            $lastManualPoint = $manualPoint[0]->getPlgDynamicPoint();

            // 保有ポイントがマイナスになった場合はエラー表示
            if ($lastManualPoint < 0) {
                return false;
            }

            return $lastManualPoint;
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * 最終利用ポイントを受注エンティティより取得
     * @param Order $order
     * @return int|null|number
     */
    public function getLastAdjustUsePoint(Order $order)
    {
        $customer = $order->getCustomer();
        if (empty($customer)) {
            return null;
        }
        try {
            // 履歴情報をもとに現在利用ポイントを計算し取得
            $qb = $this->createQueryBuilder('p')
                //->addSelect('o')
                ->where('p.customer_id = :customerId')
                ->andwhere('p.order_id = :orderId')
                ->andwhere('p.plg_point_type = :pointType')
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

            return abs($max_use_point[0]->getPlgDynamicPoint());
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * 最終仮利用ポイントを取得
     * @param Order $order
     * @return int|null|number
     */
    public function getLastPreUsePoint(Order $order)
    {
        try {
            // 履歴情報をもとに現在利用ポイントを計算し取得
            $qb = $this->createQueryBuilder('p')
                ->where('p.customer_id = :customerId')
                ->andwhere('p.order_id = :orderId')
                ->andwhere('p.plg_point_type = :pointType')
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

            return abs($max_use_point[0]->getPlgDynamicPoint());
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * 受注情報をもとに、最終保存の仮ポイントが確定かどうか判定
     * @param $order
     * @return bool|null
     */
    public function isLastProvisionalFix($order)
    {
        // 必要エンティティ判定
        if (empty($order)) {
            return false;
        }

        // 必要ステータス設定
        $needStatus = array();
        $needStatus[] = PointHistoryHelper::STATE_PRE_ADD;
        $needStatus[] = PointHistoryHelper::STATE_ADD;

        try {
            // 受注をもとに仮付与ポイントを計算
            $qb = $this->createQueryBuilder('p');
            $qb->add('where', $qb->expr()->in('p.plg_point_type', $needStatus))
                ->andWhere('p.customer_id = :customer_id')
                ->andWhere('p.order_id = :order_id')
                ->setParameter('customer_id', $order->getCustomer()->getId())
                ->setParameter('order_id', $order->getId())
                ->orderBy('p.plg_point_id', 'desc')
                ->setMaxResults(1);

            $provisionalAddPoint = $qb->getQuery()->getResult();

            // 仮ポイント取得判定
            if (count($provisionalAddPoint) < 1) {
                return false;
            }

            $pointType = $provisionalAddPoint[0]->getPlgPointType();

            if ($pointType == PointHistoryHelper::STATE_ADD) {
                return true;
            }

            return false;
        } catch (NoResultException $e) {
            return null;
        }
    }
}
