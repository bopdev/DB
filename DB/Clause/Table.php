<?php

namespace QuickBop\DB\Clause;

class Table{

  public $database = null;

  public $name;

  public $alias;

  public function __construct( $name, $alias, $database = null ){
    $this->name = $name;
    $this->alias = $alias;
    $this->database = $database;
  }

}
