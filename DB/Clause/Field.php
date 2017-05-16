<?php

namespace QuickBop\DB\Clause;

class Field{

  public $table;

  public $name;

  public $distinct = false;

  public function __construct( $name, $table = 't', $distinct = 'ALL' ){
    $this->name = $name;
    $this->table = $table;
    $this->_parseDistinct( $distinct );
  }

  protected function _parseDistinct( $d ){
    $this->distinct = strtoupper( $d ) == 'DISTINCT' ? true : false;
  }

}
