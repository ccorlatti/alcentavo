<?php
/*
 *  $Id: Mssql.php 7 2010-05-19 01:23:44Z corlatti@gmail.com $
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.org>.
 */

/**
 * @package     Doctrine
 * @subpackage  Import
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author      Frank M. Kromann <frank@kromann.info> (PEAR MDB2 Mssql driver)
 * @author      David Coallier <davidc@php.net> (PEAR MDB2 Mssql driver)
 * @version     $Revision: 7 $
 * @link        www.phpdoctrine.org
 * @since       1.0
 */
class Doctrine_Import_Mssql extends Doctrine_Import
{
    /**
     * lists all database sequences
     *
     * @param string|null $database
     * @return array
     */
    public function listSequences($database = null)
    {
        $query = "SELECT name FROM sysobjects WHERE xtype = 'U'";
        $tableNames = $this->conn->fetchColumn($query);

        return array_map(array($this->conn->formatter, 'fixSequenceName'), $tableNames);
    }

    /**
     * lists table relations
     *
     * Expects an array of this format to be returned with all the relationships in it where the key is 
     * the name of the foreign table, and the value is an array containing the local and foreign column
     * name
     *
     * Array
     * (
     *   [groups] => Array
     *     (
     *        [local] => group_id
     *        [foreign] => id
     *     )
     * )
     *
     * @param string $table     database table name
     * @return array
     */
    public function listTableRelations($tableName)
    {
        $relations = array();
        $sql = 'SELECT o1.name as table_name, c1.name as column_name, o2.name as referenced_table_name, c2.name as referenced_column_name, s.name as constraint_name FROM sysforeignkeys fk	inner join sysobjects o1 on fk.fkeyid = o1.id inner join sysobjects o2 on fk.rkeyid = o2.id inner join syscolumns c1 on c1.id = o1.id and c1.colid = fk.fkey inner join syscolumns c2 on c2.id = o2.id and c2.colid = fk.rkey inner join sysobjects s on fk.constid = s.id AND o1.name = \'' . $tableName . '\'';
        $results = $this->conn->fetchAssoc($sql);
        foreach ($results as $result)
        {
            $result = array_change_key_case($result, CASE_LOWER);
            $relations[] = array('table'   => $result['referenced_table_name'],
                                 'local'   => $result['column_name'],
                                 'foreign' => $result['referenced_column_name']);
        }
        return $relations;
    }

    /**
     * lists table constraints
     *
     * @param string $table     database table name
     * @return array
     */
    public function listTableColumns($table)
    {
        $sql     = 'EXEC sp_columns @table_name = ' . $this->conn->quoteIdentifier($table, true);
        $result  = $this->conn->fetchAssoc($sql);
        $columns = array();

        foreach ($result as $key => $val) {
            $val = array_change_key_case($val, CASE_LOWER);

            if (strstr($val['type_name'], ' ')) {
                list($type, $identity) = explode(' ', $val['type_name']);
            } else {
                $type = $val['type_name'];
                $identity = '';
            }

            if ($type == 'varchar') {
                $type .= '(' . $val['length'] . ')';
            }

            $val['type'] = $type;
            $val['identity'] = $identity;
            $decl = $this->conn->dataDict->getPortableDeclaration($val);

            $isIdentity = (bool) (strtoupper(trim($identity)) == 'IDENTITY');
            $isNullable = (bool) (strtoupper(trim($val['is_nullable'])) == 'NO');

            $description  = array(
                'name'          => $val['column_name'],
                'ntype'         => $type,
                'type'          => $decl['type'][0],
                'alltypes'      => $decl['type'],
                'length'        => $decl['length'],
                'fixed'         => $decl['fixed'],
                'unsigned'      => $decl['unsigned'],
                'notnull'       => $isIdentity ? true : $isNullable,
                'default'       => $val['column_def'],
                'primary'       => $isIdentity,
                'autoincrement' => $isIdentity,
            );

            $columns[$val['column_name']] = $description;
        }

        return $columns;
    }

    /**
     * lists table constraints
     *
     * @param string $table     database table name
     * @return array
     */
    public function listTableIndexes($table)
    {

    }

    /**
     * lists tables
     *
     * @param string|null $database
     * @return array
     */
    public function listTables($database = null)
    {
        $sql = "SELECT name FROM sysobjects WHERE type = 'U' AND name <> 'dtproperties' AND name <> 'sysdiagrams' ORDER BY name";

        return $this->conn->fetchColumn($sql);
    }

    /**
     * lists all triggers
     *
     * @return array
     */
    public function listTriggers($database = null)
    {
        $query = "SELECT name FROM sysobjects WHERE xtype = 'TR'";

        $result = $this->conn->fetchColumn($query);

        return $result;
    }

    /**
     * lists table triggers
     *
     * @param string $table     database table name
     * @return array
     */
    public function listTableTriggers($table)
    {
        $table = $this->conn->quote($table, 'text');
        $query = "SELECT name FROM sysobjects WHERE xtype = 'TR' AND object_name(parent_obj) = " . $table;

        $result = $this->conn->fetchColumn($query);

        return $result;
    }

    /**
     * lists table views
     *
     * @param string $table     database table name
     * @return array
     */
    public function listTableViews($table)
    {
        $keyName = 'INDEX_NAME';
        $pkName = 'PK_NAME';
        if ($this->conn->getAttribute(Doctrine::ATTR_PORTABILITY) & Doctrine::PORTABILITY_FIX_CASE) {
            if ($this->conn->getAttribute(Doctrine::ATTR_FIELD_CASE) == CASE_LOWER) {
                $keyName = strtolower($keyName);
                $pkName  = strtolower($pkName);
            } else {
                $keyName = strtoupper($keyName);
                $pkName  = strtoupper($pkName);
            }
        }
        $table = $this->conn->quote($table, 'text');
        $query = 'EXEC sp_statistics @table_name = ' . $table;
        $indexes = $this->conn->fetchColumn($query, $keyName);

        $query = 'EXEC sp_pkeys @table_name = ' . $table;
        $pkAll = $this->conn->fetchColumn($query, $pkName);

        $result = array();

        foreach ($indexes as $index) {
            if ( ! in_array($index, $pkAll) && $index != null) {
                $result[] = $this->conn->formatter->fixIndexName($index);
            }
        }

        return $result;
    }

    /**
     * lists database views
     *
     * @param string|null $database
     * @return array
     */
    public function listViews($database = null)
    {
        $query = "SELECT name FROM sysobjects WHERE xtype = 'V'";

        return $this->conn->fetchColumn($query);
    }
}