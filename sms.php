<?php
    if (!isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER'] != 'sms') {
        echo 'Access Denied. Invalid Username.';
    }

    if (!isset($_SERVER['PHP_AUTH_PW']) && $_SERVER['PHP_AUTH_PW'] != '123') {
        echo 'Access Denied. Invalid Password.';
    }

    if (!isset($_GET)) {
        echo 'Invalid Parameters.';
    }

    $no = trim($_GET['no']);
    $msg = trim($_GET['msg']);
    $action = trim($_GET['action']);
    $source = trim($_GET['source']);
    $socket_1 = trim($_GET['MODEM_1']);
    $socket_2 = trim($_GET['MODEM_2']);
    $socket_3 = trim($_GET['MODEM_3']);

    if ($action == 'send') {
        if ($source == 'MODEM 1') {
            $S_PORT = $socket_1;
            $S_BAUD = 115200;
            $S_DATA = 8;
            $S_STOP = 1;
        } else if ($source == 'MODEM 2') {
            $S_PORT = $socket_2;
            $S_BAUD = 115200;
            $S_DATA = 8;
            $S_STOP = 1;
        } else if ($source == 'MODEM 3') {
            $S_PORT = $socket_3;
            $S_BAUD = 115200;
            $S_DATA = 8;
            $S_STOP = 1;
        } else {
            echo 'SIMCARD IN MODEM NOT REGISTERED'; return;
        }

        exec(sprintf("mode %s: baud=%d data=%d stop=%d parity=n", $S_PORT, $S_BAUD, $S_DATA, $S_STOP), $output);

        $DIO_O = @dio_open(sprintf('%s:', $S_PORT), O_RDWR);

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
                    $DATA  = sprintf('AT+CSCS="GSM"');
                    $DIO_W = dio_write($DIO_O, sprintf("%s\r", $DATA));
                    sleep(1);
                    $DIO_R = dio_read($DIO_O, 1024);
                    // echo sprintf("RESPONSE 1: %s\r\n", $DIO_R);

                    $DATA  = sprintf('AT+CMGS="+%d"', $no);
                    $DIO_W = dio_write($DIO_O, sprintf("%s\r", $DATA));
                    sleep(1);
                    $DATA  = sprintf('%s', $msg);
                    $DIO_W = dio_write($DIO_O, sprintf("%s\r", $DATA));
                    sleep(1);
                    $DIO_W = dio_write($DIO_O, chr(26));
                    sleep(1);
                    $DIO_R = dio_read($DIO_O, 1024);
                    // echo sprintf("RESPONSE 2: %s\r\n", $DIO_R);

                    sleep(2);
                    $DIO_R = dio_read($DIO_O, 1024);
                    // echo sprintf("RESPONSE 3: %s\r\n", $DIO_R);

                    if (substr(trim($DIO_R), -2) == 'OK') {
                        echo 'OK';
                    } else echo 'FAILED';
                } else echo 'AT COMMAND NOT AVAILABLE.';
            } else echo 'MODEM FAILED 3. RECONNECT USB MODEM.';

            @dio_close($DIO_O);

        } else echo 'MODEM FAILED 2. RECONNECT USB MODEM.';
    } else echo 'MODEM FAILED 1. RECONNECT USB MODEM.';

    return;