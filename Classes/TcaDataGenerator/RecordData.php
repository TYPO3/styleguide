<?php
namespace TYPO3\CMS\Styleguide\TcaDataGenerator;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Create data for a specific table and its child tables
 */
class RecordData
{
    /**
     * List of field generators to be called for values.
     * Order is important: Each class is called top-bottom until one returns
     * true on match(), then generate() is called.
     *
     * @var array
     */
    protected $fieldValueGenerators = [
        FieldGenerator\TypeInput::class,
    ];

    /**
     * Generate data for a given table and insert into database
     *
     * @param string $tableName The tablename to create data for
     * @param int $pid Optional page id of new record. If not given, table is a "main" table and pid is determined ottherwise
     * @return array
     * @throws Exception
     */
    public function generate(string $tableName, int $pid = NULL): array
    {
        if (is_null($pid)) {
            $pid = $this->findPidOfMainTableRecord($tableName);
        }
        $fieldValues = [
            'pid' => $pid,
        ];
        $tca = $GLOBALS['TCA'][$tableName];
        foreach ($tca['columns'] as $fieldName => $fieldConfig) {
            $criteria = [
                'tableName' => $tableName,
                'fieldName' => $fieldName,
                'fieldConfig' => $fieldConfig,
            ];
            foreach ($this->fieldValueGenerators as $fieldValueGenerator) {
                $generator = GeneralUtility::makeInstance($fieldValueGenerator);
                if (!$generator instanceof FieldGeneratorInterface) {
                    throw new Exception(
                        'Field value generator ' . $fieldValueGenerator . ' must implement FieldGeneratorInterface',
                        1457693564
                    );
                }
                if ($generator->match($criteria)) {
                    $fieldValues[$fieldName] = $generator->generate($criteria);
                    break;
                }
            }
        }
        $database = $this->getDatabase();
        $database->exec_INSERTquery($tableName, $fieldValues);
        $fieldValues['uid'] = $database->sql_insert_id();
        return $fieldValues;
    }

    /**
     * "Main" tables have a single page they are located on with their possible children.
     * The methods find this page by getting the highest uid of a page where field
     * tx_styleguide_containsdemo is set to given table name.
     *
     * @param string $tableName
     * @return int
     * @throws Exception
     */
    protected function findPidOfMainTableRecord(string $tableName): int
    {
        $database = $this->getDatabase();
        $row = $database->exec_SELECTgetSingleRow(
            'uid',
            'pages',
            'tx_styleguide_containsdemo=' . $database->fullQuoteStr($tableName, 'pages')
                . BackendUtility::deleteClause('pages'),
            '',
            'pid DESC'
        );
        if (!count($row) === 1) {
            throw new Exception(
                'Found no page for main table ' . $tableName,
                1457690656
            );
        }
        return (int)$row['uid'];
    }

    /**
     * @return DatabaseConnection
     */
    protected function getDatabase(): DatabaseConnection
    {
        return $GLOBALS['TYPO3_DB'];
    }

}