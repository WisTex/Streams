<?php

namespace Code\Module\Admin;

/*
 * @file Profile_photo.php
 * @brief Module-file with functions for uploading and scaling of profile-photos
 *
 */

use App;
use Code\Web\Controller;
use Code\Lib\Libsync;
use Code\Lib\Libprofile;
use Code\Lib\Config;
use Code\Daemon\Run;
use Code\Lib\Channel;
use Code\Extend\Hook;
use Code\Render\Theme;

    
require_once('include/photo_factory.php');
require_once('include/photos.php');


class Profile_photo
{


    /* @brief Initalize the profile-photo edit view
     *
     * @return void
     *
     */

    public function init()
    {

        if (!is_site_admin()) {
            return;
        }

        $channel = Channel::get_system();
        Libprofile::load($channel['channel_address']);
    }

    /* @brief Evaluate posted values
     *
     * @param $a Current application
     * @return void
     *
     */

    public function post()
    {

        if (!is_site_admin()) {
            return;
        }

        $channel = Channel::get_system();

        check_form_security_token_redirectOnErr('/profile_photo', 'profile_photo');

        if ((array_key_exists('cropfinal', $_POST)) && (intval($_POST['cropfinal']) == 1)) {
            // logger('crop: ' . print_r($_POST,true));

            // phase 2 - we have finished cropping

            if (argc() != 3) {
                notice(t('Image uploaded but image cropping failed.') . EOL);
                return;
            }

            $image_id = argv(2);

            if (substr($image_id, -2, 1) == '-') {
                $scale = substr($image_id, -1, 1);
                $image_id = substr($image_id, 0, -2);
            }

            // unless proven otherwise
            $is_default_profile = 1;

            if ($_REQUEST['profile']) {
                $r = q(
                    "select id, profile_guid, is_default, gender from profile where id = %d and uid = %d limit 1",
                    intval($_REQUEST['profile']),
                    intval($channel['channel_id'])
                );
                if ($r) {
                    $profile = array_shift($r);
                    if (!intval($profile['is_default'])) {
                        $is_default_profile = 0;
                    }
                }
            }

            $srcX = intval($_POST['xstart']);
            $srcY = intval($_POST['ystart']);
            $srcW = intval($_POST['xfinal']) - $srcX;
            $srcH = intval($_POST['yfinal']) - $srcY;

            $r = q(
                "SELECT * FROM photo WHERE resource_id = '%s' AND uid = %d AND imgscale = %d LIMIT 1",
                dbesc($image_id),
                dbesc($channel['channel_id']),
                intval($scale)
            );
            if ($r) {
                $base_image = array_shift($r);
                $base_image['content'] = (($base_image['os_storage']) ? @file_get_contents(dbunescbin($base_image['content'])) : dbunescbin($base_image['content']));

                $im = photo_factory($base_image['content'], $base_image['mimetype']);
                if ($im->is_valid()) {
                    $im->cropImage(300, $srcX, $srcY, $srcW, $srcH);

                    $aid = 0;

                    $p = [
                        'aid' => $aid,
                        'uid' => $channel['channel_id'],
                        'resource_id' => $base_image['resource_id'],
                        'filename' => $base_image['filename'],
                        'album' => t('Profile Photos'),
                        'os_path' => $base_image['os_path'],
                        'display_path' => $base_image['display_path'],
                        'created' => $base_image['created'],
                        'edited' => $base_image['edited']
                    ];

                    $p['imgscale'] = PHOTO_RES_PROFILE_300;
                    $p['photo_usage'] = (($is_default_profile) ? PHOTO_PROFILE : PHOTO_NORMAL);

                    $r1 = $im->storeThumbnail($p, PHOTO_RES_PROFILE_300);

                    $im->scaleImage(80);
                    $p['imgscale'] = PHOTO_RES_PROFILE_80;

                    $r2 = $im->storeThumbnail($p, PHOTO_RES_PROFILE_80);

                    $im->scaleImage(48);
                    $p['imgscale'] = PHOTO_RES_PROFILE_48;

                    $r3 = $im->storeThumbnail($p, PHOTO_RES_PROFILE_48);

                    if ($r1 === false || $r2 === false || $r3 === false) {
                        // if one failed, delete them all so we can start over.
                        notice(t('Image resize failed.') . EOL);
                        $x = q(
                            "delete from photo where resource_id = '%s' and uid = %d and imgscale in ( %d, %d, %d ) ",
                            dbesc($base_image['resource_id']),
                            $channel['channel_id'],
                            intval(PHOTO_RES_PROFILE_300),
                            intval(PHOTO_RES_PROFILE_80),
                            intval(PHOTO_RES_PROFILE_48)
                        );
                        return;
                    }


                    $r = q(
                        "UPDATE photo SET photo_usage = %d WHERE photo_usage = %d
						AND resource_id != '%s' AND uid = %d",
                        intval(PHOTO_NORMAL),
                        intval(PHOTO_PROFILE),
                        dbesc($base_image['resource_id']),
                        intval($channel['channel_id'])
                    );

                    // We'll set the updated profile-photo timestamp even if it isn't the default profile,
                    // so that browsers will do a cache update unconditionally
                    // Also set links back to site-specific profile photo url in case it was
                    // changed to a generic URL by a clone operation. Otherwise the new photo may
                    // not get pushed to other sites correctly.

                    $r = q(
                        "UPDATE xchan set xchan_photo_mimetype = '%s', xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s'  
						where xchan_hash = '%s'",
                        dbesc($im->getType()),
                        dbesc(datetime_convert()),
                        dbesc(z_root() . '/photo/profile/l/' . $channel['channel_id']),
                        dbesc(z_root() . '/photo/profile/m/' . $channel['channel_id']),
                        dbesc(z_root() . '/photo/profile/s/' . $channel['channel_id']),
                        dbesc($channel['xchan_hash'])
                    );

                    // Similarly, tell the nav bar to bypass the cache and update the avatar image.
                    $_SESSION['reload_avatar'] = true;

                    Config::Set('system', 'site_icon_url', z_root() . '/photo/profile/m/' . $channel['channel_id']);

                    info(t('Shift-reload the page or clear browser cache if the new photo does not display immediately.') . EOL);
                } else {
                    notice(t('Unable to process image') . EOL);
                }
            }

            goaway(z_root() . '/admin');
        }

        // A new photo was uploaded. Store it and save some important details
        // in App::$data for use in the cropping function


        $hash = photo_new_resource();
        $importing = false;
        $smallest = 0;


        if ($_REQUEST['importfile']) {
            $hash = $_REQUEST['importfile'];
            $importing = true;
        } else {
            $matches = [];
            $partial = false;

            if (array_key_exists('HTTP_CONTENT_RANGE', $_SERVER)) {
                $pm = preg_match('/bytes (\d*)\-(\d*)\/(\d*)/', $_SERVER['HTTP_CONTENT_RANGE'], $matches);
                if ($pm) {
                    logger('Content-Range: ' . print_r($matches, true), LOGGER_DEBUG);
                    $partial = true;
                }
            }

            if ($partial) {
                $x = save_chunk($channel, $matches[1], $matches[2], $matches[3]);

                if ($x['partial']) {
                    header('Range: bytes=0-' . (($x['length']) ? $x['length'] - 1 : 0));
                    json_return_and_die($x);
                } else {
                    header('Range: bytes=0-' . (($x['size']) ? $x['size'] - 1 : 0));

                    $_FILES['userfile'] = [
                        'name' => $x['name'],
                        'type' => $x['type'],
                        'tmp_name' => $x['tmp_name'],
                        'error' => $x['error'],
                        'size' => $x['size']
                    ];
                }
            } else {
                if (!array_key_exists('userfile', $_FILES)) {
                    $_FILES['userfile'] = [
                        'name' => $_FILES['files']['name'],
                        'type' => $_FILES['files']['type'],
                        'tmp_name' => $_FILES['files']['tmp_name'],
                        'error' => $_FILES['files']['error'],
                        'size' => $_FILES['files']['size']
                    ];
                }
            }

            $res = attach_store($channel, $channel['channel_hash'], '', array('album' => t('Profile Photos'), 'hash' => $hash));

            logger('attach_store: ' . print_r($res, true), LOGGER_DEBUG);

            json_return_and_die(['message' => $hash]);
        }

        if (($res && intval($res['data']['is_photo'])) || $importing) {
            $i = q(
                "select * from photo where resource_id = '%s' and uid = %d order by imgscale",
                dbesc($hash),
                intval($channel['channel_hash'])
            );

            if (!$i) {
                notice(t('Image upload failed.') . EOL);
                return;
            }
            $os_storage = 1;

            foreach ($i as $ii) {
                if (intval($ii['imgscale']) < PHOTO_RES_640) {
                    $smallest = intval($ii['imgscale']);
                    $os_storage = intval($ii['os_storage']);
                    $imagedata = $ii['content'];
                    $filetype = $ii['mimetype'];
                }
            }
        }

        $imagedata = (($os_storage) ? @file_get_contents(dbunescbin($imagedata)) : dbunescbin($imagedata));
        $ph = photo_factory($imagedata, $filetype);

        if (!$ph->is_valid()) {
            notice(t('Unable to process image.') . EOL);
            return;
        }

        return $this->profile_photo_crop_ui_head($ph, $hash, $smallest);

        // This will "fall through" to the get() method, and since
        // App::$data['imagecrop'] is set, it will proceed to cropping
        // rather than present the upload form
    }


