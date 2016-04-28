<?php


namespace Plugin\Point\Helper\PointHistoryHelper;

use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Plugin\Point\Entity\PointSnapshot;
use Plugin\Point\Entity\Point;

/**
 * ポイント履歴ヘルパー
 * Class PointHistoryHelper
 * @package Plugin\Point\Helper\PointHistoryHelper
 */
class PointHistoryHelper
{
    // 保存内容(場所)
    const HISTORY_MESSAGE_MANUAL_EDIT = 'ポイント手動編集';
    const HISTORY_MESSAGE_EDIT = 'ポイント購入完了登録';
    const HISTORY_MESSAGE_USE_POINT = 'ポイント利用仮登録';
    const HISTORY_MESSAGE_ORDER_EDIT = 'ポイント管理画面受注ステータス変更保存';
    const HISTORY_MESSAGE_ORDER_CANCEL = 'ポイント管理画面受注ステータスキャンセル保存';

    // 保存内容(ポイント種別)
    const HISTORY_MESSAGE_TYPE_CURRENT = '保有';
    const HISTORY_MESSAGE_TYPE_PRE_ADD = '付与(仮)';
    const HISTORY_MESSAGE_TYPE_ADD = '付与(確定)';
    const HISTORY_MESSAGE_TYPE_PRE_USE = '仮利用';
    const HISTORY_MESSAGE_TYPE_USE = '利用';
    const HISTORY_MESSAGE_TYPE_ADJUST_USE = '利用調整';

    // 保存内容(ポイント種別)
    const STATE_CURRENT = 1;
    const STATE_PRE_ADD = 2;
    const STATE_ADD = 3;
    const STATE_USE = 4;
    const STATE_PRE_USE = 5;

    protected $app;                 // アプリケーション
    protected $entities;            // 保存時エンティティコレクション
    protected $currentActionName;   // 保存時保存動作(場所 + ポイント種別)
    protected $historyType;         // 保存種別( integer )
    protected $historyActionType;   // 保存ポイント種別( string )

    /**
     * PointHistoryHelper constructor.
     */
    public function __construct()
    {
        $this->app = \Eccube\Application::getInstance();
        // 全てINSERTのために保存用エンティティを再生成
        $this->refreshEntity();
        // ポイント基本情報設定値
        $this->entities['PointInfo'] = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();
    }

    /**
     * 履歴保存エンティティを新規作成
     *  - 履歴では常にINSERTのため
     */
    public function refreshEntity()
    {
        $this->entities = array();
        $this->entities['SnapShot'] = new PointSnapshot();
        $this->entities['Point'] = new Point();
        $this->entities['PointInfo'] = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();
    }

    /**
     * 計算に必要なエンティティを追加
     * @param $entity
     */
    public function addEntity($entity)
    {
        $entityName = explode('\\', get_class($entity));
        $this->entities[array_pop($entityName)] = $entity;

        return;
    }

    /**
     * 保持エンティティコレクションを返却
     * @return mixed
     */
    public function getEntities()
    {
        return $this->entities;
    }

    /**
     * キーをもとに該当エンティティを削除
     * @param $targetName
     */
    public function removeEntity($targetName)
    {
        if (in_array($targetName, $this->entities[$targetName], true)) {
            unset($this->entities[$targetName]);
        }

        return;
    }

    /**
     * 仮加算ポイント情報を履歴登録
     *  - 会員管理
     * @param $point
     */
    public function saveManualProvisionalAddPoint($point)
    {
        $this->currentActionName = self::HISTORY_MESSAGE_MANUAL_EDIT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_PRE_ADD;
        $this->historyType = self::STATE_PRE_ADD;
        $this->saveHistoryPoint($point);
    }

    /**
     * 仮加算ポイント情報を履歴登録
     *  - 受注管理画面
     * @param $point
     */
    public function saveProvisionalAddPoint($point)
    {
        $this->currentActionName = self::HISTORY_MESSAGE_ORDER_EDIT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_PRE_ADD;
        $this->historyType = self::STATE_PRE_ADD;
        $this->saveHistoryPoint($point);
    }

    /**
     * 加算ポイントの履歴登録
     *  - 受注管理画面
     * @param $point
     */
    public function saveFixProvisionalAddPoint($point)
    {
        $this->currentActionName = self::HISTORY_MESSAGE_ORDER_EDIT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_ADD;
        $this->historyType = self::STATE_ADD;
        $this->saveHistoryPoint($point);
    }

    /**
     * 加算ポイントの履歴登録
     *  - フロント画面
     * @param $point
     */
    public function saveShoppingFixProvisionalAddPoint($point)
    {
        $this->currentActionName = self::HISTORY_MESSAGE_EDIT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_ADD;
        $this->historyType = self::STATE_ADD;
        $this->saveHistoryPoint($point);
    }

    /**
     * 加算ポイント情報を履歴登録
     * @param $point
     */
    public function cancelAddPoint($point)
    {
        $this->currentActionName = self::HISTORY_MESSAGE_EDIT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_ADD;
        $this->historyType = self::STATE_ADD;
        $this->saveHistoryPoint($point);
    }

