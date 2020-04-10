<?php

namespace Zotlabs\Lib;


class LibBlock {

	static function store($arr) {

		$arr['block_entity'] = trim($arr['block_entity']);

		if (! $arr['block_entity']) {
			return false;
		}
		
		$arr['block_channel_id'] = ((array_key_exists('block_channel_id',$arr)) ? intval($arr['block_channel_id']) : 0);
		$arr['block_type'] = ((array_key_exists('block_type',$arr)) ? intval($arr['block_type']) : BLOCKTYPE_CHANNEL );
		$arr['block_comment'] = ((array_key_exists('block_comment',$arr)) ? escape_tags(trim($arr['block_comment'])) : EMPTY_STR);

		if (! intval($arr['block_id'])) {
			$r = q("select * from block where block_channel_id = %d and block_entity = '%s' limit 1",
				intval($arr['block_channel_id']),
				dbesc($arr['block_entity'])
			);
			if ($r) {
				$arr['block_id'] = $r[0]['block_id'];
			}
		}

		if (intval($arr['block_id'])) {
			return q("UPDATE block set block_channel_id = %d, block_entity = '%s', block_type = %d, block_comment = '%s' where block_id = %d",
					intval($arr['block_channel_id']),
					dbesc($arr['block_entity']),
					intval($arr['block_type']),
					dbesc($arr['block_comment']),
					intval($arr['block_id'])
			);
		}
		else {
			return create_table_from_array('block',$arr);
		}
	}

	static function remove($channel_id,$entity) {
		return q("delete from block where block_channel_id = %d and block_entity = '%s'",
			intval($channel_id),
			dbesc($entity)
		);
	}

	static function fetch_by_id($channel_id,$id) {

		$r = q("select * from block where block_channel_id = %d and block_id = %d ",
			intval($channel_id)
		);
		return (($r) ? array_shift($r) : $r);
	}


	static function fetch_by_entity($channel_id,$entity) {

		$r = q("select * from block where block_channel_id = %d and block_entity = '%s' ",
			intval($channel_id),
			dbesc($entity)
		);
		return (($r) ? array_shift($r) : $r);
	}

	static function fetch($channel_id,$type = false) {

		$sql_extra = (($type === false) ? EMPTY_STR : " and block_type = " . intval($type));
			
		$r = q("select * from block where block_channel_id = %d $sql_extra",
			intval($channel_id)
		);
		return $r;
	}

}