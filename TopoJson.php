<?php

if( !defined( 'MEDIAWIKI' ) ) die( "This is an extension to the MediaWiki package and cannot be run standalone." );

$wgExtensionCredits['custom XML Tag'][] = array(
    'path' => __FILE__,
    'name' => 'TopoJson',
    'author' => 'Tobias Haider', 
    'url' => 'https://topojson.thai-land.at', 
    'descriptionmsg' => 'topojson-desc',
    'version'  => '0.0.1'
);

$wgAutoloadClasses['TopoJson'] = __DIR__ . '/TopoJson.body.php';
$wgMessagesDirs['TopoJson'] = __DIR__ . '/i18n';

$wgHooks['ParserFirstCallInit'][] = 'wfSampleParserInit';
 
$wgResourceModules['ext.topojson'] = array(
	'scripts' => array( 'js/d3.js', 'js/topojson.min.js', 'js/gemeindeliste.js', 'js/karte.js' ),
	'styles' => 'css/topojson.css',
	'messages' => array( 'myextension-desc' ),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'TopoJson'
);


function wfSampleParserInit( Parser $parser ) {
	$parser->setHook( 'topojson', array( 'TopoJson', 'parserHook' ) );
	return true;
}
 
