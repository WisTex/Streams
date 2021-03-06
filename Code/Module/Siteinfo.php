<?php

namespace Code\Module;

use Code\Lib\System;
use Code\Lib\Config;
use Code\Web\Controller;
use Code\Extend\Hook;
use Code\Render\Theme;


class Siteinfo extends Controller
{

    public function get()
    {

        $federated = 'Nomad';
        if (Config::get('system', 'activitypub', true)) {
            $federated .= ', ActivityPub';
        }


        Hook::call('federated_transports', $federated);

        $siteinfo = replace_macros(
            Theme::get_template('siteinfo.tpl'),
            [
                '$title' => t('About this site'),
                '$url' => z_root(),
                '$sitenametxt' => t('Site Name'),
                '$sitename' => System::get_site_name(),
                '$headline' => t('Site Information'),
                '$site_about' => bbcode(get_config('system', 'siteinfo')),
                '$admin_headline' => t('Administrator'),
                '$admin_about' => bbcode(get_config('system', 'admininfo')),
                '$terms' => t('Terms of Service'),
                '$prj_header' => t('Software and Project information'),
                '$prj_name' => t('This site is powered by $Projectname'),
                '$prj_transport' => t('Federated and decentralised networking and identity services provided by Nomad'),
                '$transport_link' => '<a href="https://zotlabs.com">https://zotlabs.com</a>',

                '$ebs' => System::ebs(),
                '$additional_text' => t('Protocols:'),
                '$additional_fed' => $federated,
                '$prj_version' => ((get_config('system', 'hidden_version_siteinfo')) ? '' : sprintf(t('Version %s'), System::get_project_version())),
                '$prj_linktxt' => t('Project homepage'),
                '$prj_srctxt' => t('Developer homepage'),
                '$prj_link' => System::get_project_link(),
                '$prj_src' => System::get_project_srclink(),
                '$prj_icon' => System::get_site_icon(),
            ]
        );

        Hook::call('about_hook', $siteinfo);

        return $siteinfo;
    }
}
