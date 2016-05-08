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
namespace Plugin\Point\Form\Type;

use Plugin\Point\Entity\PointInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class PointInfoType
 * @package Plugin\Point\Form\Type
 */
class PointInfoType extends AbstractType
{
    /** @var \Eccube\Application */
    protected $app;
    /** @var array */
    protected $orderStatus;

    /**
     * PointInfoType constructor.
     * @param \Eccube\Application $app
     */
    public function __construct(\Eccube\Application $app)
    {
        $this->app = $app;
        // 全受注ステータス ID・名称 取得保持
        $this->orderStatus = array();
        $this->app['orm.em']->getFilters()->enable('incomplete_order_status_hidden');
        foreach ($this->app['eccube.repository.order_status']->findAllArray() as $id => $node) {
            $this->orderStatus[$id] = $node['name'];
        }
        $this->app['orm.em']->getFilters()->disable('incomplete_order_status_hidden');
    }

    /**
     * Build config type form
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // 初期化処理(子要素をセット)
        if ($this->isEmptyAddStatus($builder)) {
            // データーが一件もない
            $this->setNewAddStatusEntities($builder);
        } else {
            // 既に登録データがある
            $this->setEditAddStatusEntities($builder);
        }

        $builder
            ->add(
                'plg_point_info_id',
                'hidden',
                array(
                    'required' => false,
                    'mapped' => true,
                )
            )
            ->add(
                'plg_add_point_status',
                'choice',
                array(
                    'label' => 'ポイント付与タイミング',
                    'choices' => $this->orderStatus,
                    'mapped' => true,
                    'expanded' => false,
                    'multiple' => false,
                )
            )
            ->add(
                'plg_calculation_type',
                'choice',
                array(
                    'label' => 'ポイント計算方法',
                    'choices' => array(
                        \Plugin\Point\Entity\PointInfo::POINT_CALCULATE_SUBTRACTION => '利用ポイント減算',
                        \Plugin\Point\Entity\PointInfo::POINT_CALCULATE_NORMAL => '減算なし',
                    ),
                    'mapped' => true,
                    'expanded' => false,
                    'multiple' => false,
                )
            )
            ->add(
                'plg_basic_point_rate',
                'text',
                array(
                    'label' => '基本ポイント付与率',
                    'required' => true,
                    'mapped' => true,
                    'empty_data' => null,
                    'attr' => array(
                        'placeholder' => '「商品毎の付与率」が設定されていない場合に本値が適用されます。( ％ )',
                    ),
                    'constraints' => array(
                        new Assert\Regex(
                            array(
                                'pattern' => "/^\d+$/u",
                                'message' => 'form.type.numeric.invalid',
                            )
                        ),
                        new Assert\NotEqualTo(
                            array(
                                'value' => 0,
                                'message' => 'admin.point.validate.zero.error',
                            )
                        ),
                        new Assert\Range(
                            array(
                                'min' => 0,
                                'max' => 100,
                            )
                        ),
                    ),
                )
            )
            ->add(
                'plg_point_conversion_rate',
                'text',
                array(
                    'label' => 'ポイント換算率',
                    'required' => true,
                    'mapped' => true,
                    'empty_data' => null,
                    'attr' => array(
                        'placeholder' => 'ポイント利用時の換算値です( 1 → 1pt = 1円 )',
                    ),
                    'constraints' => array(
                        new Assert\Regex(
                            array(
                                'pattern' => "/^\d+$/u",
                                'message' => 'form.type.numeric.invalid',
                            )
                        ),
                        new Assert\NotEqualTo(
                            array(
                                'value' => 0,
                                'message' => 'admin.point.validate.zero.error',
                            )
                        ),
                        new Assert\Range(
                            array(
                                'min' => 0,
                                'max' => 10000,
                            )
                        ),
                    ),
                )
            )
            ->add(
                'plg_round_type',
                'choice',
                array(
                    'label' => '端数計算方法',
                    'choices' => array(
                        \Plugin\Point\Entity\PointInfo::POINT_ROUND_CEIL => '切り上げ',
                        \Plugin\Point\Entity\PointInfo::POINT_ROUND_FLOOR => '切り捨て',
                        \Plugin\Point\Entity\PointInfo::POINT_ROUND_ROUND => '四捨五入',
                    ),
                    'mapped' => true,
                    'expanded' => false,
                    'multiple' => false,
                )
            )
            ->addEventSubscriber(new \Eccube\Event\FormEventSubscriber());
    }

    /**
     * 子要素が空かどうかを判定
     * @param FormBuilderInterface $builder
     * @return bool
     */
    protected function isEmptyAddStatus(FormBuilderInterface $builder)
    {
        // ポスト値を判定
        $entity = $builder->getData();

        if (!$entity) {
            return true;
        }

        if (count($entity->getPlgAddPointStatus()) < 1) {
            return true;
        }

        return false;
    }

    /**
     * ポイント付与受注ステータスエンティティを受注ステータス分セット
     *  - 子要素がある場合
     * @param FormBuilderInterface $builder
     * @return bool
     */
    protected function setEditAddStatusEntities(FormBuilderInterface $builder)
    {
        // 受注ステータスが存在しない際
        if (count($this->orderStatus) < 1) {
            return false;
        }

        // PointInfoAddStatusのエンティティを取得
        $entity = $builder->getData();

        // PointInfoにフォーム取得基本情報をセット
        $pointInfo = new PointInfo();
        $pointInfo->setPlgBasicPointRate($entity->getPlgBasicPointRate());
        $pointInfo->setPlgPointConversionRate($entity->getPlgPointConversionRate());
        $pointInfo->setPlgRoundType($entity->getPlgRoundType());
        $pointInfo->setPlgCalculationType($entity->getPlgCalculationType());
        $pointInfo->setPlgAddPointStatus($entity->getPlgAddPointStatus());

        // 編集値をフォームに再格納
        $builder->setData($pointInfo);

        return true;
    }

    /**
     * 新規ポイント付与受注ステータスエンティティを受注ステータス分セット
     *  - 子要素がない場合
     * @param FormBuilderInterface $builder
     * @return bool
     */
    protected function setNewAddStatusEntities(FormBuilderInterface $builder)
    {
        // 受注ステータスが存在しない際
        if (count($this->orderStatus) < 1) {
            return false;
        }

        return true;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Plugin\Point\Entity\PointInfo',
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'admin_point_info';
    }
}
