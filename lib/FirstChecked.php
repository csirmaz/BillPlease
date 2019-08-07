<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2014 by Elod Csirmaz <http://www.github.com/csirmaz>

   This program is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/** This class calculates the first items that are unchecked or marked with green or blue */

class FirstChecked {
    public $html_unc = ''; // Needs to be public as $me below cannot access it otherwise (PHP 5.3)
    public $js_unc = '';
    public $data = array();

    public function init() {
        $DB = Application::get()->db();
        $Solder = Application::get()->solder();

        $me = $this; // Needed by PHP 5.3
        $DB->query_callback(
            'select distinct accountto from costs',
            false,
            function ($racc) use ($DB, $Solder, $me) {
                $DB->query_callback(
                    'select accountto,year,month,day,id,unixday from costs where accountto=? and checked=0 order by year,month,day,id limit 1',
                    array($racc['accountto']),
                    function ($r) use ($Solder, $me) {
                        $me->html_unc .= $Solder->fuse(
                            'firstchecked_item',
                            array(
                                'acc' => $r['accountto'],
                                'y' => $r['year'],
                                'm' => $r['month'],
                                'd' => $r['day']
                            )
                        );
                        $me->js_unc .= $Solder->fuse(
                            'firstchecked_jsitem',
                            array('$id' => Item::static_item_id_css($r['id']), '$class' => 'bpfirst_unc')
                        );
                        
                        $me->data[$r['unixday']] .= $r['accountto'];
                    }
                );
            }
        );
    }

    /** Returns HTML to describe the first items */
    public function gethtml() {
        return $this->html_unc;
    }

    /** Returns JS code to mark the first items */
    public function getjs() {
        return $this->js_unc;
    }
    
    public function forday($ud) {
        if(isset($this->data[$ud])) {
            return $this->data[$ud];
        }
        return False;
    }

}

?>
