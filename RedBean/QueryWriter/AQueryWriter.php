<?php
/**
 * RedBean Abstract Query Writer
 * @file 		RedBean/QueryWriter/AQueryWriter.php
 * @description
 *					Represents an abstract Database to RedBean
 *					To write a driver for a different database for RedBean
 *					Contains a number of functions all implementors can
 *					inherit or override.
 *
 * @author			Gabor de Mooij
 * @license			BSD
 *
 *
 * (c) G.J.G.T. (Gabor) de Mooij
 * This source file is subject to the BSD/GPLv2 License that is bundled
 * with this source code in the file license.txt.
 */

abstract class RedBean_QueryWriter_AQueryWriter {


	/**
	 *
	 * @var RedBean_IBeanFormatter
	 * Holds the bean formatter to be used for applying
	 * table schema.
	 */
	public $tableFormatter;
  
  
	/**
	 * @var array
	 * Supported Column Types.
	 */
	public $typeno_sqltype = array();
	
	/**
	 *
	 * @var RedBean_Adapter_DBAdapter
	 * Holds a reference to the database adapter to be used.
	 */
	protected $adapter;

	/**
	 * @var string
	 * Indicates the field name to be used for primary keys;
	 * default is 'id'.
	 */
  protected $idfield = "id";

	/**
	 * @var string
	 * default value to for blank field (passed to PK for auto-increment)
	 */
  protected $defaultValue = 'NULL';
  
	/**
	 * @var string
	 * character to escape keyword table/column names
	 */
  protected $quoteCharacter = '';
	
	/**
	 * Do everything that needs to be done to format a table name.
	 *
	 * @param string $name of table
	 * 
	 * @return string table name
	 */
	public function safeTable($name, $noQuotes = false) {
		$name = $this->getFormattedTableName($name);
		$name = $this->check($name);
		if (!$noQuotes) $name = $this->noKW($name);
		return $name;
	}
	
	/**
	 * Do everything that needs to be done to format a column name.
	 *
	 * @param string $name of column
	 *
	 * @return string $column name
	 */
	public function safeColumn($name, $noQuotes = false) {
		$name = $this->check($name);
		if (!$noQuotes) $name = $this->noKW($name);
		return $name;
	}
	
	/**
	 * Returns the sql that should follow an insert statement.
	 *
	 * @param string $table name
	 *
	 * @return string sql
	 */
  protected function getInsertSuffix ($table) {
    return "";
  }
	
	/**
	 * Returns the string identifying a table for a given type.
	 *
	 * @param string $type
	 *
	 * @return string $table
	 */
	public function getFormattedTableName($type) {
		if ($this->tableFormatter) return $this->tableFormatter->formatBeanTable($type);
		return $type;
	}

	/**
	 * Sets the Bean Formatter to be used to handle
	 * custom/advanced DB<->Bean
	 * Mappings. This method has no return value.
	 *
	 * @param RedBean_IBeanFormatter $beanFormatter the bean formatter
	 * 
	 * @return void
	 */
	public function setBeanFormatter( RedBean_IBeanFormatter $beanFormatter ) {
		$this->tableFormatter = $beanFormatter;
	}
	
	/**
	 * Get sql column type.
	 *
	 * @param integer $type constant
	 *
	 * @return string sql type
	 */
	public function getFieldType( $type = "" ) {
		return array_key_exists($type, $this->typeno_sqltype) ? $this->typeno_sqltype[$type] : "";
	}

	/**
	 * Returns the column name that should be used
	 * to store and retrieve the primary key ID.
	 *
	 * @param string $type type of bean to get ID Field for
	 *
	 * @return string $idfieldtobeused ID field to be used for this type of bean
	 */
	public function getIDField( $type ) {
		$nArgs = func_num_args();
		if ($nArgs>1) $safe = func_get_arg(1); else $safe = false;
		if ($this->tableFormatter) return $this->tableFormatter->formatBeanID($type);
		return $safe ? $this->safeColumn($this->idfield) : $this->idfield;
	}
	
