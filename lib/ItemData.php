<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2016 by Elod Csirmaz <http://www.github.com/csirmaz>

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

/** Class representing an expense item */
class ItemData {
    protected $id;

    protected $uday; /*< UnixDay object */
    protected $udayto; /*< UnixDay object; $uday+$timespan */

    protected $name;
    protected $value; /*< in the object, not an integer; in the DB, multiplied by 100 */
    protected $timespan;
    protected $accountto;
    protected $accountfrom;
    protected $checked;
    protected $istransfer;
    protected $ctype; /*< in the object, can be 'X'; in the DB, empty string is used */
    protected $business;
    protected $clong;
    protected $infuture = false; /*< Not in DB. Depends on current time. */
    protected $activelong = false; /*< Not in DB. Depends on current time. */

    /** Expects an array keyed on property names and with appropriate values */
    private function __construct($attrs = array()) {
        foreach($attrs as $k => $v) {
            if(property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
    }

    /** Constructs an object from data from the DB */
    public static function from_db($attrs = array()) {
        $attrs['value'] /= 100;
        if(!$attrs['ctype']) {
            $attrs['ctype'] = 'X';
        }
        $attrs['uday'] = new UnixDay($attrs['unixday']);
        $attrs['udayto'] = new UnixDay($attrs['unixdayto']);
        $item = new Item($attrs);
        return $item;
    }

    /** Alternative constructor */
    public static function new_empty_on($year, $month, $day) {
        return self::from_raw(array('id' => -1, 'year' => $year, 'month' => $month, 'day' => $day));
    }

    /** Alternative constructor */
    public static function new_empty_on_uday($uday) {
        return self::from_raw(array('id' => -1, 'unixday' => $uday));
    }

    /** Constructs an object from raw, possibly incomplete data.
     *
     * Extra keys in $attrs: 'year', 'month', 'day', 'unixday', 'unixdayto'
     * Year, month, day and unixday are calculated from each other.
     */
    public static function from_raw($attrs = array()) {

        // accounts (two characters) -> accountto, accountfrom (one character each)
        if(isset($attrs['accounts'])) {
            $attrs['accountto'] = substr($attrs['accounts'], 0, 1);
            $attrs['accountfrom'] = substr($attrs['accounts'], 1, 1);
        }
        // fix business -> 1/0
        $attrs['business'] = (isset($attrs['business']) && $attrs['business']) ? 1 : 0;
        // fix istransfer -> 1/0
        $attrs['istransfer'] = (isset($attrs['istransfer']) && $attrs['istransfer']) ? 1 : 0;
        // fix checked
        if((!isset($attrs['checked'])) || $attrs['checked'] == '') {
            $attrs['checked'] = 0;
        }
        $attrs['checked'] = ($attrs['checked'] ? 2 : 0); // {CHECKEDVALUE}
        // fix ctype
        if((!isset($attrs['ctype'])) || (!$attrs['ctype'])) {
            $attrs['ctype'] = 'X';
        }

        // get unixday
        if(isset($attrs['unixday'])) {
            $attrs['uday'] = new UnixDay($attrs['unixday']);
            unset($attrs['unixday']);
        } else {
            $attrs['uday'] = UnixDay::from_ymd($attrs['year'], $attrs['month'], $attrs['day']);
        }

        // fix timespan
        if((!isset($attrs['timespan'])) || $attrs['timespan'] < 1) {
            $attrs['timespan'] = 1;
        } elseif ($attrs['timespan'] == 30) {
            $attrs['timespan'] = UnixDay::month_length($attrs['uday']->month());
            
        }

        // get unixdayto (needs timespan)
        if(isset($attrs['unixdayto'])) {
            $attrs['udayto'] = new UnixDay($attrs['unixdayto']);
            unset($attrs['unixdayto']);
        } else {
            $attrs['udayto'] = new UnixDay($attrs['uday']->ud() + $attrs['timespan']);
        }

        return (new Item($attrs));
    }

    /** Sets flags that depend on the current time */
    public function set_nowday($nowday) { // expects unixtime/60/60/24
        if($this->uday->ud() > $nowday) {
            $this->infuture = true;
        } else {
            if($this->uday->ud() <= $nowday && $this->udayto->ud() > $nowday) {
                $this->activelong = true;
            }
        }
        return $this;
    }
    
    public function get_name() {
        return $this->name;
    }

    public function set_name($x) {
        $this->name = $x;
        return $this;
    }

    public function get_unixday() {
        return $this->uday->ud();
    }

    public function get_unixdayto() {
        return $this->udayto->ud();
    }

    public function get_timespan() {
        return $this->timespan;
    }

    public function get_ctype() {
        return $this->ctype;
    }

    public function set_ctype($x) {
        if((!isset($x)) || $x === false || $x == '') {
            $x = 'X';
        }
        $this->ctype = $x;
        return $this;
    }

    public function get_business() {
        return $this->business;
    }

    public function get_clong() {
        return $this->clong;
    }

    public function get_accountto() {
        return $this->accountto;
    }

    public function get_clong_as_num() {
        $v = $this->clong - 0;
        if($v == 0) {
            throw new Exception('Error retrieving long value on ' . $this->uday->simple_string());
        }
        return $v;
    }

    public function get_info() {
        return $this->uday->simple_string() . ' "' . $this->name . '" ' . $this->value 
	 . ' <' . $this->ctype . '> ' . $this->accountto . $this->accountfrom;
    }

    public function get_checked() {
        return $this->checked;
    }

    public function get_value() {
        // TODO rate
        return $this->value;
    }

    public function set_value($x) {
        $this->value = $x;
        return $this;
    }

    public function realvalue() {
        // TODO rate
        if($this->accountfrom) {
            return 0;
        }
        return $this->value;
    }
    
    public static function delete_item($DB, $id) {
        $DB->exec_assert_change('DELETE FROM costs WHERE id = ?', array($id), 1);
    }

    public function delete($DB) {
        self::delete_item($DB, $this->id);
    }

    /** Stores a NEW record in the database */
    public function store($DB) {
        $placeholders = array();
        $values = array();
        $names = array(
            'year',
            'month',
            'day',
            'name',
            'value',
            'timespan',
            'accountto',
            'accountfrom',
            'checked',
            'istransfer',
            'ctype',
            'business',
            'clong',
            'unixday',
            'unixdayto'
        );
        foreach($names as $n) {
            switch($n) {
                case 'value':
                    $v = $this->$n;
                    $v *= 100;
                break;
                case 'ctype':
                    $v = $this->$n;
                    $v = ($v == 'X' ? '' : $v);
                break;
                case 'year':
                    $v = $this->uday->year();
                break;
                case 'month':
                    $v = $this->uday->month();
                break;
                case 'day':
                    $v = $this->uday->day();
                break;
                case 'unixday':
                    $v = $this->uday->ud();
                break;
                case 'unixdayto':
                    $v = $this->udayto->ud();
                break;
                default:
                    $v = $this->$n;
                break;
            }

            $placeholders[] = '?';
            $values[] = $v;
        }

        $DB->exec_assert_change(
            'insert into costs (' . implode(',', $names) . ') values (' . implode(',', $placeholders) . ')',
            $values,
            1
        );
    }
    
    /** Toggle the checked status of the item */
    public function toggle_item_checked() {
        $this->checked = ($this->checked ? 0 : 2); // {CHECKEDVALUE}
        return $this;
    }


    /** Toggle the checked status of the item */
    public function toggle_item_business() {
        $this->business = ($this->business ? 0 : 1);
        return $this;
    }


    /** Toggle the checked status of any item */
    // TODO Rewrite into object method to call before store()
    public static function toggle_checked($DB, $id) {
        $cur = $DB->querysinglerow('select checked from costs where id = ?', array($id));
        $cur = $cur['checked'];
        $DB->exec_assert_change('update costs set checked = ? where id = ?', array(($cur > 0 ? 0 : 2), $id), 1); // {CHECKEDVALUE}
    }

    /** Toggle the business status of any item */
    // TODO Rewrite into object method to call before store()
    public static function toggle_business($DB, $id) {
        $cur = $DB->querysinglerow('select business from costs where id = ?', array($id));
        $cur = $cur['business'];
        $DB->exec_assert_change('update costs set business = ? where id = ?', array(($cur > 0 ? 0 : 1), $id), 1);
    }

}

?>
