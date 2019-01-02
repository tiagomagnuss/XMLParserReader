<?php
/**
 * ValueObject Abstract
 * 
 * @author Tiago C. Magnus
 * @copyright : Tiago Magnus - 2019
 */
namespace ValueObject;

/**
 * Abstract class for holding database rows.
 */
abstract class ValueObject
{

   /**
    * Returns the row's table's primary key. (must be the class first property)
    * Can be overwritten if the ValueObject's table has a different primary key or multiple primary keys.
    *
    * @return array Array containing the primary key indexes.
    */
   public function getPrimaryKey(): array
   {
      return ( array ) ( array_keys( ( array ) $this )[ 0 ] );
   }

   /**
    * Makes an array (columns' names as keys) into a ValueObject.
    *
    * @param array $arr
    *           Associative array with the columns' names as keys.
    * @return ValueObject The ValueObject containing the values from the array.
    */
   public static function fromArray( array $arr ): ValueObject
   {
      $className = static::class;
      $classObj = new $className();
      foreach ( $arr as $prop => $value )
      {
         $classObj->$prop = $value;
      }
      
      return $classObj;
   }

   /**
    * Converts the ValueObject into an associative array. (Columns' names are uppercased)
    *
    * @param mixed $obj
    *           The ValueObject to be converted.
    * @return array Associative array with columns' names as keys.
    */
   public static function toArray( &$obj ): array
   {
      $objArr = ( array ) $obj;
      $arr = array ();
      foreach ( $objArr as $key => $value )
      {
         $arr[ strtoupper( $key ) ] = $value;
      }
      return $arr;
   }
}
