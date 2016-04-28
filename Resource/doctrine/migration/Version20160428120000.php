<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Eccube\Application;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Plugin\Point\Entity;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Class Version20160428120000
 * @package DoctrineMigrations
 */
class Version20160428120000 extends AbstractMigration
{
    protected $app;
    // テーブル名称
    const PLG_POINT_STATUS = 'plg_point_status';

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

        // ポイントのステータステーブル
        if (!$schema->hasTable(self::PLG_POINT_STATUS)) {
            $t = $schema->createTable(self::PLG_POINT_STATUS);
            $t->addColumn('plg_point_status_id', 'integer', array('NotNull' => true, 'autoincrement' => true));
            $t->addColumn('order_id', 'integer', array('NotNull' => true, 'Default' => 0));
            $t->addColumn('customer_id', 'integer', array('NotNull' => true, 'Default' => 0));
            $t->addColumn('status', 'smallint', array('NotNull' => true, 'Default' => 0));
            $t->addColumn('del_flg', 'smallint', array('NotNull' => true, 'Default' => 0));
            $t->addColumn('point_fix_date', 'datetime', array('NotNull' => false));
            $t->setPrimaryKey(array('plg_point_status_id'));
        }
    }

    /**
     * アンインストール時処理
     * @param Schema $schema
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public function down(Schema $schema)
    {
        if ($schema->hasTable(self::PLG_POINT_STATUS)) {
            $schema->dropTable(self::PLG_POINT_STATUS);
        }
    }
}
