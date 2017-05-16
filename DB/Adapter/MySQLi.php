<?php

namespace QuickBop\DB\Adapter;

class MySQLi implements Interface{

  protected $_driver;

  protected $_stmt;

  protected $_logger;

  protected $_action;

  protected $_args;

  protected $_stopPropagation = false;

  public function __construct( MySQLi $mysqli, Psr\Log\LoggerInterface $logger ){
    $this->_driver = $mysqli;
    $this->_logger = $logger;
  }

  public function getDriver(){
    return $this->_driver;
  }

  public function parsableArgs( $action ){
    switch( $action ){
      case 'select':
        return ['select', 'from', 'join', 'where', 'groupby', 'having', 'orderby', 'limit', 'offset'];
      break;
    }
  }

  public function parseQuery( $action, $args ){
    return $this->_parseQuery( $action, $args, false );
  }

  protected function _parseQuery( $action, $args, $bindVars = true ){
    $q = "";
    switch( $action ){
      case 'select':
        $q .= "SELECT " . $this->_parseSelect( $args['select'] );
        $q .= "\nFROM " . $this->_parseFrom( $args['from'] );
        $q .= "\n" . $this->_parseJoin( $args['join'] );

        $w = $this->_parseWhere( $args['where'] );
        $q .= $w ? "\nWHERE $w" : "";

        $gb = $this->_parseGroupby( $args['groupby'] );
        $q .= $gb ? "\nGROUP BY $gb" : "";

        $h = $this->_parseHaving( $args['having'] );
        $q .= $h ? "\nHAVING $h" : "";

        $ob = $this->_parseOrderby( $args['orderby'] );
        $q .= $ob ? "\nORDER BY $ob" : "";

        $l = $this->_parseLimit( $args['limit'] );
        $off = $this->_parseOffset( $args['offset'] );
      break;
    }

    return $q;
  }

  public function query( $action, $args ){
    $this->_action = $action;
    $this->_args = $args;

    $this->_parseQuery( $action, $args, true );
  }

  protected function _parseSelect( $aliasFieldArr ){
    if( count( $aliasFieldArr ) < 1 ){
      $this->_logger->notice( 'No SELECT fields given in MySQLi Query' );
      $this->_stopPropagation();
    }

    $strArr = [];
    foreach( $aliasFieldArr as $alias=>$field ){
      //prevent sql injection via backticks
      $alias = $this->_backtickInjectionCheck( $alias, 'alias' );
      $field = $this->_backtickInjectionCheck( $field, 'field' );
      $strArr[] = "`$field` AS `$alias`";
    }
    return implode( ", ", $strArr );
  }

  protected function _parseFrom( $aliasTableArr ){
    if( ! isset( $aliasTableArr[1] ) ){
      $this->_logger->notice( 'No FROM table given in MySQLi Query' );
      $this->_stopPropagation();
    }
    $alias = $aliasTableArr[0];
    $alias = $this->_backtickInjectionCheck( $alias, 'alias' );
    $table = $aliasTableArr[1];
    $table = $this->_backtickInjectionCheck( $table, 'table' );
    return "`$table` AS `$alias`";
  }

  protected function _parseJoin( $joins ){
    $str = "";
    foreach( $joins as $j ){
      $alias = $this->_backtickInjectionCheck( $j['alias'], 'alias' );
      $table = $this->_backtickInjectionCheck( $j['table'], 'table' );
      $onArr = [];
      foreach( $j['on'] as $col ){
        $onTable = $this->_backtickInjectionCheck( $col[0], 'table' );
        $onColumn = $this->_backtickInjectionCheck( $col[1], 'column' );
        $onArr[] = ;
      }
      $on = "$onArr[0] = $onArr[1]";
      $str .= "\n{$j['type']} JOIN `$table` AS `$alias` ON ($on)";
    }
    return $str;
  }

  protected function _parseWhere( $wheres ){
    $str = "";
    $str
  }

  protected function _parseGroupby( $gbArr ){
    $_gbArr = [];
    for( $i = 0; $i < count( $groupbyArr ); $i++ ){
      $table = $this->_backtickInjectionCheck( $gbArr[$i]['table'], 'table' );
      $col = $this->_backtickInjectionCheck( $gbArr[$i]['column'], 'column' );
      $_gbArr[] = "`$table`.`$col`";
    }
    return implode( ",", $_gbArr );
  }

  protected function _parseHaving( $having ){

  }

  protected function _parseClause( $c, $depth = 0 ){
    $str = "";
    if( $this->_isFirstOrderClause( $c ) ){
      $cast = $this->_parseClauseCast( $c['cast'] );
      $operator = $this->_parseClauseOperator( $c['operator'] );
      $lhs = $this->_parseClauseOperand( $c['lhs'] );
      $rhs = $this->_parseClauseOperand( $c['rhs'] );
      $str = "CAST($lhs AS $cast) {$operator} CAST($rhs AS $cast)";
    }else{
      $strArr = [];
      foreach( $c as $k=>$_c ){
        if( $k == 'relation' ){
          break;
        }
        $strArr[] = $this->_parseClause( $_c, $depth+1 );
      }
      $indent = str_repeat( "\t", $depth );
      $str = implode( "\n{$indent}\t{$c['relation']} ", $strArr );
    }
    return "(\n{$indent}{$str}\n{$indent})";
  }

  protected function _isFirstOrderClause( $c ){
    return ! isset( $c['relation'] );
  }

  //todo
  protected function _parseClauseOperand( $o ){
    return $o;
  }

  protected function _parseClauseOperator( $o ){
    return $o;
  }

  protected function _parseClauseCast( $c ){
    switch( $c ){
      case 'uint':
      default:
        $cast = 'UNSIGNED';
      break;
    }
    return $cast;
  }

  protected function _parseOrderby( $byDirArr ){
    $strArr = [];
    foreach( $byDirArr as $by=>$dir ){
      //prevent sql injection via backticks
      if( is_array( $by ) ){
        $strArr[] = $this->_escapeColumnName( $by );
      }else{
        $by = $this->_backtickInjectionCheck( $by, 'alias' );
        $strArr[] = "`$by` $dir";
      }
    }
    return implode( ", ", $strArr );
  }

  protected function _parseLimit( $limit ){
    return $limit > 0 ? "$limit" : "";
  }

  protected function _parseOffset( $offset ){
    return $offset > 0 ? "$offset" : "";
  }

  protected function _backtickInjectionCheck( $str, $context = '' ){
    $_str = $str;
    if( strpos( $str, '`' ) !== false ){
      $str = str_replace( '`', '``', $str );
      $this->_logger->warning( 'Potential MySQL Backtick Injection attempt: {context} - "{str}"', ['context'=>$context, 'str'=>$_str] );
    }
    return $str;
  }

  protected function _escapeColumnName( $arr ){
    $table = $this->_backtickInjectionCheck( $arr[0], 'table' );
    $column = $this->_backtickInjectionCheck( $arr[1], 'column' );
    return "`$table`.`$column`";
  }

  public function lastInsertId(){
    return $this->_driver->insert_id;
  }

  protected function _stopPropagation(){
    $this->_stopPropagation = true;
  }

  public function clear(){
    $this->_stopPropagation = false;
  }

}
