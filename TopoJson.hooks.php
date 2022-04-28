<?php

use MediaWiki\Extension\MobilAmLand\Hooks as MobilAmLandHooks; 

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
	 *
	 * @return string
	 */
	public static function TopoJson( $text, $args, $parser, $frame ) {
		global $wgOut;
		$wgOut->addModules( 'ext.topojson' );
		$text = trim( $text );

		$gemeindeliste = [];
		$queries = [];
		$queries['bv'] = '[[Kategorie:Mikro-ÖV-System]][[Bundesland::+]][[aktiv::wahr]]';
		$queries['lv'] = '[[Kategorie:Linienverkehr]][[Bundesland::+]][[aktiv::wahr]]';
		$queries['cs'] = '[[Kategorie:CarSharing-System]][[Bundesland::+]][[aktiv::wahr]]';

		if( isset( $args['typ'] ) ) {
			$typen = explode(',',$args['typ']);
			$queries = array_intersect_key( $queries, array_flip( $typen ) );
		}

		if( $text != '' ) {
			$typ = isset( $args['typ'] ) ? $args['typ'] : 'single';
			$queries = [ $typ => $text ];
		}

		if( isset( $args['betriebsform'] ) ) {
			$queries = [ 'betriebsform' => '[[Kategorie:Mikro-ÖV-System]][[Bundesland::+]][[aktiv::wahr]][[Betriebsform::' . $args['betriebsform'] . ']]' ];
		}

		$orte = [];
		$filterheading = false;
		$filter = [];

		foreach( $queries as $typ => $query ) {
			$query = '{{#ask:' . $query . '
				|?Einschränkung=Einschraenkung
				|?Betriebsform
				|?Ort
				|?GKZ#
				|?FlexRaum
				|?FlexZeit
				|?Merkmal
				|?CarSharing-Software
				|?Software
				|?Jahr
				|?=Name
				|mainlabel=-
				|format=array
				|link=none
				|headers=plain
				|headersep==
				|sep=<BV>
			}}';

			$angebote = $parser->RecursiveTagParse( $query );
			$angebote = explode( '&lt;BV&gt;', $angebote );

			foreach( $angebote as $key => $props ) {
				$angebote[$key] = [];
				$prop_array = [];

				$props = explode( '&lt;PROP&gt;', $props );
				foreach( $props as &$prop ) {
					$prop = explode( '=', $prop );
					$prop_array[$prop[0]] = $prop[1];
				}
			
				$prop_array['GKZ'] = explode( '&lt;MANY&gt;', $prop_array['GKZ'] );

				$prop_array['Betriebsform'] = str_replace( "Betriebsform:", "", $prop_array['Betriebsform'] );
				
				if( isset( $args['filter'] ) ) {
					// Filter je nach Typ setzen
					if( $typ == 'cs' ) {
						$filterprop = $args['filter'] == '' ? 'Name' : $args['filter'];
						$filterheadings = [
							'Name' => 'Betreiber mit 10+ Gemeinden:',
						];
						if( isset( $filterheadings[$filterprop] ) ) {
							$filterheading = $filterheadings[$filterprop];
						}
						// wenn nach Name gefiltert wird, nur CarSharing-Angebote mit mehr als zehn Gemeinden anzeigen
						if( $filterprop != 'Name' || count( $prop_array['GKZ'] ) > 9 ) {
							$filterkey = self::clean_class( $prop_array[$filterprop] );
							$filter[$filterkey] = $prop_array[$filterprop];
						}
					}
					if( $typ == 'bv' ) {
						$filterprop = $args['filter'] == '' ? 'Bedienungsform' : $args['filter'];
						$filterheadings = [
							'Bedienungsform' => 'nach Bedienungsform filtern:',
						];
						if( isset( $filterheadings[$filterprop] ) ) {
							$filterheading = $filterheadings[$filterprop];
						}
						if( $filterprop == 'Bedienungsform' ) {
							$filterkey = self::clean_class( $prop_array['FlexZeit'] . '-' . $prop_array['FlexRaum'] );
							$filter[$filterkey] = '<span class="flex-icon flex-icon-' . self::clean_class( $prop_array['FlexZeit'] ) . '" title="' . $prop_array['FlexZeit'] . '"></span> <span class="flex-icon flex-icon-' . self::clean_class( $prop_array['FlexRaum'] ) . '" title="' . $prop_array['FlexRaum'] . '"></span>';
						} elseif( $filterprop == 'Jahr' ) {
							$filterkey = 'jahr-' . self::clean_class( $prop_array[$filterprop] );
							if( $prop_array[$filterprop] ) {
								$filter[$filterkey] = $prop_array[$filterprop];
							} else {
								unset( $filterkey );
							}
						} else {
							$filterkey = self::clean_class( $prop_array[$filterprop] );
							$filter[$filterkey] = $prop_array[$filterprop];
						}
					}
				}

				$angebote[$key]['Betriebsform'] = self::clean_class( $prop_array['Betriebsform'] );
				$angebote[$key]['Typ'] = $typ;
				$angebote[$key]['Name'] = $prop_array['Name'];
				$angebote[$key]['ID'] = self::clean_class( $prop_array['Name'] );
				$angebote[$key]['FlexRaum'] = self::clean_class( $prop_array['FlexRaum'] );
				$angebote[$key]['FlexZeit'] = self::clean_class( $prop_array['FlexZeit'] );
				if( isset( $filterkey ) ) {
					$angebote[$key]['Filter'] = $filterkey;
				}
				$merkmale = explode( '&lt;MANY&gt;', $prop_array['Merkmal'] );
				foreach( $merkmale as &$merkmal ) {
					$merkmal = self::clean_class( $merkmal );
				}
				$angebote[$key]['Merkmale'] = $merkmale;
				if( $prop_array['Einschraenkung'] ) {
					$angebote[$key]['Einschraenkung'] = str_replace( "&lt;MANY&gt;", "<br>", $prop_array['Einschraenkung'] );
				}

				foreach( $prop_array['GKZ'] as $ort ) {
					if( $ort != '' ) {
						$orte[$ort][] = $angebote[$key];
						$gemeindeliste[$ort] = MobilAmLandHooks::gemeinde(false, $ort);
					}
				}

				unset( $filterkey );
			}
		}

	    ksort( $filter );
		unset( $filter['-'] );
		unset( $filter[''] );

		$out = '<div id="bvMap" style="position:relative' . ( ( isset( $args['onclick'] ) ) ? ';display:none' : '' ) . '">
						<img src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCFET0NUWVBFIHN2ZyBQVUJ MSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3 MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KPHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJFYmVuZV8xI iB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8v d3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIKCSB3aWR0aD0iMnB4IiBoZWl naHQ9IjFweCIgdmlld0JveD0iMCAwIDIgMSIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgMi AxIiB4bWw6c3BhY2U9InByZXNlcnZlIj4KPC9zdmc+" style="width:100%">
						<div class="graphics" style="position:absolute;top:0">';

		/* Tooltips */
		$out .= '<div class="bv_tooltip no_display">
                <h5 class="bv_tooltip_ort"></h5><span class="bv_tooltip_hr"></span><h4 class="bv_tooltip_bv"></h4>
            </div>';
	    $out .= '</div></div>';    
    
		$out .= '<script>var gemeindeliste = ' . json_encode( $gemeindeliste ) . '</script>';
	    $out .= '<script>var bedarfsverkehre = ' . json_encode( $orte ) . '</script>';

	    if( !isset( $args['filter'] ) ) {
	    	$filter = '';
	   	}

	    $out .= '<script>var filter = ' . json_encode( $filter ) . '</script>';
	    $out .= '<script>var filterheading = ' . json_encode( $filterheading ) . '</script>';
	    if( isset( $args['onclick'] ) ) {
	    	$out .= '<a class="btn btn-default btn-xs" id="showMap">Karte zeigen</a>';
	   	} else {
	    	$out .= '<script>var showMap = true;</script>';
	   	}
 
		return $out;
	}

	/**
	 * Convert any random string into a classname following conventions.
	 * 
	 * - preserve valid characters, numbers and unicode alphabet
	 * - preserve already-formatted BEM-style classnames
	 * - convert to lowercase
	 *
	 * @see http://getbem.com/
	 */
	public static function clean_class($identifier) {

		// Convert or strip certain special characters, by convention.
		$filter = [
			' ' => '',
			'__' => '__', // preserve BEM-style double-underscores
			'_' => '-', // otherwise, convert single underscore to dash
			'/' => '-',
			'[' => '-',
			']' => '',
			'Ö' => 'oe',
			'ö' => 'oe',
			'Ü' => 'ue',
			'ü' => 'ue',
			'Ä' => 'ae',
			'ä' => 'ae'
		];
		$identifier = strtr($identifier, $filter);

		// Valid characters in a CSS identifier are:
		// - the hyphen (U+002D)
		// - a-z (U+0030 - U+0039)
		// - A-Z (U+0041 - U+005A)
		// - the underscore (U+005F)
		// - 0-9 (U+0061 - U+007A)
		// - ISO 10646 characters U+00A1 and higher
		// We strip out any character not in the above list.
		$identifier = preg_replace('/[^\\x{002D}\\x{0030}-\\x{0039}\\x{0041}-\\x{005A}\\x{005F}\\x{0061}-\\x{007A}\\x{00A1}-\\x{FFFF}]/u', '', $identifier);

		// Convert everything to lower case.
		return strtolower($identifier);
	}

}
