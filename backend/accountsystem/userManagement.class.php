<?php

    /**
     * Created by PhpStorm.
     * User: moritz
     * Date: 23.12.16
     * Time: 19:54
     */

    class userManagement
    {

        public function __construct($pdo)
        {

            if(is_a($pdo, 'PDO')){
                $this->pdo = $pdo;
            } else {
                $this->pdo = false;
            }
        }

        private function hash_password($password, $iterations=1024*24){
            $salt = bin2hex(openssl_random_pseudo_bytes(64));
            //Password hashing
            $passhash = hash_pbkdf2("sha512", $password, $salt, $iterations, 255);

            return array("password" => $passhash, "salt" => $salt, "iterations" => $iterations);
        }

        public function addUser($name, $password, $type = 3, $status = 1){

            if($this->pdo){

                $iterations = 1024*24;
                $hash = $this->hash_password($password, $iterations);
                $pw = $hash['password'];
                $salt = $hash['salt'];

                $sql1 = "INSERT INTO accounts_users(name, password, salt, iterations) VALUES (:accname, :pw, :salt, :iterations)";
                $sql2 = "INSERT INTO accounts(name, type, status) VALUES (:accname, :acctype, :accstatus)";

                $r1 = $this->pdo->prepare($sql1);
                $r2 = $this->pdo->prepare($sql2);

                try {
                    $this->pdo->beginTransaction();
                    $r1->execute(array(":accname" => $name, ":pw" => $pw, ":salt" => $salt, ":iterations" => $iterations));
                    $r2->execute(array(":accname" => $name, ":acctype" => $type, ":accstatus" => $status));
                    $this->pdo->commit();
                    return array("error" => false, "message" => "Der Nutzer $name (Account-Typ: $type; Account-Status: $status) wurde erfolgreich erstellt!");

                } catch (PDOException $e) {
                    $this->pdo->rollBack();
                    return array("error" => true, "message" => $e->getMessage());
                }



            } else { return array("error" => true, "message" => "PDO konnte nicht initialisiert werden!"); }

        }

        public function activateUser($name, $pw, $email) {
            //The Variable Contents have to be checked before calling the function!
            $newStatus = 2;     //Temporary; waiting for E-Mail - Confirmation

            $newPass = $this->hash_password($pw);
            $npw = $newPass['password'];
            $salt = $newPass['salt'];
            $iterations = $newPass['iterations'];

            $sql1 = "UPDATE accounts SET status=:newstatus, last_status_change=NOW(), activationcode=:activationcode WHERE name=:accname";
            $r1 = $this->pdo->prepare($sql1);

            $activationcode = bin2hex(openssl_random_pseudo_bytes(32));

            $sql2 = "UPDATE accounts_users SET email=:email, password=:pw, salt=:salt, iterations=:iterations WHERE name=:accname";
            $r2 = $this->pdo->prepare($sql2);

            try {
                $this->pdo->beginTransaction();
                $r1->execute(array(":newstatus" => $newStatus, ":accname" => $name, ":activationcode" => $activationcode));
                $r2->execute(array(":email" => $email, ":pw" => $npw, ":salt" => $salt, ":iterations" => $iterations, ":accname" => $name));

                $mail = $this->sendConfirmationMail($activationcode, $name, $email);

                if($mail === true){
                    $this->pdo->commit();
                    return array("error" => false);
                } else {
                    $this->pdo->rollBack();
                    return array("error" => true, "message" => "Fehler: Die Best&auml;tigungs-Mail konnte aufgrund eines Fehlers nicht gesendet werden!");
                }
            } catch (PDOException $e) {
                $this->pdo->rollBack();
                return array("error" => true, "message" => "Es ist ein Fehler aufgetreten: ".$e->getMessage());
            }
        }

        private function sendConfirmationMail($activationcode, $name, $email)
        {
            $to         = $email;
            $subject    = "Gykl Raumreservierung - Anmeldung";

            $headers    = "MIME-Version: 1.0" . "\r\n";
            $headers   .= "Content-type: text/html; charset=iso-8859-1" . "\r\n";
            $headers   .= "To: $name <$email>" . "\r\n";
            $headers   .= "From: activation@lima.zone" . "\r\n";
            $headers   .= "Reply-To: activation@lima.zone" . "\r\n";
            $headers   .= "X-Mailer: PHP/" . phpversion() . "\r\n";

            // Definition of Website Host
            $url = "https://gykl-rr.lima.zone";

            $message    = <<<HTML
<!DOCTYPE html><html><head><title>Account - Aktivierung</title><meta charset=utf-8><meta name="viewport" content="width=device-width, minimum-scale=1, initial-scale=1, user-scalable=yes"><style>html,body{font-family:Roboto,Noto,sans-serif;color:#212121;margin:0 auto;min-width:319px}#header{margin:0 auto;position:absolute;top:0;left:0;width:100%;height:58px;background-color:#8BC34A;color:#212121;box-shadow:0 4px 15px #9E9E9E}#headText{font-size:22px;margin-top:15px;margin-left:18px}#content{width:90%;max-width:600px;height:auto;margin:96px auto 58px auto;text-align:left}#footer{margin:0 auto;width:100%;height:48px;background-color:#212121;color:white;text-align:center}#footText{font-size:13px;padding-top:8px}#pseudoButton{background-color:#2196F3;height:48px;width:auto;text-align:center;font-size:22px;color:white;-webkit-transition:background-color 0.2s;-moz-transition:background-color 0.2s;-ms-transition:background-color 0.2s;-o-transition:background-color 0.2s;transition:background-color 0.2s;border-radius:5px;margin:0 auto}#pseudoButton:hover{background-color:#64B5F6;cursor:pointer}a{color:#33691E;text-decoration:none;-webkit-transition:color 0.2s;-moz-transition:color 0.2s;-ms-transition:color 0.2s;-o-transition:color 0.2s;transition:color 0.2s}a:hover{color:#558B2F}#buttonLink{color:white;text-decoration:none}hr{width:70%;border:none;border-bottom:1px solid #E0E0E0;margin:20px auto}#disclaimer{font-size:14px;color:#616161}</style></head><body><div id="header"><p id="headText">Raumreservierung</p></div><div id="content"><h1 style="text-align: center;">Hallo, $name!</h1><p> <br> <b>Sie haben es fast geschafft!</b><br><br> Um Ihre E-Mail '$email' zu best&auml;tigen, &ouml;ffnen Sie bitte den untenstehenden Link.<br> Anschlie&szlig;end werden Sie sich mit Ihrem Konto bei der Raumreservierung einloggen k&ouml;nnen. <br><br></p> <a href="$url?name=$name&code=$activationcode#confirm-email" title="$url#confirm-email" id="buttonLink" target="_blank"><div id="pseudoButton"><p style="padding-top: 10px;">$name aktivieren!</p></div> </a><hr><p id="disclaimer"> <b>Information</b><br> Diese E-Mail wurde im Rahmen der Account-Aktivierung f&uuml;r die Raumreservierung des <a href="https://gymnasium-klotzsche.de" title="www.gymnasium-klotzsche.de" target="_blank">Gymnasiums Dresden-Klotzsche</a> versandt.<br> Wenn Sie diese E-Mail nicht angefordert haben, dann ignorieren Sie sie einfach.<br><br> Sollten Sie weitere Fragen oder Probleme haben, k&ouml;nnen Sie sich direkt an das <a href="https://gymnasium-klotzsche.de" title="www.gymnasium-klotzsche.de" target="_blank">Gymnasium Dresden-Klotzsche</a> oder die <a href="$url#imprint" title="$url#imprint" target="_blank">Raumreservierung</a> wenden. <br><br> Wir entschuldigen uns f&uuml;r jedwede Art von Unannehmlichkeiten.<br> Ihr Team der Raumreservierung des Gymnasiums Dresden Klotzsche :-)</p></div><div id="footer"><p id="footText"> Raumreservierung &copy; 2017 by<br>Moritz Menzel, Benjamin Kirchhoff, Maximilian Seiler</p></div></body></html>
HTML;

            mail($to, $subject, $message, $headers);
            return true;    //TODO: Change to 'return mail()' again but Webserver Times @ LimaCity are too disgusting
        }

        public function deactivateUser($name)
        {
            $sql1 = "UPDATE accounts SET last_status_change=NULL, email_confirmed=0, activationcode=NULL, status=1 WHERE name=:accname";
            $sql2 = "UPDATE accounts_users SET password=:newpw, salt=:newsalt, iterations=:newiterations, email=NULL WHERE name=:accname";

            $pwgen = $this->hash_password("gykl@2016");
            $pw = $pwgen['password'];
            $salt = $pwgen['salt'];
            $it = $pwgen['iterations'];

            $r1 = $this->pdo->prepare($sql1);
            $r2 = $this->pdo->prepare($sql2);

            try {

                $this->pdo->beginTransaction();
                $r1->execute(array(":accname" => $name));
                $r2->execute(array(":newpw" => $pw, ":newsalt" => $salt, ":newiterations" => $it, ":accname" => $name));
                $this->pdo->commit();

                return array("error" => false);

            } catch (PDOException $e) {
                $this->pdo->rollBack();
                return array("error" => true, "message" => "Es ist ein Fehler aufgetreten: ".$e->getMessage());
            }
        }

        public function confirmUser($name, $code){

            $sql1 = "SELECT status, email_confirmed, activationcode, email FROM accounts, accounts_users
                     WHERE accounts_users.name = accounts.name AND accounts.name = :accname";
            $r1 = $this->pdo->prepare($sql1);

            try {
                $this->pdo->beginTransaction();
                $r1->execute(array(":accname" => $name));
                $this->pdo->commit();

                $res = $r1->fetchAll();
                if(!empty($res)){

                    $res = $res[0];

                    if($res['status'] == 2 && $res['email_confirmed'] == 0 && $res['email'] != null && $res['activationcode'] != null){
                        if($res['activationcode'] == $code){
                            //Confirm User

                            $sql2 = "UPDATE accounts SET status=:accstatus, last_status_change=NOW(), email_confirmed=1, activationcode=NULL
                                     WHERE name=:accname";
                            $r2 = $this->pdo->prepare($sql2);

                            try {
                                $this->pdo->beginTransaction();
                                $r2->execute(array(":accstatus" => 3, ":accname" => $name));
                                $this->pdo->commit();
                                return (array("error" => false, "message" => "Ihr Account wurde erfolgreich aktiviert!"));
                            } catch (Exception $e) {
                                $this->pdo->rollBack();
                                return (array("error" => true, "message" => "Fehler: ".$e->getMessage()));
                            }

                        } else {
                            return array("error" => true, "message" => "Fehler: Der Best&auml;tigungscode ist nicht korrekt!");
                        }
                    } elseif($res['status'] == 3) {
                        return array("error" => true, "message" => "Der Account ($name) wurde bereits aktiviert!");
                    } elseif($res['status'] == 1) {
                        return array("error" => true, "message" => "Der Account ($name) ist momentan deaktiviert!");
                    } else {
                        return array("Es sind nicht alle benötigten Daten in die Datenbank eingetragen! Bitte melden Sie diesen Fehler unbedingt einem Administrator!");
                    }

                } else { return array("error" => true, "message" => "Fehler: Der Nutzername wurde nicht in der Datenbank gefunden!"); }

            } catch (PDOException $e) {
                $this->pdo->rollBack();
                return array("error" => true, "message" => "Fehler: ".$e->getMessage());
            }

        }

        public function getAllUsers() {
            $sql = "SELECT accounts.A_ID, accounts.name, accounts.type, accounts.status, coalesce(accounts.last_login, '-') as last_login, accounts.email_confirmed, coalesce(accounts_users.email,'-') as email
                    FROM accounts, accounts_users
                    WHERE accounts.name = accounts_users.name
                    ORDER BY A_ID";
            $r = $this->pdo->prepare($sql);
            try {
                $r->execute();
                return array("error" => false, "response" => $r->fetchAll());
            } catch (PDOException $e) {
                return array("error" => true, "message" => $e->getMessage());
            }
        }

        public function updateUserData($oldname, $name, $type, $status){

            $sql = "UPDATE accounts SET type=:type, status=:status
                    WHERE accounts.name = :oldname";
            $sql2 = "UPDATE accounts_users SET name=:newname WHERE accounts_users.name = :oldname";
            $r = $this->pdo->prepare($sql);
            $r2 = $this->pdo->prepare($sql2);

            try {
                $this->pdo->beginTransaction();
                $r->execute(array(":type" => $type, ":status" => $status, ":oldname" => $oldname));
                $r2->execute(array(":newname" => $name, ":oldname" => $oldname));
                $this->pdo->commit();

                $newData = $this->getAllUsers();
                if(!$newData['error']){
                    return array("error" => false, "response" => $newData['response']);
                } else {
                    return array("error" => true, "message" => "Fehler bei der Datenbankanfrage: ".$newData['message']);
                }
            } catch (PDOException $e) {
                $this->pdo->rollBack();
                return array("error" => true, "message" => "Fehler beim &Auml;ndern der Nutzerdaten: ".$e->getMessage());
            }

        }

        public function deleteUser($name){

            $sql1 = "DELETE FROM accounts_users WHERE name=:username";
            $sql2 = "DELETE FROM accounts WHERE name=:username";

            $r1 = $this->pdo->prepare($sql1);
            $r2 = $this->pdo->prepare($sql2);

            try {
                $this->pdo->beginTransaction();
                $r1->execute(array(":username" => $name));
                $r2->execute(array(":username" => $name));
                $this->pdo->commit();

                $newData = $this->getAllUsers();

                if($newData['error'] == false) {

                    if(isset($newData['response']) && !empty($newData['response'])) {

                        $res = $newData['response'];
                        return array("error" => false, "data" => $res);

                    } else {
                        return array("error" => true, "message" => "Es sind keine Daten in der Datenbank vorhanden.");
                    }

                } else {
                    return array("error" => true, "message" => "Fehler beim Abruf der Daten: ".$newData['message']);
                }

            } catch (PDOException $e) {
                $this->pdo->rollBack();
                return array("error" => true, "message" => "Fehler bei der Datenbank-Abfrage: ".$e->getMessage());
            }

        }

        public function getErrors(){

            // The LIMIT = 128 is purely case-sensitive, there should never be that much error - reports...
            // and if someone thinks he has to spam, we have to manually clean the table
            $sql = "SELECT errorID, name, email, coalesce(page, '-') as page, text, created FROM errorreport ORDER BY created DESC LIMIT 128";
            $res = $this->pdo->query($sql)->fetchAll();

            for($i=0;$i < count($res);$i++){
                $res[$i]['text'] = preg_replace("/::NEWLINE::/", ' --- ', $res[$i]['text']);
                $res[$i]['text'] = preg_replace("/::AMP::/", "&", $res[$i]['text']);
                $res[$i]['created'] = date("d.m.Y - H:i", strtotime($res[$i]['created']));
            }

            return array("error" => false, "data" => $res);
        }

        public function deleteError($id) {
            $sql = "DELETE FROM errorreport WHERE errorID=:errorid";
            $r = $this->pdo->prepare($sql);

            try {
                $this->pdo->beginTransaction();
                $r->execute(array(":errorid" => $id));
                $this->pdo->commit();

                return $this->getErrors();
            } catch (PDOException $e) {
                $this->pdo->rollBack();
                return array("error" => true, "message" => "Es ist ein Fehler beim Löschen aufgetreten:<br>".$e->getMessage());
            }
        }

        public function changePassword($name, $pw) {
            $sql = "UPDATE accounts_users SET password=:pw, salt=:salt, iterations=:iterations WHERE accounts_users.name=:name";
            $a = $this->hash_password($pw);
            $pw = $a['password'];
            $salt = $a['salt'];
            $iterations = $a['iterations'];

            $r = $this->pdo->prepare($sql);

            try{
                $this->pdo->beginTransaction();
                $r->execute(array(":pw" => $pw, ":salt" => $salt, ":iterations" => $iterations, ":name" => $name));
                $this->pdo->commit();
                return array("error" => false);
            } catch( PDOException $e ) {
                return array("error" => true, "message" => "Es ist ein Fehler beim Ändern des Passwortes aufgetreten: ".$e->getMessage());
            }
        }

    }