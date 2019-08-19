<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2016,2017,2019 by Elod Csirmaz <http://www.github.com/csirmaz>

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

/** This class implements different views */

class View {

    public static function json_resp($success, $exception = NULL) {
        if($success) {
            print '{"success":true}';
        } else {
            print Application::get()->solder()->fuse('json_error', array('msg' => $e->getMessage()));
        }
    }

    public static function page_list($DB, $nowday, $list) {
        $APP = Application::get();
        $SLD = $APP->solder();
        $shortcuts = Html::shortcuts($DB);

        return $SLD->fuse(
            'page_list',
            array(
                '$head_common' => self::head_common(),
                '$head_list' => $SLD->fuse(
                    'head_list',
                    array(
                        '$cssaccounts' => Html::css_accounts(),
                        '$csstypes' => Html::css_types(),
                        '$firstcheckedjs' => $APP->first_checked()->getjs()
                    )
                ),
                '$summary' => Summary::render($nowday),
                '$list' => $list,
                '$firstcheckedhtml' => $APP->first_checked()->gethtml(),
                '$shortcutsjs' => $shortcuts['js']
            )
        );
    }

    public static function page_edit($DB, $actionButtonLabel, $itemform) {
        $SLD = Application::get()->solder();

        return $SLD->fuse(
            'page_edit',
            array('xbutton' => $actionButtonLabel, '$itemform' => $itemform)
        );
    }

    private static function head_common() {
        $PATH = Application::get()->path();
        return Application::get()->solder()->fuse(
            'head_common',
            array(
                'maincss' => Html::filewithstamp('main.css', $PATH),
                'mainjs' => Html::filewithstamp('BillPlease/main.js', $PATH)
            )
        );
    }

    /* $fromday==0 for full chart. Otherwise it is the offset */
    public static function chart_timeline($APP, $fromday, $useadjust) {
        $SLD = $APP->solder();
        $DB = $APP->db();
        $sum = 0;
        $timedsum = 0;
        $out = '';
        $nowday = $APP->nowday()->ud();
        
        if($fromday == 0) {
            $fromday = $DB->querysingle('select min(unixday) from costs') + 0;
        }
        else {
            $fromday = $nowday - $fromday;
            $sum = Summary::get_all_sum($DB, $fromday-1);
            $timedsum = $sum - Summary::get_tail_sum($DB, $fromday-1);
        }

        for($d = $fromday; $d <= $nowday; $d++) {
            $Day = Day::from_unixday($DB, $d);
            $sum += $Day->get_sum();
            $timedsum += $Day->get_timedsum();
            
            $adjustment = 0;
            if($useadjust && function_exists('\\BillPleaseExternal\\day_sum_adjust')) {
                $adjustment = \BillPleaseExternal\day_sum_adjust($Day);
            }

            # annotation
            $antext = $APP->first_checked()->forday($d);
            if($antext === False) {
                $antext = 'undefined';
                $antitle = 'undefined';
            }
            else {
                $antitle = '"Unchecked"';
                $antext = '"' . $antext . '"';
            }
            
            //[new Date(2008, 1 ,1), 30000, undefined, undefined, 40645, undefined, undefined],
            if($out) {
                $out .= ",\n";
            }
            $out .= $SLD->fuse(
                'chart_timeline_day', array(
                    '$date' => $Day->get_js_date(), 
                    '$timedsum' => - ($timedsum + $adjustment),
                    '$sum' => - ($sum + $adjustment),
                    '$antitle' => $antitle,
                    '$antext' => $antext
                )
            );
        }

        print $SLD->fuse('chart_timeline_page', array('$data' => $out, 'title' => Texts::systitle()));

    }

    public static function chart_bar($APP) {
        $DB = $APP->db();
        $nowday = $APP->nowday()->ud();
        $step = 30;

        $data = self::_barchart(
            $DB,
            ($DB->querysingle('select min(unixday) from costs') + 0),
            $step,
            $nowday,
            'graph'
        );
        print $APP->solder()->fuse(
            'chart_bar_page',
            array(
                'title' => Texts::systitle(),
                '$bycategory' => $data[0],
                '$incomeexpense' => $data[1],
                '$colors' => implode(',', $data[2]),
                'intv' => ($step == 30 ? 'monthly' : $step . '-day')
            )
        );
    }
    
    // CSV output from business entries
    public static function chart_business_csv() {
        $out = \BillPleaseExternal\chart_business_csv();
         
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=business.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        print $out;
    }

    // CSV output from monthly outgoings by type
    public static function chart_csv($APP) {
        $DB = $APP->db();
        $nowday = $APP->nowday()->ud();
        $step = 30;        

        $data = self::_barchart(
            $DB,
            ($DB->querysingle('select min(unixday) from costs') + 0),
            $step,
            $nowday,
            'csv'
        );

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=month-by-type.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        print $data[0];
        # print $APP->solder()->fuse('chart_csv_page', array('title' => Texts::systitle(), '$data' => $data[0]));
    }

    private static function _barchart(
        $DB,
        $dayfrom, // not object
        $step,
        $dayto,
        $format // "graph" or "csv"
    ) {

        $TYP = new CType($DB);
        $colors = array();
        $databytype = ''; // by-type graph (inlcudes income-expense if $format!='graph')
        $databyie = ''; // income-expense graph (used only if $format=='graph')
        // Data header
        $databyie = ($format == 'graph' ? "['Date','Income','Expense']" : '');
        $databytype = ($format == 'graph' ? "['Date'" : '"Date","Income","Expense"');
        $TYP->get_type_callback(
            function ($label, $typedata) use (&$colors, &$databytype) {
                $databytype .= ',"' . $typedata['name'] . '"';
                $colors[] = "'" . $typedata['chartcolor'] . "'";
            }
        );
        $databytype .= ($format == 'graph' ? "]" : '');

        if($step != 30) {
            $dayfrom = $dayto - floor(($dayto - $dayfrom) / $step) * $step;
        }
        $dayfrom = new UnixDay($dayfrom);
        $dayto = new UnixDay($dayto);
        if($step == 30) { // Simulate pcm steps
            $dayfrom->set_day($dayto->day());
        }

        while($dayfrom->lt($dayto)) {

            $dt = $dayfrom->simple_string();
            $dt = ($format == 'graph' ? ",\n['" . $dt . "'" : "\n\"" . $dt . '"');
            if($format == 'graph') {
                $databyie .= $dt;
            }
            $databytype .= $dt;

            // Increase date
            $udfrom = $dayfrom->ud() + 1;
            if($step == 30) { // Simulate pcm steps
                $dayfrom->add_month();
            } else {
                $dayfrom->add($step);
            }
            $udto = $dayfrom->ud();

            $TYP->sum(
                $udfrom,
                $udto,
                true,
                5000 /* TODO MAX VALUE */
            );
            if($format == 'graph') {
                $databyie .= ',' . implode(',', $TYP->get_gensums_corrected()) . ']';
            } else {
                $databytype .= ',' . implode(',', $TYP->get_gensums_corrected());
            }

            $TYP->get_sum_callback(
                function ($label, $typedata, $sum) use (&$databytype) {
                    $databytype .= "," . $sum;
                }
            );

            if($format == 'graph') {
                $databytype .= "]";
            }
        }

        return array($databytype, $databyie, $colors);
    }

}

?>
