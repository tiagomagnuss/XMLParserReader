<?php
/**
 * XMLParserReader
 * 
 * @author Tiago C. Magnus
 * @copyright : Tiago Magnus - 2019
 */

/**
 * Opens and reads a XML table.
 */
class XMLInterface
{
   const ERR = "Table not found";

   /**
    *
    * @var string File's relative path.
    */
   private $filename_;

   /**
    *
    * @var \SimpleXMLElement The file.
    */
   private $file_;

   /**
    *
    * @var \DOMDocument DOM document.
    */
   private $doc_;

   /**
    * Opens the file and saves its data.
    *
    * @param string $filename
    *           File's relative path.
    */
   public function __construct( string $filename )
   {
      libxml_use_internal_errors( true );

      if ( file_exists( $filename ) )
      {
         $this->file_ = simplexml_load_file( $filename );
         $error = libxml_get_errors();

         if ( $error )
         {
            throw new XMLParserException( $error );
         }

         $this->doc_ = new \DOMDocument( "1.0", "utf-8" );
         $this->doc_->formatOutput = true;
         $this->doc_->preserveWhiteSpace = false;

         $this->filename_ = $filename;
      }
      else
      {
         throw new XMLParserException( "Failed attempt to open $filename." );
      }
   }

   /**
    * Search for an object on XML through its table and identifier.
    *
    * @param string $table
    *           XML table name.
    * @param int $id
    *           Unique object identifier
    * @return array Associate array with the object's properties as keys and its values as values.
    */
   public function getObject( string $table, int $id ): array
   {
      $result = array ();
      $tableId = $this->getTableId( $table );

      if ( $tableId >= 0 )
      {
         $tableObj = $this->getTable( $tableId );

         // finds the primary key column name
         $primaryKey = $this->getPrimaryKey( $tableObj );

         if ( $primaryKey[ 0 ] == "" )
         {
            throw new XMLParserException( "Table doesn't have a primary key." );
         }

         $filters = array ( "$primaryKey[0]" => $id );

         // searches for the registry
         $rows = $this->getRows( $tableObj, $filters );

         // if any line was found, create the array
         if ( !empty( $rows ) )
         {
            $result = $this->xmlToArray( $tableId, $rows );
         }
      }
      // if table wasn't found on the archive, generates an error
      else
      {
         throw new XMLParserException( ERR );
      }

      return ( array ) $result[ 0 ];
   }

   /**
    * Retrieves all objects of an XML table.
    *
    * @param string $table
    *           XML table name.
    * @return array Associate array with the object's properties as keys and its values as values.
    */
   public function getAllObjects( string $table ): array
   {
      $tableId = $this->getTableId( $table );
      $results = array ();
      $i = 0;

      if ( $tableId >= 0 )
      {
         $tableObj = $this->getTable( $tableId );
         $count = count( $tableObj->row );

         while ( $i < $count )
         {
            $results[] = ( ( array ) $tableObj->row[ $i ]->value );
            $i++;
         }

         return $this->xmlToArray( $tableId, $results );
      }
      else
      {
         throw new XMLParserException( ERR );
      }
   }

   /**
    * Retrieves a filtered list of objects from a XML table.
    *
    * @param string $table
    *           XML table name.
    * @param array $filters
    *           Associative array with the keys being the column names with its value to be filtered.
    * @return array Associate array with the object's properties as keys and its values as values.
    *
    */
   public function getFilteredObjects( string $table, array $filters ): array
   {
      $result = array ();
      $tableId = $this->getTableId( $table );

      if ( $tableId >= 0 )
      {
         $tableObj = $this->getTable( $tableId );
         $rows = $this->getRows( $tableObj, $filters );

         // if any record was found, creates the result
         if ( !empty( $rows ) )
         {
            $result = $this->xmltoArray( $tableId, $rows );
         }

         return $result;
      }
      else
      {
         throw new XMLParserException( ERR );
      }
   }

