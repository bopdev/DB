<?php

namespace QuickBop\DB\Clause;

class Join{

  public $type = 'INNER';

  public $table;

  public $onLocal;

  public $onForeign;

  protected $permittedTypes = ['INNER', 'LEFT', 'RIGHT', 'OUTER'];

  public function __construct( Table $table, Select\Field $onLocal, Select\Field $onForeign, $type = 'INNER' ){
    $this->table = $table;
    $this->onLocal = $onLocal;
    $this->onForeign = $onForeign;
    $this->_parseType( $type );
  }

  protected function _parseType( $type ){
    $this->type = in_array( $type, $this->permittedTypes ) ? $type : 'INNER';
  }

  public function getOnFields(){
    return [$this->onLocal, $this->onForeign];
  }
}
