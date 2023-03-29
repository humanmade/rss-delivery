/**
 * Fuji TV Rss Delivery
 *
 * @package FujiTV
 */

/* global jQuery */

jQuery( document ).ready( function ( $ ) {
	$( '#trs-feed-button button' ).on( 'click', function (){
		if ( $( this ).attr( 'class' ) === 'check' ) {
			$( '#trs_feed_checkbox input' ).each( function (){
				if ( ! $( this ).attr( 'checked' ) ) {
					$( this ).prop( 'checked', true );
				}
			} );
		}
		if ( $( this ).attr( 'class' ) === 'uncheck' ) {
			$( '#trs_feed_checkbox input' ).each( function (){
				if ( $( this ).attr( 'checked' ) ) {
					$( this ).prop( 'checked', false );
				}
			} );
		}
	} );
} );
