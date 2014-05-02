<?php
/*
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
 * <http://www.doctrine-project.org>.
*/

namespace Doctrine\DBAL\Migrations;

/**
 * Class which wraps a Doctrine Schema class and allows execution of the
 * migration to not load every table into the Schema, only tables used by
 * the migration
 * The provides a speed boot on databases with large numbers of tables
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @since       Specialized build
 * @author      Jeff Peters <jpeters@insidesales.com>
 */
class LazyLoadSchema extends \Doctrine\DBAL\Schema\Schema {
    /**
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    private $sm;

    protected $usedTables = array();


    public function __construct(\Doctrine\DBAL\Schema\AbstractSchemaManager $sm){
        $this->sm = $sm;
        parent::__construct(array(), array(), $this->sm->createSchemaConfig());
    }

    public function getUsedTables() {
        return $this->usedTables;
    }

    public function loadUsedTablesFrom(LazyLoadSchema &$otherSchema) {
        $usedtables = $otherSchema->getUsedTables();
        foreach ($usedtables as $tableName => $table_data) {
            $this->hasTable($tableName);
            //This does not work
            //But it would be nice to not requery the database again
            //$this->_tables[$tableName] = $table_data;
            //if (count($table_data->getColumns()) > 0){
            //    $this->_tables[$tableName] = $table_data;
            //}
        }
    }

    /**
     * Redefined function from \Doctrine\DBAL\Schema\Schema
     * because parent's function is private, not protected
     * @return string
     */
    protected function getFullQualifiedAssetName($name)
    {
        if ($this->isIdentifierQuoted($name)) {
            $name = $this->trimQuotes($name);
        }
        if (strpos($name, ".") === false) {
            $name = $this->getName() . "." . $name;
        }
        return strtolower($name);
    }

    public function setTableDetails($tableName, $force=false) {
        $tableNameFQAN = $this->getFullQualifiedAssetName($tableName);
        if (isset($this->usedTables[$tableNameFQAN]) == false
                && parent::hasTable($tableName) == false) {
            try {
                $this->usedTables[$tableName] = array();
                $table_data = $this->sm->listTableDetails($tableName);
                $this->usedTables[$tableName] = $table_data;
                if (count($table_data->getColumns()) > 0){
                    $this->_tables[$tableNameFQAN] = $table_data;
                }
            } catch (Doctrine\DBAL\Schema\SchemaException $e) {
                unset($this->_tables[$tableNameFQAN]);
            }
        }
    }

    public function hasTable($tableName) {
        $this->setTableDetails($tableName);
        return parent::hasTable($tableName);
    }

    public function getTable($tableName) {
        $this->setTableDetails($tableName);
        return parent::getTable($tableName);
    }

    public function dropTable($tableName) {
        $this->setTableDetails($tableName);
        return parent::dropTable($tableName);
    }

    public function renameTable($oldTableName, $newTableName) {
        $this->setTableDetails($oldTableName);
        $this->setTableDetails($newTableName);
        return parent::renameTable($oldTableName, $newTableName);
    }

    /**
     * Cloning a Schema triggers a deep clone of all related assets.
     *
     * @return void
     */
    public function __clone()
    {
        foreach ($this->usedTables as $k => $table) {
            $this->usedTables[$k] = clone $table;
        }
        parent::__clone();
    }

}
