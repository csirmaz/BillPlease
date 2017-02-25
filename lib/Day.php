<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2014,2017 by Elod Csirmaz <http://www.github.com/csirmaz>

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

// Class representing a day of expense items
class Day {
    private $uday; /*< a UnixDay object */
    private $DB; /*< a CostsDB object */

    private $sum;
    private $timedsum;
    private $items = array();
    private $longitems = array(); // recurring items affecting this day
    
    /** Constructor */
    public function __construct($DB, $year, $month, $day, $unixday = false) {
        $this->uday = ($unixday ? $unixday : UnixDay::from_ymd($year, $month, $day));
        $this->DB = $DB;
    }

    /** Alternative constructor */
    public static function from_unixday($DB, $unixday) {
        return new self($DB, false, false, false, new UnixDay($unixday));
    }

    public function get_sum() {
        $this->load_sums();
        return $this->sum;
    }

    public function get_timedsum() {
        $this->load_sums();
        return $this->timedsum;
    }

    public function get_js_date() {
        return $this->uday->js_date();
    }

    private function load_sums() {
        // TODO Adjust with rates
        if(!isset($this->sum)) {
            // Index: costs_acfr_ud_v
            $this->sum = $this->DB->querysingle(
                'select sum(value) from costs where unixday=? and istransfer!=1 and accountfrom=\'\'',
                array($this->uday->ud())
            ) / 100;
        }
        if(!isset($this->timedsum)) {
            // Index: costs_acfr_ud_udt_v_s
            $this->timedsum = $this->DB->querysingle(
                'select sum((value*1.0)/timespan) from costs where unixday<=? and unixdayto>? and istransfer!=1 and accountfrom=\'\'',
                array($this->uday->ud(), $this->uday->ud())
            ) / 100;
        }
    }

    public function load_items($nowday) {
        if(count($this->items) > 0) {
            return;
        }
        $this->DB->query_callback(
            'select * from costs where unixday=? order by id',
            array($this->uday->ud()),
            function ($r) use ($nowday, &$sum) {
                $item = Item::from_db($r);
                $item->set_nowday($nowday);
                $this->items[] = $item;                
            }
        );
    }

    public function load_long_items($nowday) {
        if(count($this->longitems) > 0) {
            return;
        }
        $this->DB->query_callback(
            'select * from costs where unixday<? and unixdayto>? order by unixday, id', // excluding today
            array($this->uday->ud(), $this->uday->ud()),
            function ($r) use ($nowday) {
                $item = Item::from_db($r);
                $item->set_nowday($nowday);
                $this->longitems[] = $item;
            }
        );
    }

    public function to_html() {

        // header
        $h = Html::table_header_row(
            false,
            $this->uday->year(),
            $this->uday->month(),
            $this->uday->day(),
            $this->uday->ud()
        );

        foreach($this->items as $item) {
            $h .= $item->to_html();
        }

        // footer
        $h .= Html::table_footer_row(
            Application::get()->solder()->fuse(
                'day_footer',
                array(
                    '$timedsum' => printsum($this->get_timedsum()),
                    '$sum' => printsum($this->get_sum()),
                    'ud' => $this->uday->ud(),
                    '$smileyclass' => ($this->get_timedsum() <= 0 ? 'smile' : 'frown')
                )
            )
        );

        return $h;
    }

    // Get HTML describing all elements affecting the current day
    public function get_long_info($UnixDayObj) {
        $this->load_items($UnixDayObj->ud());
        $this->load_long_items($UnixDayObj->ud());
        $out = '';
        /*
        foreach ($this->items as $item){
         $out .= $item->to_html_line();
        }
        */
        foreach($this->longitems as $item) {
            $out .= $item->to_html_line($this->uday);
        }
        return Application::get()->solder()->fuse(
            'longitems_modal_body',
            array('title' => $this->uday->simple_string(), '$rows' => $out)
        );
    }

}

?>