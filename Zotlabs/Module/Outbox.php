<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\Config;

/**
 * Implements an ActivityPub outbox.
 * Typically unused for Zot6, but *may* be useful in generating
 * a consumeable ActivityStreams feed for the desired channel.
 *
 */


class Outbox extends Controller {

	function init() {

		if (observer_prohibited(true)) {
			killme();
		}

		if (argc() < 2) {
			killme();
		}

		$channel = channelx_by_nick(argv(1));
		if (! $channel) { 
			killme();
		}

		if (intval($channel['channel_system'])) {
			killme();
		}
		
		if (ActivityStreams::is_as_request()) {
			$sigdata = HTTPSig::verify(($_SERVER['REQUEST_METHOD'] === 'POST') ? file_get_contents('php://input') : EMPTY_STR);
			if ($sigdata['portable_id'] && $sigdata['header_valid']) {
				$portable_id = $sigdata['portable_id'];
				if (! check_channelallowed($portable_id)) {
					http_status_exit(403, 'Permission denied');
				}
				if (! check_siteallowed($sigdata['signer'])) {
					http_status_exit(403, 'Permission denied');
				}
				observer_auth($portable_id);
			}
			elseif (Config::get('system','require_authenticated_fetch',false)) {
				http_status_exit(403,'Permission denied');
			}

			$observer_hash = get_observer_hash();

			$params = [];
	
			$params['begin']     = ((x($_REQUEST,'date_begin')) ? $_REQUEST['date_begin']       : NULL_DATE);
			$params['end']       = ((x($_REQUEST,'date_end'))   ? $_REQUEST['date_end']         : '');
			$params['type']      = 'json';
			$params['pages']     = ((x($_REQUEST,'pages'))      ? intval($_REQUEST['pages'])    : 0);
			$params['top']       = ((x($_REQUEST,'top'))        ? intval($_REQUEST['top'])      : 0);
			$params['direction'] = ((x($_REQUEST,'direction'))  ? dbesc($_REQUEST['direction']) : 'desc'); // unimplemented
			$params['cat']       = ((x($_REQUEST,'cat'))        ? escape_tags($_REQUEST['cat']) : '');
			$params['compat']    = 1;

		
			$total = items_fetch(
    	   		[
				'total'      => true,
	       	    'wall'       => '1',
    	       	'datequery'  => $params['end'],
	            'datequery2' => $params['begin'],
           		'direction'  => dbesc($params['direction']),
	           	'pages'      => $params['pages'],
	            'order'      => dbesc('post'),
    	   	    'top'        => $params['top'],
            	'cat'        => $params['cat'],
	       	    'compat'     => $params['compat']
    		   	], $channel, $observer_hash, CLIENT_MODE_NORMAL, App::$module
		    );

			if ($total) {
				App::set_pager_total($total);
				App::set_pager_itemspage(100);
			}

			if (App::$pager['unset'] && $total > 100) {		
				$ret = 	Activity::paged_collection_init($total,App::$query_string);
			}
			else {
				$items = items_fetch(
    			    [
        		    'wall'       => '1',
            		'datequery'  => $params['end'],
	            	'datequery2' => $params['begin'],
					'records'    => intval(App::$pager['itemspage']),
					'start'      => intval(App::$pager['start']),
        	    	'direction'  => dbesc($params['direction']),
	        	    'pages'      => $params['pages'],
    	        	'order'      => dbesc('post'),
	        	    'top'        => $params['top'],
    	        	'cat'        => $params['cat'],
	    	        'compat'     => $params['compat']
    		    	], $channel, $observer_hash, CLIENT_MODE_NORMAL, App::$module
	    		);
			
				$ret = Activity::encode_item_collection($items, App::$query_string, 'OrderedCollection',true, $total);
			}

			as_return_and_die($ret,$channel);
    	}
	}
}



