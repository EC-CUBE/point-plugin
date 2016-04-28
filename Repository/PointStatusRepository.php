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
     * 仮ステータスの受注ID一覧を返却する
     * @param $customer_id
     * @return array
     */
    public function selectOrderIdsWithUnfixedByCustomer($customer_id)
    {
        // 会員IDをもとに仮付与ポイントを計算
        $qb = $this->createQueryBuilder('p')
            ->select('p.order_id')
            ->andWhere('p.customer_id = :customer_id')
            ->andWhere('p.status = :status')
            ->setParameter('customer_id', $customer_id)
            ->setParameter('status', 0);

        $result = $qb->getQuery()->getScalarResult();

        $orderIds = array();
        foreach ($result as $item) {
            $orderIds[] = $item['order_id'];
        }

        return $orderIds;
    }

    /**
     * 確定ステータスの受注ID一覧を返却する
     * @param $customer_id
     * @return array
     */
    public function selectOrderIdsWithFixedByCustomer($customer_id)
    {
        // 会員IDをもとに仮付与ポイントを計算
        $qb = $this->createQueryBuilder('p')
            ->select('p.order_id')
            ->andWhere('p.customer_id = :customer_id')
            ->andWhere('p.status = :status')
            ->setParameter('customer_id', $customer_id)
            ->setParameter('status', 1);

        $result = $qb->getQuery()->getScalarResult();

        $orderIds = array();
        foreach ($result as $item) {
            $orderIds[] = $item['order_id'];
        }

        return $orderIds;
    }
}
