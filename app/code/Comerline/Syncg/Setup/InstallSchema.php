<?php

namespace Comerline\Syncg\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
            $table = $installer->getConnection()->newtable(
                $installer->getTable('comerline_syncg_status')
            )
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'nullable' => false,
                        'primary' => true,
                        'unsigned' => true,
                    ],
                    'ID'
                )
                ->addColumn(
                    'type',
                    Table::TYPE_SMALLINT,
                    3,
                    [],
                    'Type'
                )
                ->addColumn(
                    'mg_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'unsigned' => true,
                    ],
                    'Magento ID'
                )
                ->addColumn(
                    'g_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'unsigned' => true,
                    ],
                    'G4100 ID'
                )
                ->addColumn(
                    'status',
                    Table::TYPE_SMALLINT,
                    2,
                    [
                        'default' => 0,
                    ],
                    'status'
                )
                ->addColumn(
                    'parent_g',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'unsigned' => true,
                    ],
                    'G4100 ID'
                )
                ->addColumn(
                    'parent_mg',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'unsigned' => true,
                    ],
                    'G4100 ID'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    [
                        'nullable' => false,
                        'default' => Table::TIMESTAMP_INIT,
                    ],
                    'Update At'
                )
                ->addColumn(
                    'updated_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    [
                        'nullable' => false,
                        'default' => Table::TIMESTAMP_INIT_UPDATE,
                    ],
                    'Update At'
                )
                ->setComment('Translate Status')
                ->setOption('charset', 'utf8')
                ->setOption('collate', 'utf8_general_ci');
            $installer->getConnection()->createTable($table);

            $installer->getConnection()->addIndex(
                $installer->getTable('comerline_syncg_status'),
                $setup->getIdxName(
                    $installer->getTable('comerline_syncg_status'),
                    ['type','mg_id', 'g_id', 'status'],
                ),
                ['type','mg_id', 'g_id', 'status'],
            );
        $installer->endSetup();
    }
}