   /**
    * Inserts an item on the table as long as it doesn't have problems with primary keys AND
    * have the same amount of columns as the table.
    * 
    * It's obligatory that primary keys are set, otherwise it won't be inserted.
    * If it's setted as null, the identifier of the last record + 1 will be used.
    * 
    * @param array $insert
    *           The item to be inserted, containing its columns as keys. 
    * @return int If there's a primary key, the key used will be returned otherwise returns 0.
    *             If it couldn't be inserted, returns -1.
    */
   public function insertItem( array $insert ): int
   {
      $err = -1;

      $primary = $this->getPrimaryKey( $this->file_->table );
      $colCount = count( ( array ) $this->file_->table->column );
      $rowCount = count( ( array ) ( ( array ) $this->file_->table )[ "row" ] );

      if ( !empty( $primary[ 0 ] ) )
      {
         // verifies if the table has primary key and the row's value is valid
         $valid = ( bool ) $this->validatePrimary( $primary, $insert );

         foreach ( $primary as &$key )
         {
            $colId[ $key ] = $this->getColumnIndex( $this->file_->table, $key );
         }

         // if it ain't valid, find the last record's id and increment it once. 
         if ( !( $valid ) )
         {
            foreach ( $colId as $key => $id )
            {
               $nextId = 1 + $this->file_->table->row[ count( $this->file_->table->row ) - 1 ]->value[ $id ];
               $insert[ $key ] = $nextId;
            }
         }
      }

      // if the item has every columns set
      if ( $colCount == count( $insert ) )
      {
         $insert = $this->sortColumns( $insert );
         $this->file_->table->addChild( "row" );

         foreach ( $insert as &$val )
         {
            $this->file_->table->row[ $rowCount ]->addChild( "value", $val );
         }

         $this->doc_->loadXML( $this->file_->asXML() );
         $this->doc_->save( $this->filename_, LIBXML_NOEMPTYTAG );

         $err = ( empty( $colId ) ) ? 0 : $insert[ array_keys( $colId )[ 0 ] ];
      }
      else
      {
         $err = -1;
      }

      return $err;
   }

   /**
    * Updates the specified columns for items that match the filters.
    *
    * @param array $filters
    *           The filters to update items.
    * @param array $changes
    *           The changes to be made, array with column names' as keys.
    * @param bool $all
    *           [optional] Defines if must update ALL records on the table. 
    * @return int Returns 1 if anything was updated, else returns 0.
    */
   public function updateFile( array $filters, array $changes, bool $all = false ): int
   {
      $err = 0;
      $i = 0;

      // gets the table's primary key
      $primary = $this->getPrimaryKey( $this->file_->table );

      foreach ( $this->file_->table->row as $row )
      {
         if ( ( $this->isContained( $this->file_->table, $row, $filters ) ) || ( $all ) )
         {
            foreach ( $changes as $col => $val )
            {
               $colId = $this->getColumnIndex( $this->file_->table, $col );

               // if the column isn't primary and exists
               if ( !( $this->isPrimary( $primary, $col ) ) && ( $colId >= 0 ) )
               {
                  $this->file_->table->row[ $i ]->value[ $colId ] = $val;
                  $err = 1;
               }
            }
         }

         $i++;
      }

      $this->doc_->loadXML( $this->file_->asXML() );
      $this->doc_->save( $this->filename_, LIBXML_NOEMPTYTAG );

      return $err;
   }

   /**
    * Deletes the rows that match the filters.
    *
    * @param array $filters
    *           Filters to which the rows must match, array with column names' as keys.
    * @param bool $all
    *           [optional] Defines if must remove ALL records on the table.
    * @return int Returns 1 if anything was removed, else returns 0.
    */
   public function removeItems( array $filters, bool $all = false ): int
   {
      $removeRows = array ();
      $hasPrimary = false;
      $err = 0;
      $i = 0;

      // checks if any of the keys are primary to avoid searching multiple times
      foreach ( array_keys( $filters ) as &$col )
      {
         if ( $this->isPrimary( $this->getPrimaryKey( $this->file_->table ), $col ) )
         {
            $hasPrimary = true;
         }
      }

      foreach ( $this->file_->table->row as $row )
      {
         if ( $all || $this->isContained( $this->file_->table, $row, $filters ) )
         {
            // identifies the rows to remove as to not break the foreach 
            $removeRows[] = $i;
            $err = 1;

            if ( $hasPrimary && !$all )
            {
               break;
            }
         }

         $i++;
      }

      // removes the rows that matched the filters
      $removeRows = array_reverse( $removeRows );
      foreach ( $removeRows as $key )
      {
         unset( $this->file_->table->row[ $key ] );
      }

      $this->doc_->loadXML( $this->file_->asXML() );
      $this->doc_->save( $this->filename_, LIBXML_NOEMPTYTAG );
      return $err;
   }

