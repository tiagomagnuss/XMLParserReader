<?php
/**
 * XMLParserReader
 * 
 * @author Tiago C. Magnus
 * @copyright : Tiago Magnus - 2018
 */

require_once "Database/Database.php";

/**
 * Generates the XML file that maps the table.
 * 
 * For this to work you need a Database resource that can be queried and instantiated on this class (l-57).
 * Also, for the table's primary keys, ValueObjects must exist, with a function to get their primary values (l-100).
 */
class ParseXML
{

   /**
    *
    * @var DomDocument DOM document.
    */
   private $doc_;

   /**
    *
    * @var int Quantity of lines to parse.
    */
   private $qtd_;

   /**
    *
    * @var DOMElement Dataset DOM element.
    */
   private $xmlDataset;

   /**
    *
    * @var DOMElement Table DOM element.
    */
   private $xmlTable;

   /**
    * Parses a table to XML, as long as it has at least 1 row.
    *
    * @param string $table
    *           Source table name.
    * @param int $qtd
    *           [optional] Amount of lines to parse, 0 to parse all. Defaults to 5.
    * @param string $order
    *           [optional] Defines the ordering of rows.
    * @param string $db
    *           [optional] Table's database name.
    * @return int Returns 0 if nothing was returned on the query, 1 if the file was created.
    */
   public function parseToXML( string $table, int $qtd = 5, string $order = "", string $db = "DB" ): int
   {
      $this->qtd_ = $qtd;
      $sql = new Database( $db );

      $query = ( ( $qtd > 0 ) ? "SELECT FIRST $qtd * FROM $table" : "SELECT * FROM $table" );
      $query .= $order;

      $rows = $sql->selectAll( $query );

      if ( !( empty( $rows ) ) )
      {
         $this->writeFile( $rows, $table );
         return 1;
      }
      else
      {
         return 0;
      }
   }

   /**
    * Writes the XML file.
    *
    * @param array $rows
    *           Rows returned on the query. Formatted as an array of arrays as keys => values.
    *           Where 'keys' are the table's columns.
    * @param string $table
    *           The table's name.
    */
   private function writeFile( array $rows, string $table ): void
   {
      // configures the XML
      $implementation = new DOMImplementation();
      $dtd = $implementation->createDocumentType( "xml" );

      $this->doc_ = $implementation->createDocument( null, null, $dtd );
      $this->doc_->encoding = "utf-8";
      $this->doc_->formatOutput = true;
      $this->doc_->preserveWhiteSpace = false;

      $this->xmlDataset = $this->doc_->createElement( "dataset" );
      $this->xmlTable = $this->doc_->createElement( "table" );

      // gets table's primary keys
      $table = ucfirst( strtolower( $table ) );

      require_once "ValueObjects//ValueObject.php";
      require_once "ValueObjects//$table.php";

      $tableInNamespace = "ValueObject\\$table";
      $tableObj = new $tableInNamespace();
      $primary = $tableObj->getPrimaryKey();

      if ( count( $primary ) >= 1 )
      {
         $primary = implode( ",", $primary );
      }

      // table details.
      $table = strtoupper( $table );
      $this->xmlTable->setAttribute( "name", $table );
      $this->xmlTable->setAttribute( "primary", strtoupper( $primary ) );

      $this->genBody( $rows );
      $this->xmlDataset->appendChild( $this->xmlTable );
      $this->doc_->appendChild( $this->xmlDataset );

      $this->doc_->save( "$table.xml", LIBXML_NOEMPTYTAG );
   }

   /**
    * Creates the XML file body.
    *
    * @param array $rows
    *           Obtained rows from query.
    */
   private function genBody( array $rows ): void
   {
      $i = 0;
      $keys = array_keys( $rows[ 0 ] );

      // creates the columns
      foreach ( $keys as $col )
      {
         $column = $this->doc_->createElement( "column", $col );
         $this->xmlTable->appendChild( $column );
      }

      // creates the rows
      while ( $i < count( $rows ) )
      {
         $line = $this->doc_->createElement( "row" );

         foreach ( $rows[ $i ] as $value )
         {
            // replacing & for something XML can read as &.
            $value = str_replace( "&", "&amp;", $value );
            $valObj = $this->doc_->createElement( "value", $value );
            $line->appendChild( $valObj );
         }

         $this->xmlTable->appendChild( $line );
         $i++;
      }
   }
}
?>

