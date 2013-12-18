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

/** Defines additional DB-releated convenience methods */
class CostsDB extends SQLBrite {

   /** Overrides the error function of SQLBrite */
   public function error($msg) { // TODO Display error message on exception instead
      print ('<html><body>' . $msg . '</body></html>');
      exit(1);
   }

   /** Returns a SQLite3 expression to get a valid date string from the data */
   public function c2date() {
      return "year||'-'||(case when month<10 then '0'||month else month end)||'-'||(case when day<10 then '0'||day else day end)";
   }

   /** Returns a SQLite3 expression to get a valid UNIX timestamp from the data */
   public function c2unixtime() {
      return "strftime('%s'," . $this->c2date() . ")";
   }

   /** Returns the sum of entries for a given account up to the given day */
   public function accountsum($acc, $nowday) {
      // DB index: costs_accto_ud_|_v
      $to = $this->querysingle('select sum(value) from costs where accountto = ? and unixday <= ?', array($acc, $nowday));
      // DB index: costs_accfr_ud_|_v
      $from = $this->querysingle('select sum(value) from costs where accountfrom = ? and unixday <= ?', array($acc, $nowday));
      return ($to - $from) / 100;
   }

   /** Returns the sum of checked entries for the given account */
   public function accountsum_checked($acc) {
      // Index: costs_accto_c
      $to = $this->querysingle('select sum(value) from costs where accountto = ? and checked>0', array($acc));
      // Index: costs_accfrom_c
      $from = $this->querysingle('select sum(value) from costs where accountfrom = ? and checked>0', array($acc));
      return ($to - $from) / 100;
   }

   /** Returns an ID that is still free on the given day */
   public function get_free_dayid($unixday) {
      $xno = $this->querysingle('select max(dayid) as mdayid from costs where unixday = ?', array($unixday));
      if (is_null($xno)) {
         $xno = 0;
      } else {
         $xno++;
      }
      return $xno;
   }

   /** Performs consistency checks on the DB */
   public function internal_fsck() {
      if ($this->querysingle('select count(*) from costs where unixday != ' . $this->c2unixtime() . '/60/60/24') != 0) {
         $this->error('internal_fsck(unixday value) failed');
      }
      if ($this->querysingle('select count(*) from costs where unixdayto != unixday+timespan') != 0) {
         $this->error('internal_fsck(unixdayto value) failed');
      }
      // The following may not be necessary
      if ($this->querysingle('select count(*) from costs where timespan<1') != 0) {
         $this->error('internal_fsck(timespan less than 1) failed');
      }
      if ($this->querysingle('select count(*) from costs where unixday is null') != 0) {
         $this->error('internal_fsck(unixday is null) failed');
      }
      if ($this->querysingle('select count(*) from costs where year < 2000') != 0) {
         $this->error('internal_fsck(year value) failed');
      }
      if ($this->querysingle('select count(*) from costs where checked = "" or checked is null') != 0) {
         $this->error('internal_fsck(checked not empty) failed');
      }
   }

   /** Returns the number of days passed since the UNIX epoch */
   public static function date2unixday($year, $month, $day) {
      // TODO Use a UnixDay object
      return round(mktime(0, 0, 0, $month, $day, $year) / 60 / 60 / 24);
   }

}

?>