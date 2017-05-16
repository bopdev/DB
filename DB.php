<?php

namespace QuickBop;

class DB{

  protected $_select = [];

  protected $_insert = [];

  protected $_update = [];

  protected $_replace = [];

  protected $_from = [];

  protected $_join = [];

  protected $_where = [];

  protected $_whereStructsHierarchy = [];

  protected $_groupby = [];

  protected $_having = [];

  protected $_havingStructsHierarchy = [];

  protected $_orderby = [];

  protected $_limit = 0;

  protected $_offset = 0;

  protected $_page = 1;

  protected $_tableAliases = ['t'];

  protected $_action = 'select';

  protected $_adapter;

  public function __construct( DB\Adapter\Interface $adapter ){
    $this->_adapter = $adapter;
  }

  public function getAdapter(){
    return $this->_adapter;
  }

  public function select( $aliasFieldArr, $method = 'append' ){
    $this->_action = 'select';

    switch( $method ){
      case 'set':
        $this->_select = $aliasFieldArr;
      break;
      case 'append':
        $this->_select = array_merge( $this->_select, $aliasFieldArr );
      break;
      case 'prepend':
        $this->_select = array_merge( $aliasFieldArr, $this->_select );
      break;
      case 'clear':
        $this->_select = [];
      break;
    }
  }

  public function insert( $colValArray, $method = 'append' ){
    $this->_action = 'insert';

    switch( $method ){
      case 'set':
        $this->_insert = $colValArray;
      break;
      case 'append':
        $this->_insert = array_merge( $this->_insert, $colValArray );
      break;
      case 'prepend':
        $this->_insert = array_merge( $colValArray, $this->_insert );
      break;
      case 'clear':
        $this->_insert = [];
      break;
    }
  }

  public function update( $colValArray, $method = 'append' ){
    $this->_action = 'update';

    switch( $method ){
      case 'set':
        $this->_update = $colValArray;
      break;
      case 'append':
        $this->_update = array_merge( $this->_update, $colValArray );
      break;
      case 'prepend':
        $this->_update = array_merge( $colValArray, $this->_update );
      break;
      case 'clear':
        $this->_update = [];
      break;
    }
  }

  public function replace( $colValArray, $method = 'append' ){
    $this->_action = 'replace';

    switch( $method ){
      case 'set':
        $this->_replace = $colValArray;
      break;
      case 'append':
        $this->_replace = array_merge( $this->_replace, $colValArray );
      break;
      case 'prepend':
        $this->_replace = array_merge( $colValArray, $this->_replace );
      break;
      case 'clear':
        $this->_replace = [];
      break;
    }
  }

  public function delete(){
    $this->_action = 'delete';
  }

  public function from( $table ){
    $this->_from = ['t', $table];
  }

  public function join( $table, $alias, $on, $type = 'INNER', $method = 'append' ){

    if( in_array( $alias, $this->_tableAliases ) ){
      //error
      return $this;
    }

    switch( $method ){
      case 'set':
        $this->clearJoins();
        $method = 'append';
      break;
      case 'append':
      case 'prepend':
        $this->_tableAliases[] = $alias;
      break;
    }

    if( ! in_array( $alias, array_keys( $on ) ) ){
      //error
      return $this;
    }

    if( count( $on ) != 2 ){
      //error
      return $this;
    }

    $arr = ['table'=>$table, 'alias'=>$alias, 'on'=>$on, 'type'=>$type];

    switch( $method ){
      case 'append':
        $this->_join[] = $arr;
      break;
      case 'prepend':
        array_unshift( $this->_join, $arr );
      break;
    }
  }

  public function clearJoins(){
    $this->_tableAliases = ['t'];
    $this->_join = [];
    return $this;
  }

  protected function _createStruct( $type, $name, $relation = 'AND', $parent = null ){
    switch( $type ){
      case 'where':
        $hier = &$this->_whereStructsHierarchy;
      break;
      case 'having':
        $hier = &$this->_havingStructsHierarchy;
      break;
    }

    if( in_array( $name, array_keys( $hier ) ) ){
      $this->_clearStruct( $type, $name );
    }

    $hier[$name] = $parent;
    $relation = $this->_sanitizeWhereRelation( $relation );

    $parentStruct = &$this->_getStruct( $type, $parent );
    $parentStruct[$name] = ['relation'=>$relation];
  }

  public function _clearStruct( $type, $name ){
    unset( &$this->_getStruct( $type, $name ) );
    switch( $type ){
      case 'where':
        unset( $this->_whereStructsHierarchy[$name] );
      break;
      case 'having':
        unset( $this->_havingStructsHierarchy[$name] );
      break;
    }
  }

  protected function &_getStruct( $type, $name ){
    switch( $type ){
      case 'where':
        $hier = &$this->_whereStructsHierarchy;
      break;
      case 'having':
        $hier = &$this->_havingStructsHierarchy;
      break;
    }

    $loc = [$name];
    while( $hier[$name] ){
      $loc[] = $hier[$name];
    }

    $struct = &$this->_where;
    while( $name = array_pop( $loc ) ){
      $struct = &$struct[$name];
    }

    return $struct;
  }