    /* @brief Generate content of profile-photo view
     *
     * @return void
     *
     */


    public function get()
    {

        if (!is_site_admin()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $channel = Channel::get_system();
        $pf = 0;
        $newuser = false;

        if (argc() == 3 && argv(2) === 'new') {
            $newuser = true;
        }

        if (argv(2) === 'reset') {
            Config::Delete('system', 'site_icon_url');
        }

        if (argv(2) === 'use') {
            if (argc() < 4) {
                notice(t('Permission denied.') . EOL);
                return;
            }

            $resource_id = argv(3);

            $pf = (($_REQUEST['pf']) ? intval($_REQUEST['pf']) : 0);

            $c = q(
                "select id, is_default from profile where uid = %d",
                intval($channel['channel_id'])
            );

            $multi_profiles = true;

            if (($c) && (count($c) === 1) && (intval($c[0]['is_default']))) {
                $_REQUEST['profile'] = $c[0]['id'];
                $multi_profiles = false;
            } else {
                $_REQUEST['profile'] = $pf;
            }

            $r = q(
                "SELECT id, album, imgscale FROM photo WHERE uid = %d AND resource_id = '%s' ORDER BY imgscale ASC",
                intval($channel['channel_id']),
                dbesc($resource_id)
            );
            if (!$r) {
                notice(t('Photo not available.') . EOL);
                return;
            }
            $havescale = false;
            foreach ($r as $rr) {
                if ($rr['imgscale'] == PHOTO_RES_PROFILE_80) {
                    $havescale = true;
                }
            }

            // set an already loaded and cropped photo as profile photo

            if ($havescale) {
                // unset any existing profile photos
                $r = q(
                    "UPDATE photo SET photo_usage = %d WHERE photo_usage = %d AND uid = %d",
                    intval(PHOTO_NORMAL),
                    intval(PHOTO_PROFILE),
                    intval($channel['channel_id'])
                );

                $r = q(
                    "UPDATE photo SET photo_usage = %d WHERE uid = %d AND resource_id = '%s'",
                    intval(PHOTO_PROFILE),
                    intval($channel['channel_id']),
                    dbesc($resource_id)
                );

                $r = q(
                    "UPDATE xchan set xchan_photo_date = '%s' where xchan_hash = '%s'",
                    dbesc(datetime_convert()),
                    dbesc($channel['xchan_hash'])
                );

                goaway(z_root() . '/admin');
            }

            $r = q(
                "SELECT content, mimetype, resource_id, os_storage FROM photo WHERE id = %d and uid = %d limit 1",
                intval($r[0]['id']),
                intval($channel['channel_id'])
            );
            if (!$r) {
                notice(t('Photo not available.') . EOL);
                return;
            }

            if (intval($r[0]['os_storage'])) {
                $data = @file_get_contents(dbunescbin($r[0]['content']));
            } else {
                $data = dbunescbin($r[0]['content']);
            }

            $ph = photo_factory($data, $r[0]['mimetype']);
            $smallest = 0;
            if ($ph->is_valid()) {
                // go ahead as if we have just uploaded a new photo to crop
                $i = q(
                    "select resource_id, imgscale from photo where resource_id = '%s' and uid = %d order by imgscale",
                    dbesc($r[0]['resource_id']),
                    intval($channel['channel_id'])
                );

                if ($i) {
                    $hash = $i[0]['resource_id'];
                    foreach ($i as $ii) {
                        if (intval($ii['imgscale']) < PHOTO_RES_640) {
                            $smallest = intval($ii['imgscale']);
                        }
                    }
                }
            }

            if ($multi_profiles) {
                App::$data['importfile'] = $resource_id;
            } else {
                $this->profile_photo_crop_ui_head($ph, $hash, $smallest);
            }

            // falls through with App::$data['imagecrop'] set so we go straight to the cropping section
        }

        // present an upload form

        $profiles = q(
            "select id, profile_name as name, is_default from profile where uid = %d order by id asc",
            intval($channel['channel_id'])
        );

        if ($profiles) {
            for ($x = 0; $x < count($profiles); $x++) {
                $profiles[$x]['selected'] = false;
                if ($pf && $profiles[$x]['id'] == $pf) {
                    $profiles[$x]['selected'] = true;
                }
                if ((!$pf) && $profiles[$x]['is_default']) {
                    $profiles[$x]['selected'] = true;
                }
            }
        }

        $importing = ((array_key_exists('importfile', App::$data)) ? true : false);

        if (!array_key_exists('imagecrop', App::$data)) {
            $tpl = Theme::get_template('admin_profile_photo.tpl');

            $o .= replace_macros($tpl, [
                '$user' => $channel['channel_address'],
                '$channel_id' => $channel['channel_id'],
                '$info' => ((count($profiles) > 1) ? t('Your default profile photo is visible to anybody on the internet. Profile photos for alternate profiles will inherit the permissions of the profile') : t('Your site photo is visible to anybody on the internet and may be distributed to other websites.')),
                '$importfile' => (($importing) ? App::$data['importfile'] : ''),
                '$lbl_upfile' => t('Upload File:'),
                '$lbl_profiles' => t('Select a profile:'),
                '$title' => (($importing) ? t('Use Photo for Site Logo') : t('Change Site Logo')),
                '$submit' => (($importing) ? t('Use') : t('Upload')),
                '$profiles' => $profiles,
                '$single' => ((count($profiles) == 1) ? true : false),
                '$profile0' => $profiles[0],
                '$embedPhotos' => t('Use a photo from your albums'),
                '$embedPhotosModalTitle' => t('Use a photo from your albums'),
                '$embedPhotosModalCancel' => t('Cancel'),
                '$embedPhotosModalOK' => t('OK'),
                '$modalchooseimages' => t('Choose images to embed'),
                '$modalchoosealbum' => t('Choose an album'),
                '$modaldiffalbum' => t('Choose a different album'),
                '$modalerrorlist' => t('Error getting album list'),
                '$modalerrorlink' => t('Error getting photo link'),
                '$modalerroralbum' => t('Error getting album'),
                '$form_security_token' => get_form_security_token("profile_photo"),
                '$select' => t('Select previously uploaded photo'),
            ]);

            Hook::call('profile_photo_content_end', $o);
            return $o;
        } else {
            // present a cropping form

            $filename = App::$data['imagecrop'] . '-' . App::$data['imagecrop_resolution'];
            $resolution = App::$data['imagecrop_resolution'];
            $o .= replace_macros(Theme::get_template('admin_cropbody.tpl'), [
                '$filename' => $filename,
                '$profile' => intval($_REQUEST['profile']),
                '$resource' => App::$data['imagecrop'] . '-' . App::$data['imagecrop_resolution'],
                '$image_url' => z_root() . '/photo/' . $filename,
                '$title' => t('Crop Image'),
                '$desc' => t('Please adjust the image cropping for optimum viewing.'),
                '$form_security_token' => get_form_security_token("profile_photo"),
                '$done' => t('Done Editing')
            ]);
            return $o;
        }
    }

    /* @brief Generate the UI for photo-cropping
     *
     * @param $ph Photo-Factory
     * @return void
     *
     */

    public function profile_photo_crop_ui_head($ph, $hash, $smallest)
    {
        $max_length = get_config('system', 'max_image_length', MAX_IMAGE_LENGTH);

        if ($max_length > 0) {
            $ph->scaleImage($max_length);
        }

        App::$data['width'] = $ph->getWidth();
        App::$data['height'] = $ph->getHeight();

        if (App::$data['width'] < 500 || App::$data['height'] < 500) {
            $ph->scaleImageUp(400);
            App::$data['width'] = $ph->getWidth();
            App::$data['height'] = $ph->getHeight();
        }

        App::$data['imagecrop'] = $hash;
        App::$data['imagecrop_resolution'] = $smallest;
        App::$page['htmlhead'] .= replace_macros(Theme::get_template('crophead.tpl'), []);
        return;
    }
}
