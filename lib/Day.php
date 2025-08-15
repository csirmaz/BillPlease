<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2013-2025 by Elod Csirmaz <https://www.epcsirmaz.co.uk/>

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

    private $sum; // daily balance (raw)
    private $timedsum; // "timed" daily balance, corrected by recurrence
    private $timed_weekly_sum; // "timed" balance of the previous 7 days
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
        if(!isset($this->sum)) {
            // Index: costs_acfr_ud_v
            $this->sum = $this->DB->querysingle(<<<'EOQ'
                select 
                    sum(costs.value * accountnames.rate) 
                from costs, accountnames 
                where 
                    costs.unixday = ? 
                    and costs.accountfrom = '' 
                    and accountnames.accounttofrom = costs.accountto
EOQ
                ,
                array($this->uday->ud())
            ) / 100;
        }
        return $this->sum;
    }

    public function get_timedsum() {
        // Get "timed" sum for the current day
        if(!isset($this->timedsum)) {
            // Index: costs_acfr_ud_udt_v_s
            // {TIMEDVALUE}*
            $this->timedsum = $this->DB->querysingle(<<<'EOQ'
                select 
                    sum(
                        (costs.value*1.0) * accountnames.rate / abs(costs.timespan)
                    ) 
                from costs, accountnames 
                where 
                    ((costs.unixday <= ? and costs.unixdayto > ?)
                        or (costs.unixdayto <= ? and costs.unixday > ?))
                    and costs.accountfrom = '' 
                    and accountnames.accounttofrom = costs.accountto
EOQ
                ,
                array($this->uday->ud(), $this->uday->ud(), $this->uday->ud(), $this->uday->ud())
            ) / 100;
        }
        return $this->timedsum;
    }
    
    public function get_timed_weekly_sum() {
        // Get the "timed" sum for the previous 7 days
        if(!isset($this->timed_weekly_sum)) {
            $sum = 0;
            Item::period_sum(
                $this->DB, 
                $this->uday->ud() - 6, // dayfrom (inclusive)
                $this->uday->ud(), // dayto (inclusive)
                TRUE, // timed
                FALSE, // debug
                function($item, $v, $debug, $log, $log_header) use (&$sum) {
                    if($item->get_ctype() != 'EXC') { // exclude this type
                        $sum += $v;
                    }
                }
            );
            $this->timed_weekly_sum = $sum;            
        }
        return $this->timed_weekly_sum;
    }
    
    /** Return the UnixDay object */
    public function get_uday() {
        return $this->uday;
    }

    public function get_js_date() {
        return $this->uday->js_date();
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
        // {TIMEDVALUE}*
        $this->DB->query_callback(
            'select * from costs where ((unixday<=? and unixdayto>?) or (unixdayto<=? and unixday>?)) order by unixday, id', // excluding today
            array($this->uday->ud(), $this->uday->ud(), $this->uday->ud(), $this->uday->ud()),
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
                    '$timed_weekly_sum' => printsum($this->get_timed_weekly_sum()),
                    '$timed_daily_sum' => printsum($this->get_timedsum()),
                    '$sum' => printsum($this->get_sum()),
                    'ud' => $this->uday->ud()
                )
            )
        );

        return $h;
    }

    // Get HTML describing all elements affecting the current day
    public function get_long_info($UnixDayObj) {
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
