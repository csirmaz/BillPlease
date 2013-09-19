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

class Item extends ItemData {

   /** Parses a HTML form submission and deletes an item */
   public static function delete_on_html_form($RequestObj, $DB) {
      $DB->exec_assert_change( //
      'DELETE FROM costs WHERE unixday = ? AND dayid = ?', //
      array($RequestObj->get_int('oldiuday'), $RequestObj->get_int('oldidayid')), //
      1
      //
      ); //

   }

   /** Parses a HTML form submission and updates the DB by adding or editing an item */
   public static function parse_html_form($RequestObj, $DB) {

      $oldunixday = $RequestObj->get_int('oldiuday');
      $dayid = $RequestObj->get_int('oldidayid');

      $newunixday = $DB->date2unixday( //
      $RequestObj->get_year('year'), //
      $RequestObj->get_month('month'), //
      $RequestObj->get_day('day') //
      );

      // Always delete modified entry from DB
      if ($dayid != - 1) {
         $DB->exec_assert_change( //
         'DELETE FROM costs WHERE unixday = ? AND dayid = ?', //
         array($oldunixday, $dayid), //
         1);
      }

      if ($dayid != - 1 && $newunixday != $oldunixday) {
         // if edit, but date has been edited, we need a new dayid
         $dayid = - 1;
      }

      self::from_raw(array( //
      'unixday' => $newunixday, //
      'dayid' => $dayid, //
      'name' => $RequestObj->get_string('name'), //
      'value' => $RequestObj->get_money('value'), //
      'timespan' => $RequestObj->get_int('fortime'), //
      'accounts' => $RequestObj->get_value('account'), //
      'checked' => $RequestObj->get_value('checked'), //
      'ctype' => $RequestObj->get_value('type'), //
      'business' => $RequestObj->get_checkbox('business'), //
      'clong' => $RequestObj->get_value('long') //

      ), $DB)->store($DB);
   }

   /** Generate HTML form fields based on the item */
   public function to_html_form($DB) {

      // TODO Move selector constructors to single class
      $fortimeselector = Texts::timespanselector($this->timespan);
      $typeselector = Html::typeselector($DB, $this->ctype);
      $checkedselector = Html::checkedselector($this->checked);
      $accountselector = Html::accountselector($DB, $this->accountto . $this->accountfrom);
      $cbusiness = $this->business == 1 ? 'checked' : '';

      return (<<<THEEND
<input type="hidden" name="oldidayid" value="{$this->dayid}">
<input type="hidden" name="oldiuday" value="{$this->unixday}">

<input type="text" name="year" size=2 value="{$this->year}" title="Year"> /
<input type="text" name="month" size=2 value="{$this->month}" title="Month"> /
<input type="text" name="day" size=2 value="{$this->day}" autocomplete="off" title="Day"> :
<input type="text" name="name" size=30 value="{$this->name}" spellcheck="false" autocomplete="off" onblur="namecomplete()" title="Description (Company name)"> =
<input type="text" name="value" size=4 value="{$this->value}" autocomplete="off" title="Price in GBP"> GBP
<br/>
Account: <select name="account" title="Which account?">{$accountselector}</select>
<br/>
Recurrence: <select name="fortime" title="Recurrence">$fortimeselector</select>
<br/>
Category: <select name="type" title="Type or category">$typeselector</select>
<br/>
Checked: <select name="checked" title="The item has been checked against the bank statement">$checkedselector</select>
<br/><br/>
Business? <input type="checkbox" name="business" $cbusiness title="Business entry?"><br>
Comment: <textarea rows="5" cols="40" name="long" wrap="off" title="Comment">{$this->clong}</textarea>
THEEND) ;
   }

   /** Generate a HTML view of the item */
   public function to_html( //
   $FirstChecked, /*< an object or false not to color the first entry */
   $qfocuscheck, // array or false to skip or true to add ID used to move focus
   $adddate = false /*< bool */) {
      $style_name = '';
      $style_val = '';
      $style_row = '';
      $style_acc = '';
      $style_check = 'chkd chkd' . ($this->checked + 0);
      $check_id = '';
      $comment = '';

      if ($this->infuture) {

         $style_row.= ' fr';

      } else {

         if ($this->accountfrom) {
            $style_val.= ' atm';
         }
         if ($this->business) {
            $style_row.= ' bs';
         }
         if ($FirstChecked) {
            if ($FirstChecked->isfirstunchecked($this)) {
               $style_acc = 'unc';
            }
            if ($FirstChecked->isfirstgreenblue($this)) {
               $style_acc = 'fgr';
            }
         }

         if (strstr($this->name, '[clearing]') !== FALSE) {
            $style_name.= ' cl';
         }
         if (strstr($this->name, '!?') !== FALSE) {
            $style_name.= ' auto';
         }

      }

      $style_row.= ' acc_' . strtolower($this->accountto);

      $style_name.= ' nam';

      // Retain focus
      if (($qfocuscheck !== false //
       && $qfocuscheck[0] == $this->year //
       && $qfocuscheck[1] == $this->month //
       && $qfocuscheck[2] == $this->day //
       && $qfocuscheck[3] == $this->dayid) || $qfocuscheck === true
      //
      ) {
         $check_id = 'id="focusedcheck"';
      }

      // prepare comment
      if ($this->clong) {
         $comment = str_replace(array(
            '"',
            "\n",
            "\r"
         ) , array(
            "'",
            ' ',
            ' '
         ) , $this->clong);
         $comment = ' <span class="comment" title="' . $comment . '">' . substr($comment, 0, 16) . '</span>';
      }

      $idx = $this->year . ',' . $this->month . ',' . $this->day . ',' . $this->dayid;

      return ('<tr class="' . $style_row . '">'
      // date
       . ($adddate ? '<td>' . date('D d M Y', $this->unixday * 24 * 60 * 60) . '</td>' : '')
      // name
       . '<td title="Click to edit" onclick="edi(' . $idx . ')" class="' . $style_name . '">' . $this->name
      // long (comment)
       . $comment . '</td>'
      // value
       . '<td align=right class="' . $style_val . '"><tt>' . printsum($this->value, false, false) . '</tt></td>'
      // for
       . '<td align=left>' . ($this->activelong ? '%' : '') . Texts::timespan($this->timespan . '') . '</td>'
      // account / checking
       . '<td class="ch ' . $style_acc . '"><input ' . $check_id . ' class="' . $style_check . '" type="submit" value="' . $this->accountto . $this->accountfrom . '" onclick="chkthis(' . $idx . ')"></td>'
      // type
       . '<td onclick="edt(' . $idx . ')" class="typ' . ($this->ctype != 'X' ? ' t_' . strtolower($this->ctype) . '">' . $this->ctype . '</td>' : '"></td>') . "</tr>\n");
   }

}
