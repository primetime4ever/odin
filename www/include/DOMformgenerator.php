<?php

include_once( "include/odinhtml.php" );

class FormGenerator
{
    private $inputs = array();
    private $staticData = array();
    private $columns = array();
    private $submits = array();
    private $formitems = array();
    private $htmldoc;
    private $method;
    private $action;
    private $dataType; # change to enum, static or dynamic
    private $counterName;
    public function __construct($doc, $datatype, $method, $action )
    {
        $this->htmldoc = $doc; 
        /* or one could create a new doc and 
           use import instead of append
           on the calling side */

        $this->dataType = $datatype;
        $this->method = $method;
        $this->action = $action;
    }

    public function addInput( $name, $input_type, $fullname_mapping, $value_mapping )
    {
        $add_input = array(
                        'name' => $name,
                        'type' => $input_type,
                        'fullname_mapping' => $fullname_mapping,
                        'value_mapping' => $value_mapping );
        array_push( $this->inputs, $add_input );
    }

    public function addSubmit( $name, $value, $displayname )
    {
        $add_submit = array(
                        'name' => $name,
                        'value' => $value,
                        'displayname' => $displayname );
        array_push( $this->submits, $add_submit );

    }

    public function addStaticData( $fullname, $type, $name )
    {
        $addStaticData = array(
                            'fullname' => $fullname,
                            'type' => $type,
                            'name' => $name );
        array_push( $this->staticData, $addStaticData );
    }

    public function setColumnNames( $array_list )
    {
        $this->columns = $array_list;
    }

    public function setCounterName( $counter_name )
    {
        $this->counterName = $counter_name;
    }

    private function appendStaticData( $formNode )
    {
        foreach ( $this->staticData as $data ) {
            $rowNode = $this->htmldoc->createElement('tr');
            $fragment = $this->htmldoc->createDocumentFragment();
            if ( $data[ 'type' ] === 'text' ) {
                $html = sprintf( '<td>%s:</td><td><INPUT type="text" name="%s"/></td>', $data[ 'fullname' ], $data[ 'name' ] );
            } else {
                $html = sprintf( '<INPUT type="%s" name="%s"/>', $data[ 'type' ], $data[ 'name'] );
            }

            $fragment->appendXML( $html );
            $rowNode->appendChild($fragment);
            $formNode->appendChild($rowNode);
        }
    }

    private function appendDynamicData( $formNode )
    {
        for ( $item_id = 0; $item_id < count( $this->formitems ); $item_id++ ) {
            $rowNode = $this->htmldoc->createElement('tr');
            foreach ( $this->inputs as $input ) {
                $fragment = $this->htmldoc->createDocumentFragment();
                $inputname = $input[ 'name' ].$item_id;
                $inputtype = $input[ 'type' ];
                $inputvalue = $this->formitems[ $item_id ][ $input[ 'value_mapping' ] ];
                if ( $inputtype === 'text' ) {
                    $inputfullname = $this->formitems[ $item_id ][ $input[ 'fullname_mapping' ] ];
                    $html = sprintf( '<td>%s:</td><td><INPUT type="text" name="%s" value="%s"/></td>', $inputfullname, $inputname, $inputvalue );
                } else {
                    $html = sprintf( '<INPUT type="%s" name="%s" value="%s"/>', $inputtype, $inputname, $inputvalue );
                }

                $fragment->appendXML( $html );
                $rowNode->appendChild($fragment);
            }
            $formNode->appendChild($rowNode);
        }
    }

    public function generateHTML()
    {
        $tableNode = $this->htmldoc->createTable('settingstbl','settingstbl','');
        $formNode = $this->htmldoc->createForm('settingsform','settingsform','',$this->method,$this->action);

        $rowNode = $this->htmldoc->createElement('tr');
        foreach ( $this->columns as $column ) {
            $colNode = $this->htmldoc->createElement('th', $column );
            $rowNode->appendChild($colNode);
        }
        $tableNode->appendChild($rowNode);
        
        if ( $this->dataType === 'dynamic' ) {
            $this->appendDynamicData( $formNode );
        } elseif ( $this->dataType === 'static' ) {
            $this->appendStaticData( $formNode );
        }

        # set our index counter
        if ( $this->counterName ) {
            $counterNode = $this->htmldoc->createElement( 'input' );
            $counterNode->setAttribute( 'name', $this->counterName );
            $counterNode->setAttribute( 'type', 'hidden' );
            $counterNode->setAttribute( 'value', count( $this->formitems ) );
            $formNode->appendChild( $counterNode );
        }

        # set submit nodes
        if ( $this->submits ) {
            $rowNode = $this->htmldoc->createElement('tr');
            $submitNode = $this->htmldoc->createElement('td');
            $columns = $tableNode->getElementsByTagName('th')->{'length'};
            $submitNode->setAttribute( 'colspan', $columns );
            $submitNode->setAttribute( 'align', 'right' );
            foreach ( $this->submits as $submit ) {
                $fragment = $this->htmldoc->createDocumentFragment();
                $html = sprintf( '<BUTTON type="submit" name="%s" value="%s">%s</BUTTON>', $submit[ 'name'], $submit[ 'value' ], $submit[ 'displayname' ] );
                $fragment->appendXML( $html );
                $submitNode->appendChild($fragment);
            }

            $rowNode->appendChild($submitNode);
        }

        $formNode->appendChild($rowNode);
        $tableNode->appendChild($formNode);
        return $tableNode;
    }

    public function setDynamicData( $multidimensional_array )
    {
        $this->formitems = $multidimensional_array;
    }
}

?>