	/**
	 * Checks table name or column name.
	 *
	 * @param string $table table string
	 *
	 * @return string $table escaped string
	 */
	public function check($table) {
		// if (strpos($table, '`')!==false || strpos($table, '"')!==false) { // maybe this?
		if ($this->quoteCharacter && strpos($table, $this->quoteCharacter)!==false) {
		  throw new Redbean_Exception_Security("Illegal chars in table name");
    }
		return $this->adapter->escape($table);
	}
	
	/**
	 * Puts keyword escaping symbols around string.
	 *
	 * @param string $str keyword
	 *
	 * @return string $keywordSafeString escaped keyword
	 */
	public function noKW($str) {
		$q = $this->quoteCharacter;
		return $q.$str.$q;
	}
	
	/**
	 * Adds a column of a given type to a table.
	 *
	 * @param string  $table  name of the table
	 * @param string  $column name of the column
	 * @param integer $type   type
	 *
	 */
	public function addColumn( $table, $column, $type ) {
		$table = $this->safeTable($table);
		$column = $this->safeColumn($column);
		$type = $this->getFieldType($type);
		$sql = "ALTER TABLE $table ADD $column $type ";
		$this->adapter->exec( $sql );
	}
	
	/**
	 * Update a record using a series of update values.
	 *
	 * @param string  $table		  table
	 * @param array   $updatevalues update values
	 * @param integer $id			  primary key for record
	 */
	public function updateRecord( $table, $updatevalues, $id) {
		if (!$id) {
			$insertcolumns =  $insertvalues = array();
			foreach($updatevalues as $pair) {
				$insertcolumns[] = $pair["property"];
				$insertvalues[] = $pair["value"];
			}
			return $this->insertRecord($table,$insertcolumns,array($insertvalues));
		}
		if ($id && !count($updatevalues)) return $id;
		$idfield = $this->getIDField($table, true);
		$table = $this->safeTable($table);
		$sql = "UPDATE $table SET ";
		$p = $v = array();
		foreach($updatevalues as $uv) {
			$p[] = " {$this->safeColumn($uv["property"])} = ? ";
			//$v[]=strval( $uv["value"] );
			$v[]=$uv["value"];
		}
		$sql .= implode(",", $p ) ." WHERE $idfield = ".intval($id);
		$this->adapter->exec( $sql, $v );
		return $id;
	}

	/**
	 * Inserts a record into the database using a series of insert columns
	 * and corresponding insertvalues. Returns the insert id.
	 *
	 * @param string $table			  table to perform query on
	 * @param array  $insertcolumns columns to be inserted
	 * @param array  $insertvalues  values to be inserted
	 *
	 * @return integer $insertid	  insert id from driver, new record id
	 */
	protected function insertRecord( $table, $insertcolumns, $insertvalues ) {
		$default = $this->defaultValue;
		$idfield = $this->getIDField($table, true);
		$suffix = $this->getInsertSuffix($table);
		$table = $this->safeTable($table);
		if (count($insertvalues)>0 && is_array($insertvalues[0]) && count($insertvalues[0])>0) {
			foreach($insertcolumns as $k=>$v) {
				$insertcolumns[$k] = $this->safeColumn($v);
			}
			$insertSQL = "INSERT INTO $table ( $idfield, ".implode(",",$insertcolumns)." ) VALUES ";
			$insertSQL .= "( $default, ". implode(",",array_fill(0,count($insertcolumns)," ? "))." ) $suffix";
			$first=true;
			
			foreach($insertvalues as $i=>$insertvalue) {
				$ids[] = $this->adapter->getCell( $insertSQL, $insertvalue, $i );
			}
			$result = count($ids)===1 ? array_pop($ids) : $ids;
		}
		else {
			$result = $this->adapter->getCell( "INSERT INTO $table ($idfield) VALUES($default) $suffix");
		}
		if ($suffix) return $result;
	   $last_id = $this->adapter->getInsertID();
		return ($this->adapter->getErrorMsg()=="" ?  $last_id : 0);
	}
	
	

