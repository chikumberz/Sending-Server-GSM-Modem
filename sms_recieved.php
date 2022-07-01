<?php
    $db_host     = 'localhost:3306';
    $db_name     = 'sms';
    $db_username = 'root';
    $db_password = '';

    try {
        $__DB_CON__ = new PDO(sprintf('mysql:host=%s;dbname=%s', $db_host, $db_name), $db_username, $db_password);
        // set the PDO error mode to exception
        $__DB_CON__->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        echo 'DB Connection failed: ' . $e->getMessage();
        return;
    }

    $__DB_STMT__  = $__DB_CON__->prepare('SELECT * FROM sending_servers WHERE name = ? LIMIT 1');
    $__DB_STMT__->execute(['SMS']);

    $SETTING = $__DB_STMT__->fetch();

    $__DB_STMT__  = $__DB_CON__->prepare('SELECT * FROM custom_sending_servers WHERE server_id = ? LIMIT 1');
    $__DB_STMT__->execute([$SETTING['id']]);

    $SETTING_PARAMETERS = $__DB_STMT__->fetch();

    $S_PORT_LIST = [];

    if ($SETTING_PARAMETERS['custom_one_param'] == 'MODEM_1' && $SETTING_PARAMETERS['custom_one_status']) {
        $S_PORT_LIST[] = array(
            'MODEM' => 'MODEM 1',
            'PORT'  => $SETTING_PARAMETERS['custom_one_value']
        );
    }

    if ($SETTING_PARAMETERS['custom_two_param'] == 'MODEM_2' && $SETTING_PARAMETERS['custom_two_status']) {
        $S_PORT_LIST[] = array(
            'MODEM' => 'MODEM 2',
            'PORT'  => $SETTING_PARAMETERS['custom_two_value']
        );
    }

    if ($SETTING_PARAMETERS['custom_three_param'] == 'MODEM_3' && $SETTING_PARAMETERS['custom_three_status']) {
        $S_PORT_LIST[] = array(
            'MODEM' => 'MODEM 3',
            'PORT'  => $SETTING_PARAMETERS['custom_three_value']
        );
    }

    $S_BAUD = 115200;
    $S_DATA = 8;
    $S_STOP = 1;

    foreach ($S_PORT_LIST as $S_PORT) {
        exec(sprintf("mode %s: baud=%d data=%d stop=%d parity=n", $S_PORT['PORT'], $S_BAUD, $S_DATA, $S_STOP), $output);

        $DIO_O = @dio_open(sprintf('%s:', $S_PORT['PORT']), O_RDWR);

        if ($DIO_O) {
            $DATA  = 'AT';
            $DIO_W = dio_write($DIO_O, sprintf("AT\r", $DATA));
            sleep(1);
            $DIO_R = dio_read($DIO_O, 1024);
            // echo sprintf("RESPONSE: %s\r\n", $DIO_R);

            if (substr(trim($DIO_R), -2) == 'OK') {
                $DATA  = 'AT+CMGF=1';
                $DIO_W = dio_write($DIO_O, sprintf("%s\r", $DATA));
                sleep(1);
                $DIO_R = dio_read($DIO_O, 1024);
                // echo sprintf("RESPONSE: %s\r\n", $DIO_R);

                if (substr(trim($DIO_R), -2) == 'OK') {
                    $DATA  = 'AT+CMGL="REC UNREAD"';
                    $DIO_W = dio_write($DIO_O, sprintf("%s\r", $DATA));
                    $DIO_R_TXT = '';

                    $i = 0; do {
                        sleep(2);
                        $DIO_R = dio_read($DIO_O, 1024);
                        $DIO_R = trim(str_replace($DATA, '', $DIO_R));
                        $DIO_R_LEN = strlen($DIO_R);
                        $DIO_R_TXT .= $DIO_R;

                        // echo $DIO_R . '<br>';
                        // echo $DIO_R_LEN . '<br>';

                        if ($DIO_R_LEN < 3) break;
                    $i++; } while ($i < 10);

                    if (substr(trim($DIO_R_TXT), -2) == 'OK') {
                        $DIO_R_TXT   = trim(substr(trim($DIO_R_TXT), 0, -2));
                        $DIO_R_TXT_X = explode('+CMGL: ', $DIO_R_TXT);
                        $DIO_R_TXT_LIST = [];

                        foreach ($DIO_R_TXT_X as $txt) {
                            if ($txt) {
                                $txt_x = explode("\n", trim($txt));
                                $header = $txt_x[0];
                                $message = isset($txt_x[1]) ? $txt_x[1] : '';
                                list($index, $status, $from, $from_str, $date, $time) = explode(',', trim(str_replace('"', '', str_replace(',"', ',', $header))));

                                $index    = (int) $index;
                                $status   = trim($status);
                                $to       = $S_PORT['MODEM'];
                                $from     = str_replace('+', '', $from);
                                $from_str = trim($from_str);
                                $date     = trim($date);
                                $time     = trim($time);
                                $message  = trim($message);
                                $datetime = date('Y-m-d H:i:s', strtotime('20'.$date.' '.$time));

                                $__DB_STMT__  = $__DB_CON__->prepare('INSERT INTO reports (`uid`, `user_id`, `from`, `to`, `message`, `sms_type`, `status`, `send_by`, `cost`, `sending_server_id`, `created_at`) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                                if ($__DB_STMT__->execute([uniqid(), 4, $from, $to, $message, 'plain', 'Recieved', 'to', 0, $SETTING['id'], $datetime])) {
                                    $DATA  = sprintf('AT+CMGD=%d', $index);
                                    $DIO_W = dio_write($DIO_O, sprintf("%s\r", $DATA));
                                    sleep(1);
                                    $DIO_R = dio_read($DIO_O, 1024);
                                    // echo sprintf("RESPONSE: %s\r\n", $DIO_R);
                                }

                                $DIO_R_TXT_LIST[] = array(
                                    'header' => array(
                                        'index'    => $index,
                                        'status'   => $status,
                                        'from'     => $from,
                                        'from_str' => $from_str,
                                        'date'     => $date,
                                        'time'     => $time,
                                        'datetime' => $datetime,
                                    ),
                                    'message' => $message,
                                );
                            }
                        }
                        // echo "<pre>";
                        // print_r($DIO_R_TXT_LIST);

                        echo 'OK';
                    } else echo 'FAILED';
                } else echo 'AT COMMAND NOT AVAILABLE.';
            } else echo 'MODEM FAILED 3. RECONNECT USB MODEM.';

            @dio_close($DIO_O);

        } else echo 'MODEM FAILED 2. RECONNECT USB MODEM.';
    }

    return;