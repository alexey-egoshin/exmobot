<?php

    require_once (__DIR__ . '/const.php');
    require_once (__DIR__  . '/Db.interface.php');
    require_once (__DIR__ . '/MySQL.class.php');
    require_once (__DIR__ . '/SQLite.class.php');


    /*установка пункта меню активным если в нем находимся*/
    function check_active($url){
        $real_uri = $_SERVER['REQUEST_URI'];
        if (($p = strpos($real_uri, '?')) === false){
            $uri = substr($real_uri, 1);
        }else{
            $uri = substr($real_uri, 1, strpos($real_uri, '?') - 1);
        }
        echo ($uri == $url)? 'class="active"' : '';
    }




    function send_email(){
        $email = (isset($_POST['email']))? $_POST['email'] : '';
        $email_data = Db::get_email($email);
        if (is_array($email_data)){
            $to = $email_data[0]['email'];
            $subject = $email_data[0]['subject'];
            $message = $email_data[0]['message'];
            $from = Db::get_option('from');
            $images = $email_data[0]['images'];
            $images = explode(',', $images);
            $files = array();
            $count = 0;
            if(is_array($images)){
                foreach($images as $image){
                    if(!file_exists($image)) continue;
                    $count++;
                    $files[$count]['name'] = $image;
                    $files[$count]['type'] = 'application/octet-stream';
                }
            }
            
            if (Mailer::send3($to, $subject, $message, $from, $files)){
                Db::mark_email_as_sended($to);
            }
        }
    }

    /**
     * возвращает список изображений, имеющихся в каталоге изображений
     * @param $dir
     */
    function get_images($dir){
        $images = array();
        if (is_array($files = scandir($dir))){
            foreach($files as $file){
                if ($file == '.' || $file == '..') continue;
                $images[$file] = IMG_DIR . $file;
            }
        }
        return $images;
    }

    /**
     * возвращает список Log-файлов
     */
    function get_log_files(){
        $path = '../../log';
        $logs_files = array();
        $files = scandir($path);
        if (is_array($files) && count($files)){
            foreach ($files as $file){
                if ($file == '.' || $file == '..') continue;
                $logs_files[] = $file;
            }
        }
        return $logs_files;
    }

    /**
     * возвращает список config-файлов
     */
    function get_conf_files(){
        $path = '../../conf';
        $conf_files = array();
        $files = scandir($path);
        if (is_array($files) && count($files)){
            foreach ($files as $file){
                if ($file == '.' || $file == '..') continue;
                $conf_files[] = $file;
            }
        }
        return $conf_files;
    }


    /**
     * возвращает список файлов со стратегиями
     */
    function get_strategy_files(){
        $path = '../../strategy';
        $strategy_files = array();
        $files = scandir($path);
        if (is_array($files) && count($files)){
            foreach ($files as $file){
                if ($file == '.' || $file == '..') continue;
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'py') continue;
                if ($file === '__init__.py') continue;
                $strategy_files[] = $file;
            }
        }
        return $strategy_files;
    }

    /**
     * возвращает имя фала базы данных в каталоге db 
     * (первый попавшийся файл с раширением sqlite)
     */
    function get_database_file(){
        return 'store.sqlite';
        $db = SQLite::get_instance();
        $db_file = $db->get_db_name();
        unset($db);
        return $db_file;
    }


    /*---------------------------------------------------------------------------------*/
/**
 * возвращает содержимое всех таблиц базы данных sqlite
 * либо конкретной таблицы если задано ее имя
 * @param $db_name полное имя файла базы данных
 * @param  $table_name имя таблицы БД
 * @param $limit
 * @return array|string
 */
    function get_database_data_sqlite($db_name, $table_name = null, $limit = null){
        try{
            $db = new SQLite3($db_name);
            $sql = "SELECT * FROM sqlite_master WHERE type = 'table'";
            $result = array();
            $tables = array();
            $res = $db->query($sql);
            while( $row = $res->fetchArray(SQLITE3_ASSOC)){
                $tables[] = $row['name'];
            }
            foreach($tables as $table){
                if ($table_name !== null && $table_name !== $table) continue;
                $sql = ($limit == null)? "SELECT * FROM $table" : "SELECT * FROM $table LIMIT $limit";
                $res = $db->query($sql);
                $count = 0;
                $result[$table] = array();
                while( $row = $res->fetchArray(SQLITE3_ASSOC)){
                    $result[$table][$count++] = $row;
                }
            }
            $db->close();
            return $result;

        }catch(Exception $e){
            return $e->getMessage();
        }
    }