    /**
     * 仮加算ポイント確定処理
     *  - 管理画面
     * @param $point
     * @return bool
     */
    public function fixProvisionalAddPoint($point)
    {
        // 引数判定
        if (empty($point)) {
            return false;
        }

        $this->currentActionName = self::HISTORY_MESSAGE_ORDER_EDIT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_PRE_ADD;
        $this->historyType = self::STATE_PRE_ADD;
        $this->saveHistoryPoint(abs($point) * -1);
    }

    /**
     * 仮加算ポイント確定処理
     *  - フロント画面
     * @param $point
     * @return bool
     */
    public function fixShoppingProvisionalAddPoint($point)
    {
        // 引数判定
        if (empty($point)) {
            return false;
        }

        $this->currentActionName = self::HISTORY_MESSAGE_ORDER_EDIT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_PRE_ADD;
        $this->historyType = self::STATE_PRE_ADD;
        $this->saveHistoryPoint(abs($point) * -1);
    }

    /**
     * 仮利用ポイント履歴登録
     *  - フロント画面
     * @param $point
     */
    public function savePreUsePoint($point)
    {
        $this->currentActionName = self::HISTORY_MESSAGE_USE_POINT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_PRE_USE;
        $this->historyType = self::STATE_PRE_USE;
        $this->saveHistoryPoint($point);
    }

    /**
     * 利用ポイント履歴登録
     *  - フロント画面
     * @param $point
     */
    public function saveUsePoint($point)
    {
        $this->currentActionName = self::HISTORY_MESSAGE_EDIT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_USE;
        $this->historyType = self::STATE_USE;
        $this->saveHistoryPoint($point);
    }

    /**
     * 手動登録(管理者)ポイント履歴登録
     *  - 管理画面・会員登録/編集
     * @param $point
     */
    public function saveManualPoint($point)
    {
        $this->currentActionName = self::HISTORY_MESSAGE_MANUAL_EDIT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_CURRENT;
        $this->historyType = self::STATE_CURRENT;
        $this->saveHistoryPoint($point);
    }

    /**
     * 受注画面調整利用ポイント保存
     * @param $point
     */
    public function saveUsePointAdjustOrderHistory($point)
    {
        $this->currentActionName = self::HISTORY_MESSAGE_MANUAL_EDIT;
        $this->historyActionType = self::HISTORY_MESSAGE_TYPE_ADJUST_USE;
        $this->historyType = self::STATE_USE;
        $this->saveHistoryPoint($point);
    }

    /**
     * 履歴登録共通処理
     * @param $point
     * @return bool
     */
    protected function saveHistoryPoint($point)
    {
        // 引数判定
        if (!$this->hasEntity('Customer')) {
            return false;
        }
        if (!$this->hasEntity('PointInfo')) {
            return false;
        }
        if (isset($this->entities['Order'])) {
            $this->entities['Point']->setOrder($this->entities['Order']);
        }
        $this->entities['Point']->setPlgPointId(null);
        $this->entities['Point']->setCustomer($this->entities['Customer']);
        $this->entities['Point']->setPointInfo($this->entities['PointInfo']);
        $this->entities['Point']->setPlgDynamicPoint($point);
        $this->entities['Point']->setPlgPointActionName($this->historyActionType.$this->currentActionName);
        $this->entities['Point']->setPlgPointType($this->historyType);
        try {
            $this->app['orm.em']->persist($this->entities['Point']);
            $this->app['orm.em']->flush($this->entities['Point']);
            $this->app['orm.em']->clear($this->entities['Point']);
        } catch (DatabaseObjectNotFoundException $e) {
            return false;
        }
    }

    /**
     * スナップショット情報登録
     * @param $point
     * @return bool
     */
    public function saveSnapShot($point)
    {
        // 必要エンティティ判定
        if (!$this->hasEntity('Customer')) {
            return false;
        }
        $this->entities['SnapShot']->setPlgPointSnapshotId(null);
        $this->entities['SnapShot']->setCustomer($this->entities['Customer']);
        $this->entities['SnapShot']->setPlgPointAdd($point['add']);
        $this->entities['SnapShot']->setPlgPointCurrent($point['current']);
        $this->entities['SnapShot']->setPlgPointUse($point['use']);
        $this->entities['SnapShot']->setPlgPointSnapActionName($this->currentActionName);
        try {
            $this->app['orm.em']->persist($this->entities['SnapShot']);
            $this->app['orm.em']->flush($this->entities['SnapShot']);
        } catch (DatabaseObjectNotFoundException $e) {
            return false;
        }
    }

    /**
     * エンティティの有無を確認
     *  - 引数で渡された値をキーにエンティティの有無を確認
     * @param $name
     * @return bool
     */
    protected function hasEntity($name)
    {
        if (isset($this->entities[$name])) {
            return true;
        }

        return false;
    }
}