   /**
    * Finds a table's ID on the XML file.
    *
    * @param string $tablename
    *           The table name to be searched.
    * @return int Returns the table index or -1 if wasn't found.
    */
   private function getTableId( string $tablename ): int
   {
      $foundTable = false;
      $i = 0;

      while ( ( !$foundTable ) && ( $i < $this->file_->count() ) )
      {
         if ( strtolower( $this->file_->table[ $i ]->attributes()[ 0 ] ) == strtolower( $tablename ) )
         {
            $foundTable = true;
         }

         else
         {
            $i++;
         }
      }

      return $foundTable ? $i : -1;
   }

   /**
    * Gets an table on the file through its ID.
    *
    * @param int $tableId
    *           Table's ID.
    * @return \SimpleXMLElement|NULL If the table was found, returns it as a SimpleXMLElement. 
    */
   private function getTable( int $tableId ): ?\SimpleXMLElement
   {
      return $this->file_->table[ $tableId ];
   }

   /**
    * Gets the table's primary key.
    *
    * @param \SimpleXMLElement $tableObj
    *           The table's SimpleXMLEelement object.
    * @return array Array containing the table's primary keys. 
    */
   private function getPrimaryKey( \SimpleXMLElement $tableObj ): array
   {
      return explode( ", ", $tableObj->attributes()[ "primary" ] );
   }

   /**
    * Gets the index of a table's column.
    *
    * @param \SimpleXMLElement $tableObj
    *           Table's SimpleXMLElement object.
    * @param string $columnName
    *           Column name.
    * @return int The column's index if it was found, else -1. 
    */
   private function getColumnIndex( \SimpleXMLElement $tableObj, string $columnName ): int
   {
      $index = -1;
      foreach ( ( array ) $tableObj->column as $key => $column )
      {
         if ( $column == $columnName )
         {
            $index = ( int ) $key;
            break;
         }
      }

      return $index;
   }

   /**
    * Gets an array of rows that match the filters.
    *
    * @param \SimpleXMLElement $tableObj
    *           Table's SimpleXMLElement object.
    * @param array $filters
    *           The filters that rows must match. Columns' names as keys. 
    * @throws XMLParserException Exception case one of the columns is not part of the table. 
    * @return array Returns the array of rows that match the filters. 
    */
   private function getRows( \SimpleXMLElement $tableObj, array $filters ): array
   {
      $indexFilters = array ();
      $rows = array ();

      // find the index of each column
      foreach ( $filters as $column => $value )
      {
         $index = $this->getColumnIndex( $tableObj, $column );

         if ( $index < 0 )
         {
            throw new XMLParserException( "Table doesn't contain the '$column' column." );
         }

         $indexFilters[ $index ] = $value;
      }

      // checks which rows match the filters
      foreach ( $tableObj->row as $row )
      {
         $match = true;

         foreach ( $indexFilters as $col => $value )
         {
            $verifyNull = is_null( $value ) && empty( $row->value[ $col ] );
            $match &= ( $row->value[ $col ] == $value ) || $verifyNull;
         }

         if ( $match )
         {
            $rows[] = $row->value;
         }
      }

      return $rows;
   }

