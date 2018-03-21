<?php
/**
 * Projeto DBLib
 *
 * @copyright : Trisolutions - Soluções em Engenharia Ltda. 2017
 * @version 0.1
 */

/**
 * Classe auxiliar para abrir e ler um arquivo XML com dados mockados.
 * É utilizado pelos DAO de testes unitários.
 */
class XMLInterface
{
   const ERR = "Tabela não encontrada";

   /**
    *
    * @var string
    */
   private $filename_;

   private $file_;

   private $doc_;

   /*
    * A função getTableId() não é mais necessária tendo em vista que cada arquivo conterá somente uma tabela.
    * Porém ainda pode ser utilizada normalmente, sendo que cada tabela possuirá um ID = 0.
    *
    */

   /**
    * Abre o arquivo e salva seus dados em uma variável.
    *
    * @param string $filename
    *           Caminho do arquivo XML.
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

         $this->doc_ = new DOMDocument( "1.0", "utf-8" );
         $this->doc_->formatOutput = true;
         $this->doc_->preserveWhiteSpace = false;

         $this->filename_ = $filename;
      }
      else
      {
         throw new XMLParserException( "Falha ao abrir $filename." );
      }
   }

   /**
    * Busca um objeto no XML pela sua tabela e identificador.
    *
    * @param string $table
    *           Tabela de objetos no XML.
    * @param int $id
    *           Identificador único do objeto.
    * @return array Array associativo com as propriedades do objeto como chaves e os seus respectivos valores.
    */
   public function getObject( string $table, int $id ): array
   {
      $result = array ();
      $tableId = $this->getTableId( $table );

      if ( $tableId >= 0 )
      {
         $tableObj = $this->getTable( $tableId );

         // monta o filtro com a chave primária
         $primaryKey = $this->getPrimaryKey( $tableObj );

         if ( $primaryKey[ 0 ] == "" )
         {
            throw new XMLParserException( "Tabela não possui chave primária." );
         }

         $filters = array ( "$primaryKey[0]" => $id );

         // busca o registro
         $rows = $this->getRows( $tableObj, $filters );

         // se encontrou alguma linha, a(s) relaciona com as colunas da tabela.
         if ( !empty( $rows ) )
         {
            $result = $this->xmlToArray( $tableId, $rows );
         }
      }
      else
      {
         throw new XMLParserException( ERR );
      }

      return ( array ) $result[ 0 ];
   }

