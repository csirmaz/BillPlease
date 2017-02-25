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

/** Class collecting the main logic */
class Control {


    public static function process_action($REQ, $DB) {

        switch($REQ->get_action()){

            /*
            Toggle the checked status of an item. AJAX request.
            action = check
            id = <item id>
            */
            case 'check':
                try {
                    Item::toggle_checked($DB, $REQ->get_int('id'));
                } catch(Exception $e){
                    View::json_resp(false, $e);
                    exit(0);
                }
                View::json_resp(true);
                exit(0);

            /*
            Create or modify an item. AJAX request.
            action = add
            Form submission -- see the item_as_form template
            */
            case 'add':
                try{
                    Item::parse_html_form($REQ, $DB);
                } catch(Exception $e){
                    View::json_resp(false, $e);
                    exit(0);
                }
                View::json_resp(true);
                exit(0);

            /*
            Delete an item
            action = delete
            id = <item id>
            */
            case 'delete':
                try{
                    Item::delete_on_id($REQ->get_int('id'), $DB);
                } catch(Exception $e){
                    View::json_resp(false, $e);
                    exit(0); 
                }
                View::json_resp(true);
                exit(0);
        }
    }

    
    public static function process_dynamic_content($REQ){
    
        $APP = Application::get();
        $DB = $APP->db();
    
        switch($REQ->get_view()){

            /*
            Edit an item. Modal loaded via AJAX
            
            Start editing a new item today
            view = edit
            mode = new
            
            Start editing a new item on the given day
            view = edit
            mode = new-on
            ud = <unixday>

            Modify an existing item
            view = edit
            mode = modify
            id = <item id>
            */
            case 'edit':
                switch ($REQ->get_string('mode')){
                case 'new':
                    print View::page_edit(
                        $DB, 
                        'Create', 
                        Item::new_empty_on_uday($APP->nowday()->ud())->to_html_form($DB)
                    );
                    exit(0);
                case 'new-on':
                    print View::page_edit(
                        $DB, 
                        'Create', 
                        Item::new_empty_on_uday($REQ->get_int('ud'))->to_html_form($DB)
                    );
                    exit(0);
                case 'modify':
                    print View::page_edit(
                        $DB, 
                        'Modify', 
                        Item::from_db($DB->querysinglerow('select * from costs where id=?',array($REQ->get_int('id'))))->to_html_form($DB)
                    );
                    exit(0);
                }
                break;
                // TODO Capture exceptions

            /*
            List of spread items affecting a given day. Modal loaded via AJAX.
            view = longitems
            ud = <unixday>
            */
            case 'longitems':
                try {
                    print Day::from_unixday($DB, $REQ->get_int('ud'))->get_long_info($APP->nowday());
                } catch (Exception $e){
                    print htmlspecialchars('Error: ' . $e->getMessage());
                }
                exit(0);

            /*
            Shortcuts one can use while editing. Content loaded via AJAX.
            view = shortcuts
            */
            case 'shortcuts':
                try {
                    print Html::shortcuts($DB)['legend'];
                } catch (Exception $e){
                    print htmlspecialchars('Error: ' . $e->getMessage());
                }
                exit(0);

        }
    }
    
}

?>