   /**
    * Puts the items found on formated arrays.
    *
    * @param int $tableId
    *           Table identifier.
    * @param array $rows
    *           Array containing the rows.
    * @return array Associate array with the object's properties as keys and its values as values.
    */
   private function xmlToArray( int $tableId, array $rows ): array
   {
      $i = 0;
      $n = 0;
      $results = array ();
      $rowCount = count( $rows );

      foreach ( ( array ) $this->file_->table[ $tableId ] as $index => $node )
      {
         $table[ $index ] = $node;
      }

      while ( $n < $rowCount )
      {
         unset( $values );
         $i = 0;

         foreach ( ( array ) $rows[ $n ] as $index => $node )
         {
            if ( ( is_object( $node ) ) && ( empty( $node ) ) )
            {
               $node = null;
            }

            $values[ $i ] = $node;
            $i++;
         }

         $i = 0;
         foreach ( $table[ "column" ] as $col )
         {
            $results[ $n ][ $col ] = $values[ $i ];
            $i++;
         }

         $n++;
      }

      return $results;
   }

   /**
    * Tells if a row match specific filters.
    *
    * @param \SimpleXMLElement $tableObj
    *           Table's SimpleXMLElement object.
    * @param \SimpleXMLElement $row
    *           Row's SimpleXMLElement object.
    * @param array $filters
    *           The filters to be matched, columns' names as keys.
    * @return bool Flag saying if the row matches the filters.
    */
   private function isContained( \SimpleXMLElement $tableObj, \SimpleXMLElement $row, array $filters ): bool
   {
      $match = !( empty( $filters ) || empty( $tableObj ) || empty( $row ) );

      if ( $match )
      {
         foreach ( $filters as $col => $val )
         {
            $colId = $this->getColumnIndex( $tableObj, $col );

            if ( $colId >= 0 )
            {
               if ( empty( $row->value[ $colId ] ) )
               {
                  $match &= !( $val );
               }
               else
               {
                  $match &= ( $row->value[ $colId ] == $val );
               }
            }
            else
            {
               $match = false;
               break;
            }
         }
      }

      return $match;
   }

   /**
    * Checks if the entry has conflicting primary keys.
    *
    * @param array $primary
    *           Table's primary keys.
    * @param array $entry
    *           Entry to be analized.
    * @return int Returns 1 if entry is valid, else 0.
    */
   private function validatePrimary( array $primary, array $entry ): int
   {
      $err = 1;

      foreach ( $primary as $colName )
      {
         $colId = $this->getColumnIndex( $this->file_->table, $colName );
         if ( $err < 1 || ( $entry[ $colName ] == null ) )
         {
            $err = 0;
            break;
         }

         foreach ( $this->file_->table->row as $row )
         {
            $value = ( empty( ( ( array ) $row->value )[ $colId ] ) ) ? null : ( ( array ) $row->value )[ $colId ];

            // checks if the primary key is conflicting OR null
            if ( $entry[ $colName ] == $value )
            {
               $err = 0;
               break;
            }
         }
      }

      return $err;
   }

   /**
    * Tells if a column is a table's primary key.
    *
    * @param array $primaries
    *           Table's primary keys.
    * @param string $colName
    *           Column name to be checked.
    * @return bool Flag saying it the column is a primary key.
    */
   private function isPrimary( array $primaries, string $colName ): bool
   {
      $err = false;

      foreach ( $primaries as $key )
      {
         if ( $key == $colName )
         {
            $err = true;
            break;
         }
      }

      return $err;
   }

   /**
    * Orders a row's columns, according to the table structure.
    *
    * @param array $row
    *           Associative array with the columns' names as keys.
    * @return array Ordered associative array with the columns' names as keys.
    */
   private function sortColumns( array $row ): array
   {
      $final = array ();
      // associates the columns' names to their index
      foreach ( $row as $col => $val )
      {
         $indexArray[ $col ] = $this->getColumnIndex( $this->file_->table, $col );
      }

      // orders the arrays through their indexes
      asort( $indexArray );

      // and saves it back
      foreach ( $indexArray as $col => $val )
      {
         $final[ $col ] = $row[ $col ];
      }

      return $final;
   }
}

/**
 * Class to generate Exceptions for the XMLInterface.
 */
class XMLParserException extends \Exception
{}

?>