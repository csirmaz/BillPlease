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

class Item extends ItemData {

   /** Parses a HTML form submission and deletes an item */
   public static function delete_on_html_form($RequestObj, $DB) {
      $DB->exec_assert_change(
         'DELETE FROM costs WHERE unixday = ? AND dayid = ?',
         array($RequestObj->get_int('oldiuday'), $RequestObj->get_int('oldidayid')),
         1
      );

   }

   /** Parses a HTML form submission and updates the DB by adding or editing an item */
   public static function parse_html_form($RequestObj, $DB) {

      $oldunixday = $RequestObj->get_int('oldiuday');
      $dayid = $RequestObj->get_int('oldidayid');

      $newunixday = $DB->date2unixday(
         $RequestObj->get_year('year'),
         $RequestObj->get_month('month'),
         $RequestObj->get_day('day')
      );

      // Always delete modified entry from DB
      if ($dayid != - 1) {
         $DB->exec_assert_change(
            'DELETE FROM costs WHERE unixday = ? AND dayid = ?',
            array($oldunixday, $dayid),
            1
         );
      }

      if ($dayid != - 1 && $newunixday != $oldunixday) {
         // if edit, but date has been edited, we need a new dayid
         $dayid = - 1;
      }

      self::from_raw(
         array(
            'unixday' => $newunixday,
            'dayid' => $dayid,
            'name' => $RequestObj->get_string('name'),
            'value' => $RequestObj->get_money('value'),
            'timespan' => $RequestObj->get_int('fortime'),
            'accounts' => $RequestObj->get_value('account'),
            'checked' => $RequestObj->get_value('checked'),
            'ctype' => $RequestObj->get_value('type'),
            'business' => $RequestObj->get_checkbox('business'),
            'clong' => $RequestObj->get_value('long')
         ),
         $DB
      )->store($DB);
   }

   /** Generate HTML form fields based on the item */
   public function to_html_form($DB) {

      // TODO Move selector constructors to single class
      return Application::get()->solder()->fuse(
         'item_as_form',
         array( //
            'dayid' => $this->dayid,
            'unixday' => $this->uday->ud(),
            'year' => $this->uday->year(),
            'month' => $this->uday->month(),
            'day' => $this->uday->day(),
            'name' => $this->name,
            'value' => $this->value,
            '$accountselector' => Html::accountselector($DB, $this->accountto . $this->accountfrom),
            '$fortimeselector' => Texts::timespanselector($this->timespan),
            '$typeselector' => Html::typeselector($DB, $this->ctype),
            '$checkedselector' => Html::checkedselector($this->checked),
            '$cbusiness' => ($this->business == 1 ? 'checked="checked"' : ''),
            'clong' => $this->clong
         )
      );

   }

   /** Returns a CSS class identifying the current item */
   public function item_id_css() {
      return self::static_item_id_css($this->id);
   }

   /** Returns a CSS class identifying the current item */
   public static function static_item_id_css($id){
      return 'bpid_' . $id;
   }

   /** Generate a HTML view of the item */
   public function to_html(
      $adddate = false /*< bool */
   ) {
      $style_row = $this->item_id_css();

      if ($this->infuture) {

         $style_row .= ' bpfuture';

      } else {

         if ($this->business) {
            $style_row .= ' bpbusiness';
         }

         if (strstr($this->name, '!?') !== FALSE) {
            $style_row .= ' bpuncertain';
         }

      }

      $style_acc = 'bpchkd bpchkd_' . ($this->checked + 0); // checked
      $style_acc .= ' bpacc_' . strtolower($this->accountto); // account identifier; see Html::css_accounts
      $idx = $this->uday->year() . ',' . $this->uday->month() . ',' . $this->uday->day() . ',' . $this->dayid;

      return Application::get()->solder()->fuse(
         'item',
         array(
            '$class_tr' => $style_row,
            'date' => ($adddate ? date('D d M Y', $this->uday->ud() * 24 * 60 * 60) : ''),
            '$idx' => $idx,
            'name' => $this->name,
            'comment' => mb_substr($this->clong, 0, 16),
            '$value' => printsum($this->value, false, false),
            'timespan' => Texts::timespan($this->timespan . ''),
            '$class_acc' => $style_acc,
            'acc' => $this->accountto . $this->accountfrom,
            '$ctype' => strtolower($this->ctype),
            'type' => $this->ctype
         )
      );
   }

   public function to_html_line() {
      $perday = $this->value / $this->timespan;
      return '<tr><td>' . implode(
         '</td><td>',
         array(
            $this->uday->year() . '-' . $this->uday->month() . '-' . $this->uday->day(),
            $this->name,
            printsum($this->value),
            printsum($perday * 365 / 12) . '/m ',
            printsum($perday) . '/d'
         )
      ) . '</td></tr>';
   }

}