/**
 * возвращает содержимое всех таблиц базы данных mysql
 * либо конкретной таблицы если задано ее имя
 * @param $db_name полное имя файла базы данных
 * @param  $table_name имя таблицы БД
 * @param  $limit
 * @return array|string
 */
    function get_database_data_mysql($table_name = null, $limit = null){
        try{
            $db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$db) {
                die('Connect Error (' . mysqli_connect_errno() . ') '
                    . mysqli_connect_error());
            }
            $db->set_charset('utf8');
            $sql = "SHOW TABLES";
            $result = array();
            $tables = array();
            $res = mysqli_query($db, $sql);
            while($row = mysqli_fetch_assoc($res))
                $tables[] = $row['Tables_in_' . DB_NAME];
            mysqli_free_result($res);

            foreach($tables as $table){
                if ($table_name !== null && $table_name !== $table) continue;
                $sql = ($limit == null)? "SELECT * FROM $table" : "SELECT * FROM $table LIMIT $limit";
                $res = mysqli_query($db, $sql);
                $count = 0;
                $result[$table] = array();
                while( $row = mysqli_fetch_assoc($res)){
                    $result[$table][$count++] = $row;
                }
                mysqli_free_result($res);
            }
            mysqli_close($db);
            return $result;

        }catch(Exception $e){
            return $e->getMessage();
        }
    }

    /**
     * выполняет произвольный запрос к базе sqlite
     */
    function exec_query_sqlite($db_name, $sql){
        try{
            $db = new SQLite3($db_name);
            $res = $db->query($sql);
            $result = array();
            $count = 0;
            while( $row = $res->fetchArray(SQLITE3_ASSOC)){
                $result[$count++] = $row;
            }
            return $result;
        }catch(Exception $e){
            return $e->getMessage();
        }

    }

    /**
     * выполняет произвольный запрос к базе mysql
     */
    function exec_query_mysql($sql){
        try{
            $db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$db) {
                die('Connect Error (' . mysqli_connect_errno() . ') '
                    . mysqli_connect_error());
            }
            $result = array();
            $count = 0;
            $res = mysqli_query($db, $sql);
            if (is_object($res)){
                while($row = mysqli_fetch_assoc($res))
                    $result[$count++] = $row;
                mysqli_free_result($res);
            }else{
                $result = $res;
            }
            mysqli_close($db);
            return $result;

        }catch(Exception $e){
            return $e->getMessage();
        }
    }

    /**
     * возвращает данные по изменению балансов
     * сгруппированные по сессиям и валютам и отсортированные по времени
     */
    function get_balances_data_sqlite($db_name){
        $selector = array();
        $rows = exec_query_sqlite($db_name, 'SELECT session_id FROM balance GROUP BY session_id');
        if (is_array($rows)){
            foreach ($rows as $row){
                $session_id = $row['session_id'];
                $rows2 = exec_query_sqlite($db_name, "SELECT currency FROM balance WHERE session_id='{$session_id}' GROUP BY currency");
                if (is_array($rows2)){
                    foreach($rows2 as $row2){
                        $selector[$session_id][] = $row2['currency'];
                    }
                }

            }
        }
        $result = array();
        foreach($selector as $session_id => $currencies){
            foreach($currencies as $currency){
                $rows = exec_query_sqlite($db_name, "SELECT utime, amount FROM balance WHERE currency='{$currency}' AND session_id='{$session_id}' ORDER BY utime ASC");
                $result[$session_id][$currency] = $rows;
            }
        }

        return $result;

    }

    /**
     * возвращает данные по изменению балансов
     * сгруппированные по сессиям и валютам и отсортированные по времени
     */
    function get_balances_data_mysql(){
        $selector = array();
        $rows = exec_query_mysql('SELECT session_id FROM balance GROUP BY session_id');
        if (is_array($rows)){
            foreach ($rows as $row){
                $session_id = $row['session_id'];
                $rows2 = exec_query_mysql("SELECT currency FROM balance WHERE session_id='{$session_id}' GROUP BY currency");
                if (is_array($rows2)){
                    foreach($rows2 as $row2){
                        $selector[$session_id][] = $row2['currency'];
                    }
                }

            }
        }
        $result = array();
        foreach($selector as $session_id => $currencies){
            foreach($currencies as $currency){
                $rows = exec_query_mysql("SELECT utime, amount FROM balance WHERE currency='{$currency}' AND session_id='{$session_id}' ORDER BY utime ASC");
                $result[$session_id][$currency] = $rows;
            }
        }

        return $result;
    }


    /**
     * возвращает значение баланса по первой записи в таблице balance
     * @param $store_type тип БД (mysql, sqlite)
     */
    function get_start_balance($store_type, $session_id, $currency){
        $sql = "SELECT amount FROM balance WHERE session_id='{$session_id}' AND currency='{$currency}' ORDER BY utime ASC LIMIT 1";
        if ($store_type == 'mysql'){
            $res = exec_query_mysql($sql);
        }elseif($store_type == 'sqlite'){
            $res = exec_query_sqlite($sql);
        }else{
            throw new Exception('Param "store_type" must be in (mysql|sqlite)!');
        }
        return $res;
    }

    /**
     * возвращает данные по сделкам
     * сгруппированные по сессиям и валютным парам и отсортированные по времени
     */
    function get_trades_data_sqlite($db_name){
        $selector = array();
        $rows = exec_query_sqlite($db_name, 'SELECT session_id FROM user_trades GROUP BY session_id');
        if (is_array($rows)){
            foreach ($rows as $row){
                $session_id = $row['session_id'];
                $rows2 = exec_query_sqlite($db_name, "SELECT pair FROM user_trades WHERE session_id='{$session_id}' GROUP BY pair");
                if (is_array($rows2)){
                    foreach($rows2 as $row2){
                        $selector[$session_id][] = $row2['pair'];
                    }
                }

            }
        }
        $result = array();
        foreach($selector as $session_id => $pairs){
            foreach($pairs as $pair){
                $rows = exec_query_sqlite($db_name, "SELECT * FROM user_trades WHERE pair='{$pair}' AND session_id='{$session_id}' ORDER BY trade_date DESC");
                $result[$session_id][$pair] = $rows;
            }
        }

        return $result;

    }

    /**
     * возвращает данные по сделкам
     * сгруппированные по сессиям и валютным парам и отсортированные по времени
     */
    function get_trades_data_mysql(){
        $selector = array();
        $rows = exec_query_mysql('SELECT session_id FROM user_trades GROUP BY session_id');
        if (is_array($rows)){
            foreach ($rows as $row){
                $session_id = $row['session_id'];
                $rows2 = exec_query_mysql("SELECT pair FROM user_trades WHERE session_id='{$session_id}' GROUP BY pair");
                if (is_array($rows2)){
                    foreach($rows2 as $row2){
                        $selector[$session_id][] = $row2['pair'];
                    }
                }

            }
        }
        $result = array();
        foreach($selector as $session_id => $pairs){
            foreach($pairs as $pair){
                $rows = exec_query_mysql("SELECT * FROM user_trades WHERE pair='{$pair}' AND session_id='{$session_id}' ORDER BY trade_date DESC");
                $result[$session_id][$pair] = $rows;
            }
        }

        return $result;

    }


    /**
     * Перенос данных из базы SQLite в базу MySQL
     * @param $db_name
     */
    function move_data_sqlite_mysql($db_name){
        try{
            $mysql = MySQL::get_instance();
            $mysql->delete_tables();
            $mysql->create_tables();
            $data = get_database_data_sqlite($db_name);
            foreach($data as $table => $rows){
                $mysql->insert($table, $rows);
            }
            return true;
        }catch (Exception $e){
            return strval($e->getMessage());
        }
    }

    /**
     * Перенос данных из базы MySQL в базу SQLite
     * @param $db_name
     */
    function move_data_mysql_sqlite(){
        try{
            $sqlite = SQLite::get_instance();
            $sqlite->delete_tables();
            $sqlite->create_tables();
            $data = get_database_data_mysql();
            foreach($data as $table => $rows){
                $sqlite->insert($table, $rows);
            }
            return true;
        }catch (Exception $e){
            return strval($e->getMessage());
        }
    }


    /**
     * Возвращает направление обмена обратное заданному
     * @param $direction направление обмена
     * @return string
     */
    function direction_reverse($direction){
        $valutes = explode('_', $direction);
        return $valutes[1] . '_' . $valutes[0];
    }


    /**
     * Возвращает результаты анализа торгов в сессии
     * по заданной паре в виде массива
     * данные из базы sqlite
     * @param $db_name
     * @return array|bool массив вида
     * Array(
     *
     *  'session_id' =>
     *      Array(
     *          'pair' => Array(
     *          'amount_profit' => $amount_profit,
     *          'quantity_profit' => $quantity_profit,
     *          'amount_sum' => $amount_sum,
     *          'quantity_sum' => $quantity_sum,
     *          'count_sell' => $count_sell,
     *          'count_buy' => $count_buy
     *         )
     *
     *
     *      )
     * )
     *
     */
    function analize_user_trades_sqlite($db_name){
        $data = get_trades_data_sqlite($db_name);
        $result = array();
        $fee = 0.998;
        foreach($data as $session_id => $session_data){
            $result[$session_id] = array();
            foreach($session_data as $pair => $trades){
                $trades = array_reverse($trades);
                $count_sell = 0;
                $count_buy = 0;
                $amount_start = 0.0;
                $amount_curr = 0.0;
                $amount_sum = 0.0;
                $quantity_start = 0.0;
                $quantity_curr = 0.0;
                $quantity_sum = 0.0;
                $sell_sum = 0.0;
                $buy_sum = 0.0;
                foreach($trades as $trade){
                    if ($trade['trade_type'] == 'sell'){
                        $count_sell++;
                        if ($amount_start == 0){
                            $amount_start = $trade['amount'];
                        }
                        $amount_curr += $trade['amount'] * $fee;
                        $quantity_curr -= $trade['quantity'];
                        $sell_sum += $trade['amount'];

                    }elseif ($trade['trade_type'] == 'buy'){
                        $count_buy++;
                        if ($quantity_start == 0){
                            $quantity_start = $trade['quantity'];
                        }
                        $amount_curr -= $trade['amount'];
                        $quantity_curr += $trade['quantity'] * $fee;
                        $buy_sum += $trade['amount'];
                    }
                    $amount_sum += $trade['amount'];
                    $quantity_sum += $trade['quantity'];
                }
                $amount_profit = $amount_curr - $amount_start;
                $quantity_profit = $quantity_curr - $quantity_start;
                $result[$session_id][$pair] = array(
                    'amount_start' => $amount_start,
                    'quantity_start' => $quantity_start,
                    'amount_profit' => $amount_profit,
                    'quantity_profit' => $quantity_profit,
                    'amount_sum' => $amount_sum,
                    'quantity_sum' => $quantity_sum,
                    'count_sell' => $count_sell,
                    'count_buy' => $count_buy,
                    'sell_sum' => $sell_sum,
                    'buy_sum' => $buy_sum
                );

            }
        }
        return $result;
    }

    /**
     * Возвращает результаты анализа торгов в сессии
     * по заданной паре в виде массива
     * данные из базы mysql
     * @param $db_name
     * @return array|bool массив вида
     * Array(
     *
     *  'session_id' =>
     *      Array(
     *          'pair' => Array(
     *          'amount_profit' => $amount_profit,
     *          'quantity_profit' => $quantity_profit,
     *          'amount_sum' => $amount_sum,
     *          'quantity_sum' => $quantity_sum,
     *          'count_sell' => $count_sell,
     *          'count_buy' => $count_buy
     *         )
     *
     *
     *      )
     * )
     *
     */
    function analize_user_trades_mysql(){
        $data = get_trades_data_mysql();
        $result = array();
        $fee = 0.998;
        foreach($data as $session_id => $session_data){
            $result[$session_id] = array();
            foreach($session_data as $pair => $trades){
                $trades = array_reverse($trades);
                $count_sell = 0;
                $count_buy = 0;
                $amount_start = 0.0;
                $amount_curr = 0.0;
                $amount_sum = 0.0;
                $quantity_start = 0.0;
                $quantity_curr = 0.0;
                $quantity_sum = 0.0;
                $sell_sum = 0.0;
                $buy_sum = 0.0;
                foreach($trades as $trade){

                    if ($trade['trade_type'] == 'sell'){
                        if ($amount_start == 0){
                            $amount_start = $trade['amount'];
                        }
                        if ($quantity_start == 0){
                            $quantity_start = $trade['quantity'];
                            $quantity_curr = $trade['quantity'];
                        }
                        $count_sell++;
                        $amount_curr += $trade['amount'] * $fee;
                        $quantity_curr -= $trade['quantity'];
                        $sell_sum += $trade['amount'];

                    }elseif ($trade['trade_type'] == 'buy'){
                        if ($amount_start == 0){
                            $amount_start = $trade['amount'];
                            $amount_curr = $trade['amount'];
                        }
                        if ($quantity_start == 0){
                            $quantity_start = $trade['quantity'];
                        }
                        $count_buy++;
                        $amount_curr -= $trade['amount'];
                        $quantity_curr += $trade['quantity'] * $fee;
                        $buy_sum += $trade['amount'];
                    }
                    $amount_sum += $trade['amount'];
                    $quantity_sum += $trade['quantity'];
                }
                $amount_profit = $amount_curr - $amount_start;
                $quantity_profit = $quantity_curr - $quantity_start;
                $result[$session_id][$pair] = array(
                    'amount_start' => $amount_start,
                    'quantity_start' => $quantity_start,
                    'amount_profit' => $amount_profit,
                    'quantity_profit' => $quantity_profit,
                    'amount_sum' => $amount_sum,
                    'quantity_sum' => $quantity_sum,
                    'count_sell' => $count_sell,
                    'count_buy' => $count_buy,
                    'sell_sum' => $sell_sum,
                    'buy_sum' => $buy_sum
                );

            }
        }
        return $result;
    }


?>