<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2016-2025 by Elod Csirmaz <http://www.github.com/csirmaz>

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

    public static function page_list($DB, $nowday, $list, $searchterms) {
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
                '$shortcutsjs' => $shortcuts['js'],
                'searchterms' => $searchterms
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
    
    public static function recently_modified($APP) {
        $SLD = $APP->solder();
        $DB = $APP->db();
        $nowday = $APP->nowday()->ud();
        $List = Html::table_header_row('Recently modified items (from least to most recent)',false,false,false,false,true);
        $DB->query_callback('select * from (select * from costs order by id desc limit 200) order by id asc', false,
            function($r)use($nowday, &$List){
                $item = Item::from_db($r);
                $item->set_nowday($nowday);
                $List .= $item->to_html(true /*add date*/);
            }
        );
        $List .= Html::table_footer_row('^ most recently modified');
        print View::page_list($DB, $nowday, $List, '' /* $searchterm */);
    }

    /* $fromday==0 for full chart. Otherwise it is the offset */
    public static function chart_timeline($APP, $fromday) {
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
            
            $sum_out = round(-$sum, 2);
            $timedsum_out = round(-$timedsum, 2);
            
            # annotation (timed)
            $antext = $APP->first_checked()->forday($d);
            if($antext === '') {
                $antext = 'undefined';
                $antitle = 'undefined';
            }
            else {
                $antitle = '"' . $antext . '"';
                $antext = '"' . $timedsum_out . '"';
            }
            
            # annotation (not timed)
            $an2title = 'undefined';
            $an2text = 'undefined';
            if($d >= $nowday - 70 && (($nowday - $d) % 30) == 0) {
                $an2title = '"Sum"';
                $an2text = '"' . $sum_out . '"';
            }
            
            //[new Date(2008, 1 ,1), 30000, undefined, undefined, 40645, undefined, undefined],
            if($out) {
                $out .= ",\n";
            }
            $out .= $SLD->fuse(
                'chart_timeline_day', array(
                    '$date' => $Day->get_js_date(), 
                    '$timedsum' => $timedsum_out,
                    '$sum' => $sum_out,
                    '$antitle' => $antitle,
                    '$antext' => $antext,
                    '$an2title' => $an2title,
                    '$an2text' => $an2text
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
            $APP,
            '24months', // dayfrom ($DB->querysingle('select min(unixday) from costs') + 0),
            $step,
            $nowday,
            'graph',
            $APP->debug()
        );
        
        $extracontent = '';
        if(function_exists('\BillPleaseExternal\chart_bar_hook')) {
            $raw_month_data = self::_barchart($APP, '12months', 30, $nowday, 'data');
            $extracontent = \BillPleaseExternal\chart_bar_hook($DB, $nowday, $raw_month_data);
        }
        
        print $APP->solder()->fuse(
            'chart_bar_page',
            array(
                'title' => Texts::systitle(),
                '$bycategory' => $data[0],
                '$incomeexpense' => $data[1],
                '$colors' => implode(',', $data[2]),
                'intv' => ($step == 30 ? 'monthly' : $step . '-day'),
                '$extracontent' => $extracontent
            )
        );
    }
    
    // CSV output from monthly outgoings by type
    public static function chart_csv($APP) {
        $DB = $APP->db();
        $nowday = $APP->nowday()->ud();
        $step = 30;        

        $data = self::_barchart(
            $APP,
            ($DB->querysingle('select min(unixday) from costs') + 0), // dayfrom
            $step,
            $nowday,
            'csv',
            FALSE
        );

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=month-by-type.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        print $data[0];
        # print $APP->solder()->fuse('chart_csv_page', array('title' => Texts::systitle(), '$data' => $data[0]));
    }
    
    private static function _chart_esc($s, $format) {
        return Solder::escape($s, $format == 'graph' ? 'js' : 'csv');
    }

    private static function _barchart(
        $APP,
        $dayfrom, // not object | "12months" | "24months"
        $step, // in days. 30 to use months
        $dayto,
        $format, // "graph" | "csv" | "data"
        $debug=FALSE // bool
    ) {
        $DB = $APP->db();
        $TYP = new CType($DB);
        
        $colors = array();
        $databytype = ''; // by-type graph or csv (inlcudes income-expense if $format!='graph')
        $databyie = ''; // income-expense graph or csv (used only if $format=='graph')
        $outdata = [ // used if $format=='data'
            '_INCOME' => ['name'=>'Income','color'=>'#000','values'=>[]],
            '_EXPENSE' => ['name'=>'Income','color'=>'#000','values'=>[]]
        ];
        
        // Data header
        $databyie = ($format == 'graph' ? "['Date','Income','Expense']" : '');
        $databytype = ($format == 'graph' ? "['Date'" : '"Date","Income","Expense"');
        $TYP->get_type_callback(
            function ($label, $typedata) use (&$colors, &$databytype, &$outdata, $format) {
                $databytype .= ',"' . self::_chart_esc($typedata['name'], $format) . '"';
                $colors[] = "'" . $typedata['chartcolor'] . "'";
                $outdata[$label] = [
                    'name' => $typedata['name'],
                    'color' => $typedata['chartcolor'],
                    'values' => []
                ];
            }
        );
        $databytype .= ($format == 'graph' ? "]" : '');

        if($step != 30) {
            $dayfrom = $dayto - floor(($dayto - $dayfrom) / $step) * $step;
        }
        if($dayfrom === '24months') {
            $dayfrom = new UnixDay($dayto);
            for($i=0; $i<24; $i++) {
                $dayfrom->sub_month();
            }
        } elseif($dayfrom === '12months') {
            $dayfrom = new UnixDay($dayto);
            for($i=0; $i<12; $i++) {
                $dayfrom->sub_month();
            }
        } else {
            $dayfrom = new UnixDay($dayfrom);
        }
        $dayto = new UnixDay($dayto);
        if($step == 30) { // Simulate pcm steps
            $dayfrom->set_day($dayto->day());
        }

        // Loop through time periods
        while($dayfrom->lt($dayto)) {
        
            if($debug) { print("DEBUG TIME PERIOD BASE={$dayfrom->simple_string()} MAX={$dayto->simple_string()}\n"); }

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
            
            if($debug) { 
                $udfrom_str = (new UnixDay($udfrom))->simple_string();
                print("DEBUG TIME PERIOD FROM=".($udfrom_str)." TO=".((new UnixDay($udto))->simple_string())."\n\n");
            }

            $TYP->sum($udfrom, $udto, true /* get timed sum */, $debug);
            
            if($debug) {
                $TYP->get_logs_callback(
                    function ($label, $data) use ($udfrom_str) {
                        
                        print("DEBUG CATEGORY=$label FROM=$udfrom_str\n");
                        $row_prefix = self::_chart_esc("DEBUG.{$label}.{$udfrom_str}", "csv") . ",";
                        foreach($data as $row) {
                            print($row_prefix);
                            foreach($row as $v) {
                                print(self::_chart_esc($v, "csv").",");
                            }
                            print("\n");
                        }
                    }
                );
            }
            
            if($format == 'graph') {
                $databyie .= ',' . implode(',', $TYP->get_gensums_corrected()) . ']';
            } elseif($format == 'csv') {
                $databytype .= ',' . implode(',', $TYP->get_gensums_corrected());
            } elseif($format == 'data') {
                $outdata['_INCOME']['values'][] = $TYP->get_gensums_corrected()['+'];
                $outdata['_EXPENSE']['values'][] = $TYP->get_gensums_corrected()['-'];
            }

            $TYP->get_sum_callback(
                function ($label, $typedata, $sum) use (&$databytype, &$outdata, $format) {
                    if($format == 'data') {
                        $outdata[$label]['values'][] = $sum;
                    } else {
                        $databytype .= "," . $sum;
                    }
                }
            );

            if($format == 'graph') {
                $databytype .= "]";
            }
        }

        if($format == 'data') { return $outdata; }
        return array($databytype, $databyie, $colors);
    }

}

?>
