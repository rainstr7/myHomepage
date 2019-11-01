<?php

header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding("UTF-8");

function sendMessage($botToken, $chatId, $message)
{
    $telegramurl = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
    $request = curl_init($telegramurl);
    curl_setopt($request, CURLOPT_POST, true);
    $query = ['chat_id' => $chatId, "text" => $message];
    curl_setopt($request, CURLOPT_POSTFIELDS, $query);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($request);
    curl_close($request);

    return ($result);
}

function sendPhoto($botToken, $chatId, $file, $fileId = null)
{
    $telegramurl = "https://api.telegram.org/bot" . $botToken . "/sendPhoto";
    $request = curl_init($telegramurl);
    curl_setopt($request, CURLOPT_POST, true);
    $query = ['chat_id' => $chatId];
    if ($fileId == null) {
        $query["photo"] = new CurlFile(realpath($file));
    } else {
        $query["photo"] = $fileId;
    }
    curl_setopt($request, CURLOPT_POSTFIELDS, $query);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($request);
    curl_close($request);

    return ($result);
}


function sendDocument($botToken, $chatId, $file, $fileId = null)
{
    $telegramurl = "https://api.telegram.org/bot" . $botToken . "/sendDocument";
    $request = curl_init($telegramurl);
    curl_setopt($request, CURLOPT_POST, true);
    $query = ['chat_id' => $chatId];
    if ($fileId == null) {
        $query["document"] = new CurlFile(realpath($file));
    } else {
        $query["document"] = $fileId;
    }
    curl_setopt($request, CURLOPT_POSTFIELDS, $query);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($request);
    curl_close($request);

    return ($result);
}

/* Getting File extension */
function getExtension($filename)
{
    $path_info = pathinfo($filename);
    if (isset($path_info['extension'])) {
        return mb_strtolower($path_info['extension']);
    } else {
        return null;
    }
}

function mb_str_replace($needle, $replacement, $haystack)
{
    return implode($replacement, mb_split($needle, $haystack));
}

function mb_str_pad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT)
{
    $diff = strlen($input) - mb_strlen($input);

    return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
}

require_once("telegram-site-helper-config.php");

if (!array_key_exists("act", $_GET)) {
    echo json_encode(["status" => "error", "error" => "NO_ACT"]);
    exit();
}

if (DBTYPE == "mysql") {
    try {
        $db = new PDO('mysql:host=' . MYSQL_HOST . ';dbname=' . MYSQL_DBNAME, MYSQL_USER, MYSQL_PASSWORD);
        $db->exec('set names utf8');
    } catch (PDOException $e) {
    }
} else {
    try {
        $db = new PDO("sqlite:" . SQLITE_DBNAME);
        $db->exec('PRAGMA journal_mode=WAL;');
    } catch (PDOException $e) {
    }
}


$act = $_GET["act"];

