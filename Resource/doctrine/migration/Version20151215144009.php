<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Eccube\Application;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Plugin\Point\Entity;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Class Version20151215144009
 * @package DoctrineMigrations
 */
class Version20151215144009 extends AbstractMigration
{
    protected $app;
    // テーブル名称
    const PLG_POINT_INFO = 'plg_point_info';
    const PLG_POINT = 'plg_point';
    const PLG_POINT_CUSTOMER = 'plg_point_customer';
    const PLG_POINT_PRODUCT_RATE = 'plg_point_product_rate';
    const PLG_POINT_SNAP_SHOT = 'plg_point_snapshot';
    const PLG_POINT_DTB_PAGE_LAYOUT = 'PageLayout';

    public function __construct(){
        $this->app = \Eccube\Application::getInstance();
    }

    /**
     * インストール時処理
     * @param Schema $schema
     * @return bool
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs

        // ポイント機能基本情報格納テーブルを追加
        if (!$schema->hasTable(self::PLG_POINT_INFO)) {
            $t = $schema->createTable(self::PLG_POINT_INFO);
            $t->addColumn('plg_point_info_id', 'integer', array('NotNull' => true, 'autoincrement' => true));
            $t->addColumn('plg_add_point_status', 'smallint', array('NotNull' => true, 'Default' => 0));
            $t->addColumn('plg_basic_point_rate', 'integer', array('NotNull' => false, 'Default' => null));
            $t->addColumn('plg_point_conversion_rate', 'integer', array('NotNull' => false, 'Default' => null));
            $t->addColumn('plg_round_type', 'smallint', array('NotNull' => true, 'Default' => 0));
            $t->addColumn('plg_calculation_type', 'smallint', array('NotNull' => true, 'Default' => 0));
            $t->addColumn('create_date', 'datetime', array('NotNull' => true));
            $t->addColumn('update_date', 'datetime', array('NotNull' => true));
            $t->setPrimaryKey(array('plg_point_info_id'));
        }

        // ポイントテーブル
        if (!$schema->hasTable(self::PLG_POINT)) {
            $t = $schema->createTable(self::PLG_POINT);
            $t->addColumn('plg_point_id', 'integer', array('NotNull' => true, 'autoincrement' => true));
            $t->addColumn('plg_point_info_id', 'integer', array('NotNull' => false));
            $t->addColumn('plg_point_product_rate_id', 'integer', array('NotNull' => false));
            $t->addColumn('plg_dynamic_point', 'integer', array('NotNull' => false, 'Default' => 0));
            $t->addColumn('order_id', 'integer', array('NotNull' => false, 'Default' => 0));
            $t->addColumn('customer_id', 'integer', array('NotNull' => false, 'Default' => 0));
            $t->addColumn('plg_point_type', 'smallint', array('NotNull' => true, 'Default' => 0));
            $t->addColumn(
                'plg_point_action_name',
                'string',
                array('length' => '255', 'NotNull' => false, 'Default' => null)
            );
            $t->addColumn('create_date', 'datetime', array('NotNull' => true));
            $t->addColumn('update_date', 'datetime', array('NotNull' => true));
            $t->setPrimaryKey(array('plg_point_id'));
        }

        // 会員ポイントテーブル
        if (!$schema->hasTable(self::PLG_POINT_CUSTOMER)) {
            $t = $schema->createTable(self::PLG_POINT_CUSTOMER);
            $t->addColumn('plg_point_customer_id', 'integer', array('NotNull' => true, 'autoincrement' => true));
            $t->addColumn('plg_point_current', 'integer', array('NotNull' => false, 'Default' => 0));
            $t->addColumn('customer_id', 'integer', array('NotNull' => false, 'Default' => 0));
            $t->addColumn('create_date', 'datetime', array('NotNull' => true));
            $t->addColumn('update_date', 'datetime', array('NotNull' => true));
            $t->setPrimaryKey(array('plg_point_customer_id'));
        }

        // 商品ポイント付与率テーブル
        if (!$schema->hasTable(self::PLG_POINT_PRODUCT_RATE)) {
            $t = $schema->createTable(self::PLG_POINT_PRODUCT_RATE);
            $t->addColumn('plg_point_product_rate_id', 'integer', array('NotNull' => true, 'autoincrement' => true));
            $t->addColumn('product_id', 'integer', array('NotNull' => true, 'Default' => 0));
            $t->addColumn('plg_point_product_rate', 'integer', array('NotNull' => false));
            $t->addColumn('create_date', 'datetime', array('NotNull' => true));
            $t->addColumn('update_date', 'datetime', array('NotNull' => true));
            $t->setPrimaryKey(array('plg_point_product_rate_id'));
        }

        // ポイント履歴スナップショットテーブル
        if (!$schema->hasTable(self::PLG_POINT_SNAP_SHOT)) {
            $t = $schema->createTable(self::PLG_POINT_SNAP_SHOT);
            $t->addColumn('plg_point_snapshot_id', 'integer', array('NotNull' => true, 'autoincrement' => true));
            $t->addColumn('plg_point_use', 'integer', array('NotNull' => false, 'Default' => null));
            $t->addColumn('plg_point_current', 'integer', array('NotNull' => false, 'Default' => null));
            $t->addColumn('plg_point_add', 'integer', array('NotNull' => false, 'Default' => null));
            $t->addColumn(
                'plg_point_snap_action_name',
                'string',
                array('length' => '255', 'NotNull' => false, 'Default' => null)
            );
            $t->addColumn('order_id', 'integer', array('NotNull' => false, 'Default' => 0));

            $t->addColumn('customer_id', 'integer', array('NotNull' => false, 'Default' => 0));
            $t->addColumn('create_date', 'datetime', array('NotNull' => true));
            $t->addColumn('update_date', 'datetime', array('NotNull' => true));
            $t->setPrimaryKey(array('plg_point_snapshot_id'));
        }
    }

    /**
     * アンインストール時処理
     * @param Schema $schema
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public function down(Schema $schema)
    {
        if ($schema->hasTable(self::PLG_POINT_INFO)) {
            $schema->dropTable(self::PLG_POINT_INFO);
        }
        if ($schema->hasTable(self::PLG_POINT)) {
            $schema->dropTable(self::PLG_POINT);
        }
        if ($schema->hasTable(self::PLG_POINT_CUSTOMER)) {
            $schema->dropTable(self::PLG_POINT_CUSTOMER);
        }
        if ($schema->hasTable(self::PLG_POINT_PRODUCT_RATE)) {
            $schema->dropTable(self::PLG_POINT_PRODUCT_RATE);
        }
        if ($schema->hasTable(self::PLG_POINT_SNAP_SHOT)) {
            $schema->dropTable(self::PLG_POINT_SNAP_SHOT);
        }
    }
}
