<?php

class TopoJsonHooks {

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( "topojson", "TopoJsonHooks::TopoJson" );
		return true;
	}

	/**
	 * TopoJson
	 *
	 * @param string $text
	 * @param array $args
	 * @param Parser $parser
	 * @return string
	 */
	public static function TopoJson( $text, $args, $parser, $frame ) {
		global $wgOut;
		$wgOut->addModules( 'ext.topojson' );

		$gemeindeliste = [];

	    //reine Bedarfsverkehre
		$query = "{{#ask:[[Kategorie:Mikro-ÖV-System]][[Bundesland::+]][[aktiv::wahr]]
			|?Einschränkung=Einschraenkung
			|?Betriebsform
			|?Ort
			|?GKZ#
			|?FlexRaum
			|?FlexZeit
			|?=Name
			|mainlabel=-
			|format=array
			|link=none
			|headers=plain
			|headersep==
			|sep=<BV>
		}}";

	    //bestimmtes Bedarfsverkehrssystem
	    if( $text != '' ) {
			$query = "{{#ask:[[" . $text . "]]
				|?Einschränkung=Einschraenkung
				|?Betriebsform
				|?Ort
				|?GKZ#
				|?FlexRaum
				|?FlexZeit
				|?=Name
				|mainlabel=-
				|format=array
				|link=none
				|headers=plain
				|headersep==
				|sep=<BV>
			}}";
		}

	    $lvquery = "{{#ask:[[Kategorie:Linienverkehr]][[Bundesland::+]][[aktiv::wahr]]
			|?Einschränkung=Einschraenkung
			|?Betriebsform
			|?Ort
			|?GKZ#
			|?FlexRaum
			|?FlexZeit
			|?=Name
			|mainlabel=-
			|format=array
			|link=none
			|headers=plain
			|headersep==
			|sep=<LV>
		}}";

	    $orte = Array();
	    $betriebsformen = Array();

	    $bedarfsverkehre = $parser->RecursiveTagParse( $query );
	    $bedarfsverkehre = explode( '&lt;BV&gt;', $bedarfsverkehre );

	    foreach( $bedarfsverkehre as $key => $props ) {
	    	$bedarfsverkehre[$key] = [];
			$prop_array = [];

	    	$props = explode( '&lt;PROP&gt;', $props );
	    	foreach( $props as &$prop ) {
	    		$prop = explode( '=', $prop );
				$prop_array[$prop[0]] = $prop[1];
	   		}
    	
			$bf = $prop_array['Betriebsform'];
			$bf = str_replace( "Betriebsform:", "", $bf );
			$bf = strtolower( $bf );
			$bf = preg_replace( '/\W+$/', '', $bf );        // delete all trailing non-alphanumeric characters
			$bf = preg_replace( '/\s+/', '-', $bf );          // replace single or multiple spaces with a hyphen

			$betriebsformen[$bf] = str_replace( "Betriebsform:", "", $prop_array['Betriebsform'] );

	    	$bedarfsverkehre[$key]['Betriebsform'] = $bf;
			if( $prop_array['Einschraenkung'] ) {
	    		$bedarfsverkehre[$key]['Einschraenkung'] = str_replace( "&lt;MANY&gt;", "<br>", $prop_array['Einschraenkung'] );
			}
			$bedarfsverkehre[$key]['Name'] = $prop_array['Name'];
			$bedarfsverkehre[$key]['FlexRaum'] = $prop_array['FlexRaum'];
			$bedarfsverkehre[$key]['FlexZeit'] = $prop_array['FlexZeit'];

	    	$prop_array['GKZ'] = explode( '&lt;MANY&gt;', $prop_array['GKZ'] );
	    	foreach( $prop_array['GKZ'] as $ort ) {
				if( $ort != '' ) {
	    			$orte[$ort][] = $bedarfsverkehre[$key];
					$gemeindeliste[$ort] = UbiGoHooks::gemeinde(false, $ort);
				}
	    	}
   		}
	    ksort( $betriebsformen );


		// Linienverkehre nur, wenn es nicht um einzelnen Bedarfsverkehr geht
	    if( $text == '' ) {
			$linienverkehre = $parser->RecursiveTagParse( $lvquery );
			$linienverkehre = explode( '&lt;LV&gt;', $linienverkehre );

			foreach( $linienverkehre as $key => $props ) {
				$linienverkehre[$key] = [];
				$prop_array = [];

				$props = explode( '&lt;PROP&gt;', $props );
				foreach( $props as $prop ) {
					$prop = explode( '=', $prop );
					$prop_array[$prop[0]] = $prop[1];
				}
			
				if( $prop_array['Einschraenkung'] ) {
					$linienverkehre[$key]['Einschraenkung'] = str_replace( "&lt;MANY&gt;", "<br>", $prop_array['Einschraenkung'] );
				}
				$linienverkehre[$key]['Betriebsform'] = 'linienverkehr';
				$linienverkehre[$key]['Name'] = $prop_array['Name'];
				$prop_array['GKZ'] = explode( '&lt;MANY&gt;', $prop_array['GKZ'] );
				foreach( $prop_array['GKZ'] as $ort ) {
					if( $ort != '' ) {
						$orte[$ort][] = $linienverkehre[$key];
						$gemeindeliste[$ort] = UbiGoHooks::gemeinde(false, $ort);
					}
				}
			}
		}
    
		$out = '<div id="bvMap" style="position:relative' . ( ( isset( $args['onclick'] ) ) ? ';display:none' : '' ) . '">
						<img src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCFET0NUWVBFIHN2ZyBQVUJ MSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3 MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KPHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJFYmVuZV8xI iB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8v d3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIKCSB3aWR0aD0iMnB4IiBoZWl naHQ9IjFweCIgdmlld0JveD0iMCAwIDIgMSIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgMi AxIiB4bWw6c3BhY2U9InByZXNlcnZlIj4KPC9zdmc+" style="width:100%">
						<div class="graphics" style="position:absolute;top:0">';

		// Tooltip nur, wenn nicht einzelnes System
		if( $text == '' ) {
			$out .= '<div class="bv_tooltip no_display">
                <h5 class="bv_tooltip_ort"></h5><span class="bv_tooltip_hr"></span><h4 class="bv_tooltip_bv"></h4>
            </div>';
	   	}
	    $out .= '</div></div>';    
    
		$out .= '<script>var gemeindeliste = ' . json_encode( $gemeindeliste ) . '</script>';
	    $out .= '<script>var bedarfsverkehre = ' . json_encode( $orte ) . '</script>';

	    if( !isset( $args['filter'] ) ) {
	    	$betriebsformen = '';
	   	}

	    $out .= '<script>var betriebsformen = ' . json_encode( $betriebsformen ) . '</script>';
	    if( isset( $args['onclick'] ) ) {
	    	$out .= '<a class="btn btn-default btn-xs" id="showMap">Karte zeigen</a>';
	   	} else {
	    	$out .= '<script>var showMap = true;</script>';
	   	}
 
		return $out;
	}

}