   /**
    * Busca todos os objetos de uma tabela no XML.
    *
    * @param string $tabela
    *           Tabela de objeto no XML.
    * @return array Array de arrays associativos com as propriedades do objeto como chaves e os seus respectivos valores.
    */
   public function getAllObjects( string $table ): array
   {
      $tableId = $this->getTableId( $table );
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
    * Busca uma lista de objetos no XML de dada tabela e uma lista de propriedades para filtrar.
    *
    * @param int $tableId
    *           O ID da tabela dos objetos no XML.
    * @param array $filters
    *           Array relacional de colunas => valores a serem buscados.
    * @return array Array de arrays associativos com as propriedades do objeto como chaves e os seus respectivos valores.
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

         // se obteve alguma linha, a(s) relaciona com as colunas.
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
    * Insere um item na tabela desde que o mesmo não gere conflitos com chaves primárias e tenha
    * a mesma quantidade de colunas que a tabela.
    *
    * @param array $insert
    *           O item a ser inserido, array de valores.
    * @return int Se houver chave primária, retorna a primeira chave, se não houver chave primária retorna 0.
    *         Em caso de não inserção, retorna -1.
    */
   public function insertItem( array $insert ): int
   {
      $err = -1;

      $primary = $this->getPrimaryKey( $this->file_->table );
      $colCount = count( ( array ) $this->file_->table->column );
      $rowCount = count( ( ( array ) $this->file_->table )[ "row" ] );

      if ( !( empty( $primary[ 0 ] ) ) )
      {
         // se a tabela conter chave primária e a entrada for válida.
         $valid = $this->validatePrimary( $primary, $insert );

         foreach ( $primary as &$key )
         {
            $colId[ $key ] = $this->getColumnIndex( $this->file_->table, $key );
         }

         // caso não seja válida, gera o próximo ID da chave primária baseado na última linha da tabela.
         if ( !( $valid ) )
         {
            foreach ( $colId as $key => $id )
            {
               $nextId = 1 + $this->file_->table->row[ count( $this->file_->table->row ) - 1 ]->value[ $id ];
               $insert[ $key ] = $nextId;
            }
         }
      }

      // se a entrada estiver com todos as colunas "preenchidas"
      if ( $colCount == count( $insert ) )
      {
         $insert = $this->sortColumns( $insert );
         $this->file_->table->addChild( "row" );

         $insert = $this->sortColumns( $insert );
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
    * Atualiza os valores especificados nas linhas que atendem aos filtros.
    *
    * @param array $filters
    *           Os filtros que as linhas devem atender.
    * @param array $changes
    *           As mudanças a serem feitas, array formatado com nome_da_coluna => valor.
    * @param bool $all
    *           [optional]
    *           Define se realiza a operação em TODOS os itens da tabela.
    * @return int Retorna 1 se houve alguma atualização, 0 se não.
    */
   public function updateFile( array $filters, array $changes, bool $all = false ): int
   {
      $err = 0;
      $i = 0;

      $primary = $this->getPrimaryKey( $this->file_->table );

      foreach ( $this->file_->table->row as $row )
      {
         if ( ( $this->isContained( $this->file_->table, $row, $filters ) ) || ( $all ) )
         {
            foreach ( $changes as $col => $val )
            {
               $colId = $this->getColumnIndex( $this->file_->table, $col );

               // se a coluna não for primária e existir
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
    * Remove as linhas que atendem aos filtros.
    *
    * @param array $filters
    *           Os filtros aos quais as linhas devem atender, array formatado com nome_da_coluna => valor.
    * @param bool $all
    *           [optional]
    *           Define se realiza a operação em TODOS os itens da tabela.
    * @return int Retorna 1 se houve alguma remoção, 0 se não.
    */
   public function removeItems( array $filters, bool $all = false ): int
   {
      $err = 0;
      $i = 0;

      foreach ( $this->file_->table->row as $row )
      {
         if ( $this->isContained( $this->file_->table, $row, $filters ) || $all )
         {
            // adiciona todos os matches em um array...
            $removeMe[] = $this->file_->table->row[ $i ];
            $err = 1;
         }

         $i++;
      }

      // e remove cada um deles da tabela.
      foreach ( $removeMe as $obj )
      {
         unset( $obj[ 0 ] );
      }

      $this->doc_->loadXML( $this->file_->asXML() );
      $this->doc_->save( $this->filename_, LIBXML_NOEMPTYTAG );

      return $err;
   }

   /**
    * Busca o ID da tabela no arquivo XML.
    *
    * @param string $tablename
    *           O nome da tabela a ser procurado.
    * @return int Retorna o índice da tabela ou -1 se não encontrá-la.
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
    * Busca uma tabela no arquivo XML através do seu ID.
    *
    * @param int $tableId
    *           ID da tabela a ser encontrada
    * @return SimpleXMLElement|NULL Se encontrar a tabela pelo seu ID, a retorna como elemento SimpleXMLElement.
    */
   private function getTable( int $tableId ): ?SimpleXMLElement
   {
      return $this->file_->table[ $tableId ];
   }

   /**
    * Retorna a chave primária da tabela definida nos seus atributos.
    *
    * @param SimpleXMLElement $tableObj
    *           Objeto SimpleXMLElement que contém, inclusive, os atributos da tabela.
    * @return string As colunas primárias da tabela.
    */
   private function getPrimaryKey( SimpleXMLElement $tableObj ): array
   {
      return explode( ", ", $tableObj->attributes()[ "primary" ] );
   }

   /**
    * Retorna o indíce de uma coluna de uma tabela específica no arquivo XML.
    *
    * @param SimpleXMLElement $tableObj
    *           A tabela em formato de objeto XML.
    * @param string $columnName
    *           O nome da coluna a ser procurada.
    * @return int O valor do índice da tabela se encontrada, se não a encontrar retorna -1.
    */
   private function getColumnIndex( SimpleXMLElement $tableObj, string $columnName ): int
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
    * Retorna um array de linhas que atendem aos critérios de determinados filtros.
    *
    * @param SimpleXMLElement $tableObj
    *           A tabela em formato de objeto XML.
    * @param array $filters
    *           Os filtros, coluna e valor, tomados como critério.
    * @throws XMLParserException Exceção gerada caso alguma das colunas não exista na tabela.
    * @return array Retorna o array das linhas que atenderam aos critérios exigidos.
    */
   private function getRows( SimpleXMLElement $tableObj, array $filters ): array
   {
      $indexFilters = array ();

      // acha o index de cada coluna informada.
      foreach ( $filters as $column => $value )
      {
         $index = $this->getColumnIndex( $tableObj, $column );

         if ( $index < 0 )
         {
            throw new XMLParserException( "Tabela não possui a coluna '$column' para filtrar." );
         }

         $indexFilters[ $index ] = $value;
      }

      $rows = array ();
      // verifica quais linhas atendem aos filtros
      foreach ( $tableObj->row as $row )
      {
         $match = true;

         foreach ( $indexFilters as $col => $value )
         {
            $match &= $row->value[ $col ] == $value;
         }

         if ( $match )
         {
            $rows[] = $row->value;
         }
      }

      return $rows;
   }

   /**
    * Transforma o resultado das buscas em array formatado.
    *
    * @param int $tableId
    *           O ID da tabela em que está o objeto.
    * @param array $rows
    *           Array com as linhas encontradas na busca.
    * @return array Array no formato de tabela (nome_da_coluna => valor) com o objeto encontrado.
    */
   private function xmlToArray( int $tableId, array $rows ): array
   {
      $i = 0;
      $n = 0;

      foreach ( ( array ) $this->file_->table[ $tableId ] as $index => $node )
      {
         $table[ $index ] = $node;
      }

      $rowCount = count( $rows );
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
    * Indica se uma linha atende aos filtros determinados.
    *
    * @param SimpleXMLElement $tableObj
    *           A tabela de dados.
    * @param SimpleXMLElement $row
    *           A linha a ser verificada.
    * @param array $filters
    *           Os critérios a serem aplicados, array formatado com nome_da_coluna => valor.
    * @return bool Se a linha atende aos critérios.
    */
   private function isContained( SimpleXMLElement $tableObj, SimpleXMLElement $row, array $filters ): bool
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
    * Busca conflitos entre as chaves primárias da entrada, ou se alguma chave é nula.
    *
    * @param array $primary
    *           O array de chaves primárias da tabela.
    * @param array $entry
    *           A linha de dados a entrar no banco de dados, somente valores.
    * @return int Retorna 0 se encontrar algum problema ou 1 se não.
    */
   private function validatePrimary( array $primary, array $entry ): int
   {
      $err = 1;

      foreach ( $primary as $colName )
      {
         if ( $err < 1 )
         {
            break;
         }

         $colId = $this->getColumnIndex( $this->file_->table, $colName );

         foreach ( $this->file_->table->row as $row )
         {
            $value = ( empty( ( ( array ) $row->value )[ $colId ] ) ) ? null : ( ( array ) $row->value )[ $colId ];

            // verifica se a chave primária da entrada gera conflito com alguma linha ou é nula.
            if ( ( $entry[ $colId ] == $value ) || ( is_null( $entry[ $colId ] ) ) )
            {
               $err = 0;
               break;
            }
         }
      }

      return $err;
   }

   /**
    * Indica se uma coluna faz parte das chaves primárias de uma tabela.
    *
    * @param array $primaries
    *           Array das chaves primárias da tabela.
    * @param string $colName
    *           O nome da coluna para verificação.
    * @return bool
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
    * Ordena as colunas de acordo com o mapa da tabela.
    *
    * @param array $row
    *           Mapa de valores de uma linha, formato coluna => valor.
    * @return array Array coluna => valor ordenado como o mapa da tabela.
    */
   private function sortColumns( array $row ): array
   {
      $final = array ();
      foreach ( $row as $col => $val )
      {
         $indexArray[ $col ] = $this->getColumnIndex( $this->file_->table, $col );
      }

      asort( $indexArray );
      foreach ( $indexArray as $col => $val )
      {
         $final[ $col ] = $row[ $col ];
      }

      return $final;
   }
}
class XMLParserException extends Exception
{}

?>