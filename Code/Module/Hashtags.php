<?php

namespace Code\Module;

/*
 * Hashtag autocomplete controller
 *
 */

use Code\Web\Controller;

class Hashtags extends Controller
{

    public function init()
    {
        $result = [];

        $t = escape_tags($_REQUEST['t']);
        if (!$t) {
            json_return_and_die($result);
        }

        $r = q(
            "select distinct(term) from term where term like '%s' and ttype = %d order by term",
            dbesc($t . '%'),
            intval(TERM_HASHTAG)
        );
        if ($r) {
            foreach ($r as $rv) {
                $result[] = ['text' => $rv['term']];
            }
        }

        json_return_and_die($result);
    }
}
