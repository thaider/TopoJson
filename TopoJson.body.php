<?php

class TopoJson {

	/**
	 * Parser hook
	 *
	 * @param string $text
	 * @param array $args
	 * @param Parser $parser
	 * @return string
	 */
	public static function parserHook( $text, $args, $parser, $frame ) {
		global $wgOut;
		wfProfileIn( __METHOD__ );
		$wgOut->addModules( 'ext.topojson' );

    $substitution = Array(
    		'ä' => 'ae',
    		'ü' => 'ue',
    		'ö' => 'oe',
    		'Ä' => 'ae',
    		'Ü' => 'ue',
    		'Ö' => 'oe',
    		'ß' => 'ss'
    		);
    
    //reine Bedarfsverkehre
    $query = "{{#ask:[[Bundesland::+]][[aktiv::wahr]]|?Einschränkung=Einschraenkung|?Betriebsform|?Ort|?FlexRaum|?FlexZeit|?=Name|mainlabel=-|format=array|link=none|headers=plain|headersep==|sep=<BV>}}";
    //bestimmtes Bedarfsverkehrssystem
    if( $text != '' ) $query = "{{#ask:[[" . $text . "]]|?Einschränkung=Einschraenkung|?Betriebsform|?Ort|?FlexRaum|?FlexZeit|?=Name|mainlabel=-|format=array|link=none|headers=plain|headersep==|sep=<BV>}}";
    $bedarfsverkehre = $parser->RecursiveTagParse( $query );
    $bedarfsverkehre = explode( '&lt;BV&gt;', $bedarfsverkehre );
    $orte = Array();
    $betriebsformen = Array();
    foreach( $bedarfsverkehre as $key => $props ) {
    	$props = explode( '&lt;PROP&gt;', $props );
    	$bedarfsverkehre[$key] = Array();
    	foreach( $props as $prop ) {
    		$prop = explode( '=', $prop );
    		$bedarfsverkehre[$key][$prop[0]] = $prop[1];
    		}
    	
			$bf = $bedarfsverkehre[$key]['Betriebsform'];
			$bf = str_replace( "Betriebsform:", "", $bf );
			$bf = strtolower( $bf );
			$bf = preg_replace( '/\W+$/', '', $bf );        // delete all trailing non-alphanumeric characters
			$bf = preg_replace( '/\s+/', '-', $bf );          // replace single or multiple spaces with a hyphen
    	$betriebsformen[$bf] = str_replace( "Betriebsform:", "", $bedarfsverkehre[$key]['Betriebsform'] );
    	$bedarfsverkehre[$key]['Betriebsform'] = $bf;
    	$bedarfsverkehre[$key]['Ort'] = explode( '&lt;MANY&gt;', $bedarfsverkehre[$key]['Ort'] );
    	$bedarfsverkehre[$key]['Einschraenkung'] = str_replace( "&lt;MANY&gt;", "<br>", $bedarfsverkehre[$key]['Einschraenkung'] );
    	foreach( $bedarfsverkehre[$key]['Ort'] as $ort ) {
    		$ort = strtr( strtolower( $ort ), $substitution );
    		$orte[$ort][] = $bedarfsverkehre[$key];
    		}
    	}
    ksort( $betriebsformen );
    
		$out = '<div id="bvMap" style="position:relative' . ( ( isset( $args['onclick'] ) ) ? ';display:none' : '' ) . '">
						<img src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCFET0NUWVBFIHN2ZyBQVUJ MSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3 MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KPHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJFYmVuZV8xI iB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8v d3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIKCSB3aWR0aD0iMnB4IiBoZWl naHQ9IjFweCIgdmlld0JveD0iMCAwIDIgMSIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgMi AxIiB4bWw6c3BhY2U9InByZXNlcnZlIj4KPC9zdmc+" style="width:100%">
						<div class="graphics" style="position:absolute;top:0">';
		// tooltip nur wenn nicht einzelnes system
		if( $text == '' ) {
			$out .= '<div class="bv_tooltip no_display">
                <h5 class="bv_tooltip_ort"></h5><span class="bv_tooltip_hr"></span><h4 class="bv_tooltip_bv"></h4>
            </div>';
    	}
    $out .= '</div></div>';    
    
    $out .= '<script>var bedarfsverkehre = ' . json_encode( $orte ) . '</script>';
    if( !isset( $args['filter'] ) ) {
    	$betriebsformen = '';
    	}
    $out .= '<script>var betriebsformen = ' . json_encode( $betriebsformen ) . '</script>';
    if( isset( $args['onclick'] ) ) {
    	$out .= '<a class="btn btn-default btn-xs" id="showMap">Karte zeigen</a>';
    	}
    else {
    	$out .= '<script>var showMap = true;</script>';
    	}
 
 		wfProfileOut( __METHOD__ );
		return $out;
	}

}