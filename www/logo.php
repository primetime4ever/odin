<?php

/*
   Odin - IP plan management and tracker
   Copyright (C) 2015-2016  Tobias Eliasson <arnestig@gmail.com>
                            Jonas Berglund <jonas.jberglund@gmail.com>
                            Martin Rydin <martin.rydin@gmail.com>

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License along
   with this program; if not, write to the Free Software Foundation, Inc.,
   51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

*/

include_once( "include/settings.php" );

$settingshandler = new Settings();

$imagedata = base64_decode($settingshandler->getSettingValue( 'logo' ));

$im = imagecreatefromstring($imagedata);
$imageinfo = getimagesizefromstring( $imagedata );
if ( $im !== false ) {
    if ( isset( $_REQUEST[ 'small' ] ) ) {
        $im = imagescale( $im, 70 ); 
    }
    imagesavealpha($im, true);
    header('Content-Type: '.$imageinfo[ 'mime' ]);

    switch( $imageinfo[ 'mime' ] ) { 
        case "image/jpeg": 
            imagejpeg($im);
            break; 
        case "image/png": 
            imagepng($im);
            break; 
    }

    imagedestroy($im);
}

?>
