<?php

namespace QuickBop\DB\Adapter;

interface Interface{

  public function getDriver();

  public function clear();

  public function parsableArgs( $action );

  public function parseQuery( $action, $args );

  public function query( $action, $args );

  public function lastInsertId();
}
