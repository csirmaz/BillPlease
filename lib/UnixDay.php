<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2013 by Elod Csirmaz <http://www.github.com/csirmaz>

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

/** This class represents a day as a datetime that can be manipulated using different protocols */

class UnixDay {
   private $myud;
   private $myyear;
   private $mymonth; /* 1..12 */
   private $myday;

   public function __construct($ud) {
      $this->myud = $ud;
      $this->unixday2date();
   }

   /** Alternative constructor */
   public static function from_ymd($year, $month, $day) {
      $me = new self(0);
      $me->myyear = $year;
      $me->mymonth = $month;
      $me->myday = $day;
      $me->date2unixday();
      return $me;
   }

   /** Alternative constructor: from UNIX timestamp */
   public static function from_ut($ut) {
      return new self(floor($ut / 60 / 60 / 24));
   }

   public function ud() {
      return $this->myud;
   }
   public function year() {
      return $this->myyear;
   }
   public function month() {
      return $this->mymonth;
   }
   public function day() {
      return $this->myday;
   }

   public function set_day($day) {
      $this->myday = $day;
      $this->date2unixday();
   }

   public function add($days) {
      $this->myday += $days;
      $this->date2unixday();
   }

   public function sub($days) {
      $this->myday -= $days;
      $this->date2unixday();
   }

   public function add_month() {
      $this->mymonth++;
      if ($this->mymonth > 12) {
         $this->myyear++;
         $this->mymonth -= 12;
      }
      $this->date2unixday();
   }

   public function sub_month() {
      $this->mymonth--;
      if ($this->mymonth < 1) {
         $this->myyear--;
         $this->mymonth += 12;
      }
      $this->date2unixday();
   }

   public function eq($unixday) {
      return ($this->myud = $unixday->ud());
   }

   public function lt($unixday) {
      return ($this->myud < $unixday->ud());
   }

   public function gt($unixday) {
      return ($this->myud > $unixday->ud());
   }

   public function simple_string() {
      $ut = $this->myud * 24 * 60 * 60;
      return date('Y-n-j', $ut);
   }

   public function js_date() {
      return 'new Date(' . $this->myyear . ',' . ($this->mymonth - 1) . ',' . $this->myday . ')';
   }

   private function date2unixday() {
      $this->myud = round(mktime(0, 0, 0, $this->mymonth, $this->myday, $this->myyear) / 60 / 60 / 24);
   }

   private function unixday2date() {
      $ut = $this->myud * 24 * 60 * 60;
      $this->myyear = date('Y', $ut);
      $this->mymonth = date('n', $ut);
      $this->myday = date('j', $ut);
   }

}

?>