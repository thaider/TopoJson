(function ($) {
			
			var container_width = $('div.graphics').parent().width();
			
			var svg = null;
			var div_graphics = null;
			var margin = {top: 0, right: 0, bottom: 0, left: 0},
			    width = container_width - margin.left - margin.right,
			    height = width/2 - margin.top - margin.bottom;

			var map_states = null;
			var map_gems = null;
			var projection = null;
			var path = null;
			var scale = null;
			
			$( document ).ready( function() {
				if( typeof showMap !== 'undefined' ) {
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
					} else {
						insert_static_map();
						// at least should display the dropdown buttons
					}
				}

			function filter_geometry_bv( data ) {
				var geometries = data.objects.grenzenaut.geometries;
				var geometries_filtered = new Array();
				var gfi = 0;
				var bv;
				
				for ( var i = 0; i < geometries.length; i++ ) {
					if ( typeof bedarfsverkehre[geometries[i].id] !== 'undefined' ) {
						geometries_filtered[gfi] = geometries[i];
						geometries_filtered[gfi].properties = new Array();
						bv = bedarfsverkehre[geometries[i].id];
						geometries_filtered[gfi].properties.einschraenkung = "bv_eing";
						geometries_filtered[gfi].properties.linienverkehr = "";
						geometries_filtered[gfi].properties.betriebsform = "";
						for ( var j = 0; j < bv.length; j++ ) {
							geometries_filtered[gfi].properties.betriebsform = geometries_filtered[gfi].properties.betriebsform + ' ' + bv[j].Betriebsform;
							if( typeof bv[j].Einschraenkung == 'undefined' || bv[j].Einschraenkung === "" ) {
								bv[j].Einschraenkung = "";
								geometries_filtered[gfi].properties.einschraenkung = "";
							}
							if( bv[j].Bedienungsform === "Linienbetrieb" ) {
								geometries_filtered[gfi].properties.linienverkehr = "linie";
							}
						}
						gfi++;
					}
				}

				/* LOG NON-MATCHING BVs TO CONSOLE */
				/*
				var bedarfsverkehre_unshown = $.extend( {}, bedarfsverkehre ); // cloning bedarfsverkehre
				for( var i = 0; i < geometries_filtered.length; i++) {
					delete bedarfsverkehre_unshown[geometries_filtered[i].properties.name.toLowerCase()];
					}
				console.log( bedarfsverkehre_unshown );
				*/
				
				data.objects.grenzenaut.geometries = geometries_filtered;
				return data;
			} 

			function filter_geometry_bv_eing( data ) {
				var geometries = data.objects.gemeinden.geometries;
				var geometries_filtered = new Array();
				var gfi = 0;
								
				for (var i = 0; i < geometries.length; i++) {
					if ( typeof bedarfsverkehre_eing[geometries[i].properties.name.toLowerCase()] !== 'undefined' ) {
						geometries_filtered[gfi] = geometries[i];
						gfi++;
					}
				}

				/* LOG NON-MATCHING BVs TO CONSOLE */
				/*
				var bedarfsverkehre_unshown = bedarfsverkehre;
				for( var i = 0; i < geometries_filtered.length; i++) {
					delete bedarfsverkehre_unshown[geometries_filtered[i].properties.name.toLowerCase()];
					}
				console.log( bedarfsverkehre_unshown );
				*/
				
				data.objects.gemeinden.geometries = geometries_filtered;
				return data;
			} 
			
			
			function clicked_gem( gid ) {
				var gemeinde = d3.select( 'g.map-at-municipalities #gid-' + gid );
				if( gemeinde.classed( 'clicked' ) === true ) {
					gemeinde.classed( 'clicked', false );
					$( 'div.bv_tooltip' ).removeClass( 'clicked' );
					}
				else {
					gemeinde.classed( 'clicked', true );
					$( 'div.bv_tooltip' ).addClass( 'clicked' );
					}
			}

			function show_tooltip(data, mouse_evt) {
				var tt = $( 'div.bv_tooltip' );
				var svg = $( 'div.graphics svg' );
				
				if( tt.hasClass( 'clicked' ) === false ) {
					tt.find('.bv_tooltip_ort').html( gemeindeliste[data] );
				
					tt.find('.bv_tooltip_bv').html( '' );
				
					$.each( bedarfsverkehre_ges[data], function( index, bv ) {
						var href = mw.config.get('wgServer') + mw.config.get('wgArticlePath').replace( '$1', bv.Name );
						$('.bv_tooltip_bv').append( '<div class="bv_tooltip_name"><a href="' + href + '">' + bv.Name + '</a><br><small>' + bv.Einschraenkung + '</small></div>' );
						});

					mouse_evt[0] += 15;
					mouse_evt[1] += 15;
					
					if( mouse_evt[0] > svg.attr("width") - 205 ) {
						mouse_evt[0] = mouse_evt[0] - 220;
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
				if( $( 'div.bv_tooltip' ).hasClass( 'clicked' ) === false ) {
					$( 'div.bv_tooltip' ).addClass( 'no_display' );
					d3.select( 'g.map-at-municipalities path.highlight' ).classed( 'highlight', false );
					}
			}

			function insert_static_map() {
				/* TODO */
				/*
				var graphics = $('div.graphics');
				graphics.html('<img width="100%" src="./sites/all/themes/offenerhaushalt/img/frontpage_map_at_static_1170_585.png">');
				*/
			}

			function init_map() {
				// set dimension of dom-container _before_ loading of the json files to avoid 'jumping' of the page
				div_graphics = d3.select('div.graphics');
				svg = div_graphics.append('svg');
				svg.attr("width", width + margin.left + margin.right)
					.attr("height", height + margin.top + margin.bottom);
					
//				bedarfsverkehre_ges = $.extend( {}, bedarfsverkehre, bedarfsverkehre_eing );
				bedarfsverkehre_ges = $.extend( {}, bedarfsverkehre );

				d3.json( mw.config.get('wgServer') + mw.config.get('wgScriptPath') + "/extensions/TopoJson/json/oesterreich.json", function(error, aut) {
					// Create base map auf austria with state-borders
					scale = height * 13;

					projection = d3.geo.mercator()
						.center([13.4,47.7])
						.scale(scale)
						.translate([width / 2, height / 2]);

					path = d3.geo.path()
						.projection(projection);

					map_states = svg.append('g');
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
					d3.json( mw.config.get('wgServer') + mw.config.get('wgScriptPath') + "/extensions/TopoJson/json/gemeinden.json", function(error, aut_gem) {
						// 'echte' bedarfsverkehre
						bv = $.extend( true, {}, aut_gem );
						bv = filter_geometry_bv( bv );

						var gems_bv = topojson.feature(bv, bv.objects.grenzenaut);

						map_bv = svg.append('g');
						map_bv.attr('class','map-at-bv map-at-municipalities')
							.attr("transform", "translate(" + margin.left + "," + margin.top + ")");

						map_bv.selectAll('.bv')
							.data(gems_bv.features)
							.enter().append('path')
							.attr('d',path)
							.attr('id', function(d) { return 'gid-'+d.id; })
							.attr('class', function(d) { return 'bv '+d.properties.einschraenkung+' '+d.properties.linienverkehr+' '+d.properties.betriebsform; })
							.on("mouseover", function (d) 	{ show_tooltip( d.id, d3.mouse(div_graphics[0][0])); })
							.on("mousemove", function (d) 	{ show_tooltip( d.id, d3.mouse(div_graphics[0][0])); })
							.on("mouseout", function (d)	{ hide_tooltip(); })
							.on("click", function (d)	{ clicked_gem( d.id ); });


						$( 'div.graphics' ).append( '<div class="filter"></div>' );
						if( betriebsformen != '' ) {
							$.each( betriebsformen, function( key, value) {
								$( '.filter' ).append( '<div class="filter-betriebsform" data-betriebsform="' + key + '">' + value + '</div>' );
								});
						}
						$( '[data-betriebsform]' ).click( function() {
							d3.selectAll( '.bv' ).classed( 'filtered', false );
							if( $(this).hasClass( 'active' ) ) {
								$(this).removeClass( 'active' );
								}
							else {
								d3.selectAll( '.' + $(this).attr( 'data-betriebsform' ) ).classed( 'filtered', true );
								$( '[data-betriebsform]' ).removeClass( 'active' );
								$(this).addClass( 'active' );
								}
							});

/*						// eingeschränkte bedarfsverkehre
						bv_eing = $.extend( true, {}, aut_gem );
						bv_eing = filter_geometry_bv_eing( bv_eing );

						var gems_bv_eing = topojson.feature(bv_eing, bv_eing.objects.gemeinden);

						map_bv_eing = svg.append('g');
						map_bv_eing.attr('class','map-at-bv-eing map-at-municipalities')
							.attr("transform", "translate(" + margin.left + "," + margin.top + ")");

						map_bv_eing.selectAll('.bv_eing')
							.data(gems_bv_eing.features)
							.enter().append('path')
							.attr('d',path)
							.attr('id', function(d) { return 'gid-'+d.properties.iso; })
							.attr('class', function(d) { return 'bv_eing'; })
							.on("mouseover", function (d) 	{ show_tooltip( { gid: d.properties.iso, name: d.properties.name },d3.mouse(div_graphics[0][0])); })
							.on("mousemove", function (d) 	{ show_tooltip({ gid: d.properties.iso, name: d.properties.name },d3.mouse(div_graphics[0][0])); })
							.on("mouseout", function (d)	{ hide_tooltip(); })
							.on("click", function (d)	{ clicked_gem( d.properties.iso ); });
*/
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