switch ($act) {

    case 'newChat':

        $sth = $db->prepare("SELECT * FROM telegramSiteHelperManagers WHERE managerStatus=:managerStatus AND managerTelegramId IS NOT NULL");
        $sth->execute([":managerStatus" => 1]);
        $managers = [];
        while ($answer = $sth->fetch()) {
            $managers[] = $answer["managerId"];
        }

        if (count($managers) == 0) {
            echo json_encode(["status" => "error", "error" => "NO_MANAGERS_AVALIABLE"]);
            exit();
        }
        $chatId = mb_strtoupper(uniqid());

        $client = null;

        if (array_key_exists("chatCustomerName", $_POST)) {
            $chatCustomerName = $_POST["chatCustomerName"];
            $client .= " " . $chatCustomerName;
        } else {
            $chatCustomerName = null;
        }
        if (array_key_exists("chatCustomerPhone", $_POST)) {
            $chatCustomerPhone = $_POST["chatCustomerPhone"];
            $client .= " " . $chatCustomerPhone;
        } else {
            $chatCustomerPhone = null;
        }

        $sth = $db->prepare("INSERT INTO telegramSiteHelperChats (chatId, chatCustomerName, chatCustomerPhone) VALUES (:chatId, :chatCustomerName, :chatCustomerPhone)");
        $sth->execute([
            ":chatId"            => $chatId,
            ":chatCustomerName"  => $chatCustomerName,
            ":chatCustomerPhone" => $chatCustomerPhone,
        ]);
        $sth = $db->prepare("SELECT * FROM telegramSiteHelperManagers");
        $sth->execute();
        while ($answer = $sth->fetch()) {
            sendMessage(BOTTOKEN, $answer["managerTelegramId"],
                "Новый клиент" . $client . " начал чат. Для перехода к чату нажмите /chat_" . $chatId . " или дождитесь сообщения от клиента.");
        }
        echo json_encode(["status" => "ok", "chatId" => $chatId]);
        exit();
        break;

    case 'editChat':
        if (!array_key_exists("chatId", $_POST)) {
            echo json_encode(["status" => "error", "error" => "NO_CHAT_ID"]);
            exit();
        }
        $chatId = $_POST["chatId"];
        if (array_key_exists("chatCustomerName", $_POST)) {
            $chatCustomerName = $_POST["chatCustomerName"];
        } else {
            $chatCustomerName = null;
        }
        if (array_key_exists("chatCustomerPhone", $_POST)) {
            $chatCustomerPhone = $_POST["chatCustomerPhone"];
        } else {
            $chatCustomerPhone = null;
        }
        $sth = $db->prepare("UPDATE telegramSiteHelperChats SET chatCustomerName=:chatCustomerName, chatCustomerPhone=:chatCustomerPhone WHERE chatId=:chatId");
        $sth->execute([
            ":chatId"            => $chatId,
            ":chatCustomerName"  => $chatCustomerName,
            ":chatCustomerPhone" => $chatCustomerPhone,
        ]);

        $sth = $db->prepare("SELECT telegramSiteHelperManagers.managerTelegramId FROM telegramSiteHelperChats LEFT JOIN telegramSiteHelperManagers ON telegramSiteHelperManagers.managerId=telegramSiteHelperChats.chatManager WHERE chatId=:chatId");
        $sth->execute([":chatId" => $chatId]);
        $answer = $sth->fetch();
        $managerToSend = [];
        $managerToSend[] = $answer["managerTelegramId"];
        $sth = $db->prepare("SELECT managerTelegramId FROM telegramSiteHelperManagers WHERE managerNowChat=:managerNowChat");
        $sth->execute([":managerNowChat" => $chatId]);
        while ($answer = $sth->fetch()) {
            $managerToSend[] = $answer["managerTelegramId"];
        }
        $managerToSend = array_unique($managerToSend);
        foreach ($managerToSend as $i => $item) {
            sendMessage(BOTTOKEN, $item,
                "Клиент из чата /chat_" . $chatId . " оставил данные: " . $chatCustomerName . " " . $chatCustomerPhone);
        }


        echo json_encode(["status" => "ok"]);
        exit();
        break;

    case 'sendMessage':

        if (!array_key_exists("chatId", $_POST)) {
            echo json_encode(["status" => "error", "error" => "NO_CHAT_ID"]);
            exit();
        }
        $chatId = $_POST["chatId"];
        if (!array_key_exists("message", $_POST) AND !array_key_exists("file", $_POST)) {
            echo json_encode(["status" => "error", "error" => "NO_MESSAGE"]);
            exit();
        }
        $message = null;
        if (array_key_exists("message", $_POST)) {
            $message = $_POST["message"];
        }
        $file = null;
        if (array_key_exists("file", $_POST)) {
            $file = $_POST["file"];
        }

        if ($message != null) {

            /* Getting managers for send */
            $sth = $db->prepare("SELECT telegramSiteHelperManagers.managerTelegramId, telegramSiteHelperChats.chatCustomerName FROM telegramSiteHelperChats LEFT JOIN telegramSiteHelperManagers ON telegramSiteHelperManagers.managerId=telegramSiteHelperChats.chatManager WHERE chatId=:chatId");
            $sth->execute([":chatId" => $chatId]);
            $answer = $sth->fetch();
            if ($answer["chatCustomerName"] != null) {
                $client = $answer["chatCustomerName"];
            } else {
                $client = "Клиент";
            }
            $managerToSend = [];

            $sth = $db->prepare("SELECT * FROM telegramSiteHelperManagers");
            $sth->execute();
            $managerToSendOne = null;
            while ($answer = $sth->fetch()) {
                if ($chatId == $answer['managerNowChat']) {
                    $managerToSendOne = $answer["managerTelegramId"];
                    break;
                }
                $managerToSend[] = $answer["managerTelegramId"];
            }


            $managerToSend = array_unique($managerToSend);
            $messagePrepared = json_decode('"\ud83d\udde3"') . " " . $client . " (/chat_" . $chatId . ")\n" . $message;
            if (count($managerToSend) == 0 && $managerToSendOne == null) {
                echo json_encode(["status" => "error", "error" => "NO_MANAGER"]);
                exit();
            }
            if ($managerToSendOne != null) {
                sendMessage(BOTTOKEN, $managerToSendOne, $messagePrepared);
            } else {
                foreach ($managerToSend as $i => $item) {
                    sendMessage(BOTTOKEN, $item, $messagePrepared);
                }
            }

            $sth = $db->prepare("INSERT INTO telegramSiteHelperMessages (msgChatId, msgFrom, msgTime, msgText) VALUES (:msgChatId, :msgFrom, :msgTime, :msgText)");
            $msgText = json_encode(["text" => $message]);
            $sth->execute([
                ":msgChatId" => $chatId,
                ":msgFrom"   => "client",
                ":msgTime"   => time(),
                ":msgText"   => $msgText,
            ]);

            /* Update the UPDATE file */
            $fpTime = fopen("tsh-chatUpdates/" . $chatId . ".update", "w");
            fwrite($fpTime, "" . microtime(true));
            fclose($fpTime);
        }

        if ($file != null) {
            /* Update the UPDATE file */
            $fpTime = fopen("tsh-chatUpdates/" . $chatId . ".update", "w");
            fwrite($fpTime, "" . microtime(true));
            fclose($fpTime);

            $messageFileId = null;

            if (mb_strlen($file) > 10240000) {
                echo json_encode(["status" => "error", "error" => "SO_BIG_FILE"]);
                exit();
            }


            /* Save File to server folder */
            $filename = null;
            if (array_key_exists("filename", $_POST)) {
                $filename = $_POST["filename"];
            }
            $ext = getExtension($filename);

            $fileURL = "tsh-files/" . $filename;
            $pos = strpos($file, 'base64') + 7;
            $data = substr($file, $pos);
            $data = base64_decode($data);
            file_put_contents($fileURL, $data);

            /* Getting managers for send */
            $sth = $db->prepare("SELECT telegramSiteHelperManagers.managerTelegramId, telegramSiteHelperChats.chatCustomerName FROM telegramSiteHelperChats LEFT JOIN telegramSiteHelperManagers ON telegramSiteHelperManagers.managerId=telegramSiteHelperChats.chatManager WHERE chatId=:chatId");
            $sth->execute([":chatId" => $chatId]);
            $answer = $sth->fetch();
            if ($answer["chatCustomerName"] != null) {
                $client = $answer["chatCustomerName"];
            } else {
                $client = "Клиент";
            }
            $managerToSend = [];
            $managerToSend[] = $answer["managerTelegramId"];
            $sth = $db->prepare("SELECT managerTelegramId FROM telegramSiteHelperManagers WHERE managerNowChat=:managerNowChat");
            $sth->execute([":managerNowChat" => $chatId]);
            while ($answer = $sth->fetch()) {
                $managerToSend[] = $answer["managerTelegramId"];
            }
            $managerToSend = array_unique($managerToSend);
            $messagePrepared = json_decode('"\ud83d\udde3"') . " " . $client . " (/chat_" . $chatId . ")\nОтправил файл:";
            /* Sending ... */
            foreach ($managerToSend as $i => $item) {
                sendMessage(BOTTOKEN, $item, $messagePrepared);
                if ($ext == "jpg" || $ext == "png" || $ext == "jpeg") {
                    if ($messageFileId != null) {
                        sendPhoto(BOTTOKEN, $item, null, $messageFileId);
                    } else {
                        $sf = sendPhoto(BOTTOKEN, $item, $fileURL);
                    }
                    $ft = "photo";
                } else {
                    if ($messageFileId != null) {
                        sendDocument(BOTTOKEN, $item, null, $messageFileId);
                    } else {
                        $sf = sendDocument(BOTTOKEN, $item, $fileURL);
                    }
                    $ft = "file";
                }

                try {
                    $messageSentStatus = json_decode($sf, true);
                    if ($messageSentStatus["ok"] == true) {
                        if ($ft == "photo") {
                            $messageFileId = $messageSentStatus["result"]["photo"][(count($messageSentStatus["result"]["photo"]) - 1)]["file_id"];
                            $messageFileName = "";
                        } else {
                            $messageFileId = $messageSentStatus["result"]["document"]["file_id"];
                            $messageFileName = $messageSentStatus["result"]["document"]["file_name"];
                        }
                    }
                } catch (Exception $e) {
                }

            }

            /* Unlinking file */
            unlink($fileURL);


            /* Save file Id to database */
            if ($ft == "photo") {
                $msgText = json_encode(["photo" => $messageFileId, "filename" => ""]);
            } else {
                $msgText = json_encode(["file" => $messageFileId, "filename" => $messageFileName]);
            }
            $sth = $db->prepare("INSERT INTO telegramSiteHelperMessages (msgChatId, msgFrom, msgTime, msgText) VALUES (:msgChatId, :msgFrom, :msgTime, :msgText)");
            $sth->execute([
                ":msgChatId" => $chatId,
                ":msgFrom"   => "client",
                ":msgTime"   => time(),
                ":msgText"   => $msgText,
            ]);

            $fpTime = fopen("tsh-chatUpdates/" . $chatId . ".update", "w");
            fwrite($fpTime, "" . microtime(true));
            fclose($fpTime);


        }

        echo json_encode(["status" => "ok", "time" => date("j.m (H:i:s)", time())]);
        exit();
        break;


    case
    'pollMessages':

        if (array_key_exists("type", $_GET)) {
            if ($_GET["type"] == 'lp') {
                $type = "lp";
            } elseif ($_GET["type"] == 'sse') {
                $type = "sse";
            } else {
                $type = "lp";
            }
        }


        if (array_key_exists("lastMessageId", $_GET)) {
            $lastMessageId = $_GET["lastMessageId"];
        } else {
            $lastMessageId = 0;
        }


        /* IF it is Server Side Event create a Event-Stream header */
        if ($type == "sse") {
            header('Content-Type: text/event-stream; charset=utf-8');

        }

        set_time_limit(0);

        if ($type == "lp") {
            $nowTime = time();
        }


        /* No cache, no limit, no woman to cry */
        header('Cache-Control: no-cache');


        /* No chat ID */
        if (!array_key_exists("chatId", $_GET)) {

            if ($type == "sse") {
                echo "data: ";
            }

            echo json_encode(["command" => "error", "error" => "NO_CHAT_ID"]);

            if ($type == "sse") {
                echo "\n\n";
            };
            exit();
        }
        /* Getting chat ID */
        $chatId = $_GET["chatId"];

        if ($lastMessageId == 0 OR $type == "sse") {

            $sth = $db->prepare("SELECT count(*) as count FROM telegramSiteHelperChats WHERE chatId=:chatId");
            $sth->execute([":chatId" => $chatId]);
            $answer = $sth->fetch();
            /* If chatId is not exists in DB*/
            if ($answer["count"] == 0) {
                if ($type == "sse") {
                    echo "data: ";
                }
                echo json_encode(["command" => "error", "error" => "BAD_CHAT_ID"]);
                if ($type == "sse") {
                    echo "\n\n";
                };
                exit();
            }

            /* Getting 500 last messages */
            $sth = $db->prepare("SELECT * FROM telegramSiteHelperMessages LEFT JOIN telegramSiteHelperManagers ON telegramSiteHelperManagers.managerId=telegramSiteHelperMessages.msgFrom WHERE msgChatId=:msgChatId ORDER BY msgTime DESC, msgId DESC LIMIT 500");
            $sth->execute([":msgChatId" => $chatId]);
            $messages = [];
            $lastMessageId = 0;
            while ($answer = $sth->fetch()) {
                $msg = [
                    "msgId"       => $answer["msgId"],
                    "msgFrom"     => $answer["msgFrom"],
                    "msgTime"     => date("j.m (H:i:s)", $answer["msgTime"]),
                    "msgText"     => mb_str_replace("\n", "<br>", $answer["msgText"]),
                    "managerName" => $answer["managerName"],
                ];
                $messages[] = $msg;
                if ($lastMessageId == 0) {
                    $lastMessageId = $answer["msgId"];
                }
            }

            if ($type == "sse") {
                echo "data: ";
            }

            echo json_encode([
                "command"       => "allMessages",
                "messages"      => array_reverse($messages),
                "lastMessageId" => $lastMessageId,
            ]);

            if ($type == "sse") {
                echo "\n\n";
                @ob_flush();
                flush();
            } else {
                exit();
            }

            if ($type == "sse") {
                echo "data: ";
                echo json_encode(["command" => "loadComplete"]) . "\n\n";
                @ob_flush();
                flush();
            }
        }


        if ($type == "sse") {
            $lastTimeQuery = microtime(true); /* If it is a server side event - lastTimeQuery is NOW */
        } else {
            $lastTimeQuery = 0;
        }


        while (true) {

            @$lastTimeUpdate = file_get_contents("tsh-chatUpdates/" . $chatId . ".update");

            if ($lastTimeUpdate != false) {
                $lastTimeUpdate = (float)$lastTimeUpdate;
                $lastTimeQuery = (float)$lastTimeQuery;
                if ($lastTimeUpdate > $lastTimeQuery) {
                    $sth = $db->prepare("SELECT * FROM telegramSiteHelperMessages LEFT JOIN telegramSiteHelperManagers ON telegramSiteHelperManagers.managerId=telegramSiteHelperMessages.msgFrom WHERE msgChatId=:msgChatId AND msgId>:msgId ORDER BY msgId ASC");
                    $sth->execute([":msgChatId" => $chatId, ":msgId" => $lastMessageId]);
                    $messages = [];
                    $lastTimeQuery = microtime(true);
                    while ($answer = $sth->fetch()) {
                        $msg = [
                            "msgId"       => $answer["msgId"],
                            "msgFrom"     => $answer["msgFrom"],
                            "msgTime"     => date("j.m (H:i:s)", $answer["msgTime"]),
                            "msgText"     => mb_str_replace("\n", "<br>", $answer["msgText"]),
                            "managerName" => $answer["managerName"],
                        ];
                        $messages[] = $msg;
                        $lastMessageId = $answer["msgId"];
                    }
                    if (count($messages) > 0) {
                        if ($type == "sse") { // Выводим, если это ServerSideEvent
                            echo "data: ";
                        }
                        echo json_encode([
                            "command"       => "newMessages",
                            "messages"      => $messages,
                            "lastMessageId" => $lastMessageId,
                        ]);
                        if ($type == "sse") {
                            echo "\n\n";
                            @ob_flush();
                            flush();
                        } else {
                            exit();
                        }
                    } else {
                        if ($type == "sse") { // Выводим, если это ServerSideEvent
                            echo "data: ";
                            echo json_encode(["command" => "nothing"]) . "\n\n";
                            @ob_flush();
                            flush();
                        }
                    }

                }
            }
            usleep(500000);
            if ($type == "lp") {
                if ((time() - $nowTime) > 60) {
                    echo json_encode(["command" => "timeout"]);
                    exit();
                }
            }
        }
        break;


    case 'getPhoto':

        if (!array_key_exists("fileId", $_GET)) {
            header("https/1.0 404 Not Found");
            exit();
        }
        $fileId = $_GET["fileId"];
        $telegramurl = "https://api.telegram.org/bot" . BOTTOKEN . "/getFile";
        $request = curl_init($telegramurl);
        curl_setopt($request, CURLOPT_POST, true);
        $query = ['file_id' => $fileId];
        curl_setopt($request, CURLOPT_POSTFIELDS, $query);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($request);
        curl_close($request);

        @$result = json_decode($result, true);
        if ($result["ok"] == true) {
            $photoData = file_get_contents("https://api.telegram.org/file/bot" . BOTTOKEN . "/" . $result["result"]["file_path"]);
            header('Content-Type: image/jpeg');
            print $photoData;

        } else {
            header("https/1.0 404 Not Found");
            exit();
        }
        break;

    case 'getDocument':

        if (!array_key_exists("fileId", $_GET)) {
            header("https/1.0 404 Not Found");
            exit();
        }

        $fileId = $_GET["fileId"];

        if (array_key_exists("filename", $_GET)) {
            $filename = $_GET["filename"];
        } else {
            $filename = null;
        }

        $telegramurl = "https://api.telegram.org/bot" . BOTTOKEN . "/getFile";
        $request = curl_init($telegramurl);
        curl_setopt($request, CURLOPT_POST, true);
        $query = ['file_id' => $fileId];
        curl_setopt($request, CURLOPT_POSTFIELDS, $query);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($request);
        curl_close($request);

        @$result = json_decode($result, true);
        if ($result["ok"] == true) {
            if ($filename == null) {
                $filename = urlencode(basename("https://api.telegram.org/file/bot" . BOTTOKEN . "/" . $result["result"]["file_path"]));
            }
            $documentData = file_get_contents("https://api.telegram.org/file/bot" . BOTTOKEN . "/" . $result["result"]["file_path"]);
            header("Content-Disposition: attachment; filename=" . $filename);
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
            header("Content-Description: File Transfer");
            //header("Content-Length: " . filesize($file));
            print $documentData;

        } else {
            header("https/1.0 404 Not Found");
            exit();
        }
        break;

    default:
        echo json_encode(["status" => "error", "error" => "BAD_ACT"]);
        exit();

}