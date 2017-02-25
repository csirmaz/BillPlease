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
    public static function delete_on_id($id, $DB) {
        $DB->exec_assert_change('DELETE FROM costs WHERE id = ?', array($id), 1);

    }

    /** Parses a HTML form submission and updates the DB by adding or editing an item */
    public static function parse_html_form($RequestObj, $DB) {

        $oldunixday = $RequestObj->get_int('oldiuday');

        $newunixday = $DB->date2unixday(
            $RequestObj->get_year('year'),
            $RequestObj->get_month('month'),
            $RequestObj->get_day('day')
        );

        // Always delete modified entry from DB
        $oldid = $RequestObj->get_int('oldid');
        if($oldid != - 1) {
	    ItemData::delete_item($DB, $oldid);
        }

        self::from_raw(
            array(
                'unixday' => $newunixday,
                'name' => $RequestObj->get_string('name'),
                'value' => $RequestObj->get_money('value'),
                'timespan' => $RequestObj->get_int('fortime'),
                'accounts' => $RequestObj->get_value('account'),
                'checked' => $RequestObj->get_checkbox('checked') ? 2 : 0,
                'istransfer' => $RequestObj->get_checkbox('istransfer'),
                'ctype' => $RequestObj->get_value('type'),
                'business' => $RequestObj->get_checkbox('business'),
                'clong' => $RequestObj->get_value('long')
            )
        )->store($DB);
    }

    /** Generate HTML form fields based on the item */
    public function to_html_form($DB) {

        // TODO Move selector constructors to single class
        return Application::get()->solder()->fuse(
            'item_as_form',
            array( //
                'id' => $this->id,
                'unixday' => $this->uday->ud(),
                'year' => $this->uday->year(),
                'month' => $this->uday->month(),
                'day' => $this->uday->day(),
                'name' => $this->name,
                'value' => $this->value,
                '$accountselector' => Html::accountselector($DB, $this->accountto . $this->accountfrom),
                '$fortimeselector' => Texts::timespanselector($this->timespan),
                '$typeselector' => Html::typeselector($DB, $this->ctype),
                '$cchecked' => ($this->checked == 2 ? 'checked="checked"' : ''),
                '$cistransfer' => ($this->istransfer == 1 ? 'checked="checked"' : ''),
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
    public static function static_item_id_css($id) {
        return 'bpid_' . $id;
    }

    /** Generate a HTML view of the item */
    public function to_html(
        $adddate = false /*< bool */
    ) {
        $style_row = $this->item_id_css();

        if($this->infuture) {

            $style_row .= ' bpfuture';

        } else {

            if($this->business) {
                $style_row .= ' bpbusiness';
            }
            
            if($this->istransfer) {
                $style_row .= ' bptransfer';
            }

        }

        $style_acc = 'bpchkd bpchkd_' . ($this->checked + 0); // checked
        $style_acc .= ' bpacc_' . strtolower($this->accountto); // account identifier; see Html::css_accounts
        return Application::get()->solder()->fuse(
            'item',
            array(
                'id' => $this->id,
                '$class_tr' => $style_row,
                'date' => ($adddate ? date('D d M Y', $this->uday->ud() * 24 * 60 * 60) : ''),
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
    
    public function to_csv_line($CTypeObj) {
      return Application::get()->solder()->fuse(
         'item_csv_line',
         array(
            'ctypename' => $CTypeObj->get_name_from_label($this->ctype),
            'date' => date('Y-M-d', $this->uday->ud() * 24 * 60 * 60),
            'name' => $this->name,
            'value' => $this->value,
            'account' => $this->accountto . $this->accountfrom
         )
      ) . "\n";
    }

    public function to_html_line($ud_now) {
        $perday = $this->value / $this->timespan;
        return '<tr><td>' . implode(
            '</td><td>',
            array(
                $this->uday->simple_string(),
                $this->name,
                printsum($this->value),
                printsum($perday * 365 / 12) . '/m ',
                printsum($perday) . '/d',
                ($this->uday->ud() + $this->timespan - $ud_now->ud()) // Remaining days
                
            )
        ) . '</td></tr>';
    }

}