	public function selectRecord( $type, $conditions, $addSql=null, $delete=null, $inverse=false ) {
		if (!is_array($conditions)) throw new Exception("Conditions must be an array");
		
		$table = $this->safeTable($type);
		$sqlConditions = array();
		$bindings=array();
		foreach($conditions as $column=>$values) {
			$sql = $this->safeColumn($column);
			$sql .= " ".($inverse ? " NOT ":"")." IN ( ";
			$sql .= implode(",",array_fill(0,count($values),"?")).") ";
			$sqlConditions[] = $sql;
			if (!is_array($values)) throw new Exception("Values must be an array");
			foreach($values as $k=>$v) {
				$values[$k]=strval($v);
			}
			$bindings = array_merge($bindings,$values);
		}
		//$addSql can be either just a string or array($sql, $bindings)
		if (is_array($addSql)) {
			$bindings = array_merge($bindings,$addSql[1]);	
			$addSql = $addSql[0];
		}
		$sql="";
		if (count($sqlConditions)>0) {
			$sql = implode(" AND ",$sqlConditions);
			$sql = " WHERE ( $sql ) ";
			if ($addSql) $sql .= " AND $addSql ";
		}
		elseif ($addSql) {
			$sql = " WHERE ".$addSql;
		}
		$sql = (($delete) ? "DELETE FROM " : "SELECT * FROM ").$table.$sql;
		$rows = $this->adapter->get($sql,$bindings);
		return $rows;
	}



	public function createView($referenceTable, $constraints, $viewID) {
		
		$viewID = $this->safeTable($viewID,true);
		$safeReferenceTable = $this->safeTable($referenceTable);

		try{ $this->adapter->exec("DROP VIEW $viewID"); }catch(Exception $e){}

		$columns = array_keys( $this->getColumns( $referenceTable ) );

		$referenceTable = ($referenceTable);
		$joins = array();
		foreach($constraints as $table=>$constraint) {
			$safeTable = $this->safeTable($table);
			$addedColumns = array_keys($this->getColumns($table));
			foreach($addedColumns as $addedColumn) {
				$newColName = $addedColumn."_of_".$table;
				$newcolumns[] = $this->safeTable($table).".".$this->safeColumn($addedColumn) . " AS ".$this->safeColumn($newColName);
			}
			if (count($constraint)!==2) throw Exception("Invalid VIEW CONSTRAINT");
			$referenceColumn = $constraint[0];
			$compareColumn = $constraint[1];
			$join = $referenceColumn." = ".$compareColumn;
			$joins[] = " LEFT JOIN $safeTable ON $join ";
		}
		
		$joins = implode(" ", $joins);
		foreach($columns as $k=>$column) {
			$columns[$k]=$safeReferenceTable.".".$this->safeColumn($column);
		}
		$columns = implode("\n,",array_merge($newcolumns,$columns));
		$sql = "CREATE VIEW $viewID AS SELECT $columns FROM $safeReferenceTable $joins ";
		
		$this->adapter->exec($sql);
		return true;
	}




	
	/**
	 * Truncates a table
	 *
	 * @param string $table
	 */
	public function wipe($table) {
		$table = $this->safeTable($table);
		$sql = "TRUNCATE $table ";
		$this->adapter->exec($sql);
	}

	/**
	 * Counts rows in a table.
	 *
	 * @param string $beanType
	 *
	 * @return integer $numRowsFound
	 */
	public function count($beanType) {
		$table = $this->safeTable($beanType);
		$sql = "SELECT count(*) FROM $table ";
		return (int) $this->adapter->getCell($sql);
	}




}