(function ($) {
	
	var container_width = $('div.graphics').parent().width();
	
	var svg = null;
	var g = null;
	var div_graphics = null;
	var margin = { top: 0, right: 0, bottom: 0, left: 0 },
		width = container_width - margin.left - margin.right,
		height = width/2 - margin.top - margin.bottom;

	var map_states = null;
	var map_gems = null;
	var projection = null;
	var path = null;
	var scale = null;
	
	$( document ).ready( function() {
		if( showMap === true ) {
			init_topojson();
		}
	});
	
	$( '#showMap' ).click( function() {
		$( this ).hide();
		$( '#bvMap' ).show( 200, function() { init_topojson() } );
	});

	function init_topojson() {
		if (typeof d3 !== 'undefined') {
			// reset height and width
			container_width = $('div.graphics').parent().width();
			width = container_width - margin.left - margin.right;
			height = width/2 - margin.top - margin.bottom;
			init_map();
		}
	}

	function filter_geometry_bv( data ) {
		var geometries = data.objects.gemeinden.geometries;
		var geometries_filtered = new Array();
		var gfi = 0;
		var bv;
		
		for ( var i = 0; i < geometries.length; i++ ) {
			if ( typeof bedarfsverkehre1[geometries[i].properties.iso] !== 'undefined' ) {
				geometries_filtered[gfi] = geometries[i];
				classes = [];
				bv = bedarfsverkehre1[geometries[i].properties.iso];
				var einschraenkung = true;
				for ( var j = 0; j < bv.length; j++ ) {
					classes.push( bv[j].ID, bv[j].Betriebsform, bv[j].Typ, bv[j].FlexRaum, bv[j].FlexZeit, bv[j].FlexRaumZeit, bv[j].Filter );
					if( typeof bv[j].Einschraenkung == 'undefined' || bv[j].Einschraenkung === "" ) {
						bv[j].Einschraenkung = "";
						einschraenkung = false;
					}
					if( typeof bv[j].Merkmale != 'undefined' && bv[j].Merkmale.length > 0 ) {
						classes = classes.concat( bv[j].Merkmale );
					}
					if( bv[j].Bedienungsform === "Linienbetrieb" ) {
						classes.push('linie');
					}
				}
				if( einschraenkung ) {
					classes.push('bv_eing');
				}
				geometries_filtered[gfi].properties.classes = classes;
				gfi++;
			}
		}

		/* LOG NON-MATCHING BVs TO CONSOLE */
		/*
		var bedarfsverkehre_unshown = $.extend( {}, bedarfsverkehre ); // cloning bedarfsverkehre
		for( var i = 0; i < geometries_filtered.length; i++) {
			delete bedarfsverkehre_unshown[geometries_filtered[i].id];
		}
		console.log( bedarfsverkehre_unshown );
		*/
		
		data.objects.gemeinden.geometries = geometries_filtered;
		return data;
	} 

	function clicked_gem( gid ) {
		var gemeinde = d3.select( 'g.map-at-municipalities #gid-' + gid );

		// geklickte Gemeinde ist Gemeinde, für die Tooltip angezeigt wird
		if( gemeinde.classed( 'clicked' ) === true ) {
			gemeinde.classed( 'clicked', false );
			$( 'div.bv_tooltip' ).removeClass( 'clicked' );

		// es wird kein Tooltip angezeigt oder für eine andere Gemeinde
		} else {
			var tooltip_clicked = $( 'div.bv_tooltip' ).hasClass( 'clicked' );
			hide_tooltip();
			show_tooltip( gid, d3.mouse(div_graphics[0][0]));
			if( ! tooltip_clicked ) {
				gemeinde.classed( 'clicked', true );
				$( 'div.bv_tooltip' ).addClass( 'clicked' );
			}
		}
	}

	function show_tooltip(data, mouse_evt) {
		var tt = $( 'div.bv_tooltip' );
		var svg = $( 'div.graphics svg' );
		
		if( tt.hasClass( 'clicked' ) === false ) {
			var href_gemeinde = mw.config.get('wgServer') + mw.config.get('wgArticlePath').replace( '$1', gemeindeliste1[data] );
			tt.find('.bv_tooltip_ort').html( '<a href="' + encodeURI( href_gemeinde ) + '">' + gemeindeliste1[data] + '</a>' );
		
			tt.find('.bv_tooltip_bv').html( '' );
		
			$.each( bedarfsverkehre1[data], function( index, bv ) {
				var href = mw.config.get('wgServer') + mw.config.get('wgArticlePath').replace( '$1', bv.Name );
				$('.bv_tooltip_bv').append( '<div class="bv_tooltip_name"><a href="' + encodeURI( href ) + '">' + bv.Name + '</a><br><small>' + bv.Einschraenkung + '</small></div>' );
			});

			mouse_evt[0] += 15;
			mouse_evt[1] += 15;
			
			if( mouse_evt[0] > svg.attr("width") - 255 ) {
				mouse_evt[0] = mouse_evt[0] - 270;
			}
			if( mouse_evt[1] > svg.attr("height")/2 ) {
				mouse_evt[1] = mouse_evt[1] - tt.height() - 30;
			}

			tt.css( 'top', mouse_evt[1] + 'px' );
			tt.css( 'left', mouse_evt[0] + 'px' );
			tt.removeClass( 'no_display' );

			d3.select( 'g.map-at-municipalities #gid-' + data ).classed( 'highlight', true );
		}
	}

	function hide_tooltip() {
		d3.select( 'path.clicked' ).classed( 'clicked', false );
		$( 'div.bv_tooltip' ).removeClass( 'clicked' ).addClass( 'no_display' );
		d3.select( 'g.map-at-municipalities path.highlight' ).classed( 'highlight', false );
	}

	function hide_tooltip_if_unclicked() {
		if( $( 'div.bv_tooltip' ).hasClass( 'clicked' ) === false ) {
			$( 'div.bv_tooltip' ).addClass( 'no_display' );
			d3.select( 'g.map-at-municipalities path.highlight' ).classed( 'highlight', false );
		}
	}

	function zoomed() {
		projection.translate(d3.event.translate).scale(d3.event.scale);
		g.selectAll('path').attr('d', path);
	}

	function init_map() {
		// set dimension of dom-container _before_ loading of the json files to avoid 'jumping' of the page
		div_graphics = d3.select('div.graphics');
		svg = div_graphics.append('svg');
		svg.attr("width", width + margin.left + margin.right)
			.attr("height", height + margin.top + margin.bottom);
			
		d3.json( mw.config.get('wgServer') + mw.config.get('wgScriptPath') + "/extensions/TopoJson/json/oesterreich.json", function(error, aut) {
			// Create base map auf austria with state-borders
			scale = height * 13;

			projection = d3.geo.mercator()
				.center([13.4,47.7])
				.scale(scale)
				.translate([width / 2, height / 2]);

			path = d3.geo.path()
				.projection(projection);

			zoom = d3.behavior.zoom()
				.translate(projection.translate())
				.scale(projection.scale())
				.scaleExtent([13 * height, 60 * height])
				.on('zoom', zoomed);

			g = svg.append('g')
				.call(zoom);

			map_states = g.append('g');
			map_states.attr('class','map-at-states')
				.attr("transform", "translate(" + margin.left + "," + margin.top + ")");

			var states = topojson.feature(aut, aut.objects.aut);

			map_states.selectAll('.state')
				.data(states.features)
				.enter().append('path')
				.attr('d',path)
				.attr('id', function(d) { return d.properties.iso; })
				.attr('class', 'state');

			// load gemeinde-borders (todo thats not optimal)
			d3.json( mw.config.get('wgServer') + mw.config.get('wgScriptPath') + "/extensions/TopoJson/json/gemeinden-2021.json", function(error, aut_gem) {
				// 'echte' bedarfsverkehre
				bv = $.extend( true, {}, aut_gem );
				bv = filter_geometry_bv( bv );

				var gems_bv = topojson.feature(bv, bv.objects.gemeinden);

				map_bv = g.append('g');
				map_bv.attr('class','map-at-bv map-at-municipalities')
					.attr("transform", "translate(" + margin.left + "," + margin.top + ")");

				map_bv.selectAll('.bv')
					.data(gems_bv.features)
					.enter().append('path')
					.attr('d',path)
					.attr('id', function(d) { return 'gid-'+d.properties.iso; })
					.attr('class', function(d) { return 'gemeinde ' + d.properties.classes.join(' '); })
					.on("mouseover", function (d) 	{ show_tooltip( d.properties.iso, d3.mouse(div_graphics[0][0])); })
					.on("mousemove", function (d) 	{ show_tooltip( d.properties.iso, d3.mouse(div_graphics[0][0])); })
					.on("mouseout", function (d)	{ hide_tooltip_if_unclicked(); })
					.on("click", function (d)	{ d3.event.stopPropagation(); clicked_gem( d.properties.iso ); });


				$( 'div.graphics' ).append( '<div class="filter"></div>' );
				if( filter1 != '' ) {
					if( filterheading1 ) {
						$( '.filter' ).append( '<div class="filter-heading">' + filterheading1 + '</div>' );
					}
					$.each( filter1, function( key, value) {
						$( '.filter' ).append( '<div class="filter-value" data-filter-value="' + key + '">' + value + '</div>' );
						});
				}
				$(document).on( 'click', '[data-filter-value]', function() {
					d3.selectAll( '.filtered' ).classed( 'filtered', false );
					if( $(this).hasClass( 'active' ) ) {
						$(this).removeClass( 'active' );
						}
					else {
						d3.selectAll( '.' + $(this).attr( 'data-filter-value' ) ).classed( 'filtered', true );
						$( '[data-filter-value]' ).removeClass( 'active' );
						$(this).addClass( 'active' );
						}
					});

				$( '#bvMap1' ).click( function(e) {
					hide_tooltip();
				});

				// after loading and initial drawing is done, attach resizing (--> redrawing) functionality if the window size changes
				d3.select(window).on('resize', resize);

				function resize() {
					// update things when the window size changes
					container_width = $('div.graphics').parent().width();
					width = container_width - margin.left - margin.right;
					height = width/2 - margin.top - margin.bottom;
					scale = height * 13;

					// update projection
					projection
						.translate([width / 2, height / 2])
						.scale(scale);

					// resize the map container
					svg.attr("width", width + margin.left + margin.right)
					   .attr("height", height + margin.top + margin.bottom);

					// resize the map
					svg.selectAll('.state').attr('d', path);
					svg.selectAll('.bv').attr('d', path);
				}
			});
		});
	}
}(jQuery));