  public function createWhereStruct( $name, $relation = 'AND', $parent = null ){
    $this->_createStruct( 'where', $name, $relation, $parent );
  }

  public function clearWhereStruct( $name ){
    $this->_clearStruct( 'where', $name );
  }

  protected function _where( $type, $lhs, $operator, $rhs, $cast = 'uint', $structName = null ){
    $struct = &$this->_getStruct( $type, $structName );

    $operator = $this->_sanitizeWhereOperator( $operator );
    $cast = $this->_sanitizeWhereCast( $cast );

    $arr = ['lhs'=>$lhs, 'operator'=>$operator, 'rhs'=>$rhs, 'cast'=>$cast];
    $struct[] = $arr;
  }

  public function where( $lhs, $operator, $rhs, $cast = 'uint', $structName = null ){
    $this->_where( 'where', $lhs, $operator, $rhs, $cast, $structName );
  }

  public function clearWheres(){
    $this->_where = [];
    $this->_whereStructsHierarchy = [];
    return $this;
  }

  protected function _sanitizeWhereRelation( $rel ){
    $rel = strtoupper( $rel );
    return $rel == 'AND' ? 'AND' : 'OR';
  }

  protected function _sanitizeWhereCast( $cast ){
    $cast = strtoupper( $cast );
    return in_array( $cast, ['uint'] ) ? $cast : 'uint';
  }

  protected function _sanitizeWhereOperator( $op ){
    $op = strtoupper( $op );
    switch( $op ){
      case '=':
      case '<':
      case '>':
      case '<=':
      case '>=':
      case 'LIKE':
      case 'NOT LIKE':
      case 'IN':
      case 'NOT IN':
      case 'IS';
      case 'IS NOT':
        //no change
      break;
      case '!=':
        $op = '<>';
      break;
      case '==':
      default:
        $op = '=';
      break;
    }
    return $op;
  }

  public function groupby( $table, $col, $method = 'append' ){
    $groupby = $this->_groupby;
    switch( $method ){
      case 'append':
        $groupby[] = ['table'=>$table, 'column'=>$col];
      break;
      case 'prepend':
        array_unshift( $groupby, ['table'=>$table, 'column'=>$col] );
      break;
    }
    $this->_groupby = $groupby;

    return $this;
  }

  public function clearGroupbys(){
    $this->_groupby = [];
    return $this;
  }

  public function createHavingStruct( $name, $relation = 'AND', $parent = null ){
    $this->_createStruct( 'having', $name, $relation, $parent );
  }

  public function clearHavingStruct( $name ){
    $this->_clearStruct( 'having', $name );
  }

  public function having( $lhs, $operator, $rhs, $cast = 'uint', $structName = null ){
    $this->_where( 'having', $lhs, $operator, $rhs, $cast, $structName );
  }

  public function clearHavings(){
    $this->_having = [];
    $this->_havingStructsHierarchy = [];
    return $this;
  }

  public function orderby( $by, $dir = 'ASC', $method = 'append' ){
    $dir = self::sanitizeOrderbyDirection( $dir );

    $arr = ['by'=>$by, 'dir'=>$dir];

    switch( $method ){
      case 'append':
        array_push( $this->_orderby, $arr );
      break;
      case 'prepend':
        array_unshift( $this->_orderby, $arr );
      break;
    }

    return $this;
  }

  public function clearOrderbys(){
    $this->_orderby = [];
  }

  public static function sanitizeOrderbyDirection( $dir ){
    return strtoupper( $dir ) == 'ASC' ? 'ASC' : 'DESC';
  }

  public function limit( $num ){
    if( is_int( $num ) && $num >= 0 ){
      $this->_limit = $num;
      if( $num != 0 && $this->_offset == 0 ){
        $this->_resolveOffsetByPage();
      }
    }
    return $this;
  }

  public function offset( $num ){
    if( is_int( $num ) && $num >= 0 )
      $this->_offset = $num;
    return $this;
  }

  public function page( $num ){
    if( is_int( $num ) && $num >= 1 ){
      $this->_page = $num;
      $this->_offset = 0; //to prevent confusion
      if( $this->_limit != 0 ){
        $this->_resolveOffsetByPage();
      }
    }
    return $this;
  }

  public function perPage( $num ){
    return $this->limit( $num );
  }

  protected function _resolveOffsetByPage(){
    $this->_offset = $this->_limit * ( $this->_page - 1 );
  }

  public function generateQuery(){
    $args = [];

    foreach( $this->_adapter->parsableArgs( $this->_action ) as $key ){
      $args[$key] = $this->_$key;
    }

    return $this->_adapter->parseQuery( $this->_action, $args );
  }

  public function query( $method = null ){

  }

}
