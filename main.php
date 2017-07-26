<?php

// shut up PHP from uninitialized variables
error_reporting(E_ALL & ~(E_NOTICE | E_WARNING));

include_once('admin_cmds.php');
include_once('bot_cmds.php');
include_once('bloodmoon.php');
include_once('antihack.php');

class ServerAdm {

    use AdminCmds,
        BotCmds,
        BloodMoonParty,
        AntiHack;

    private $host = '127.0.0.1';
    private $port = '8081';
    private $pass = 'xxxxxxxxxxxx';
    private $socket;
    private $line;
    // bloodmoon day related
    private $server_day = 0;
    private $next_bloodmoon = 0;
    private $bloodmon_active = false;
    private $cmdseq = 0;
    // database related
    private $db;
    private $track_stmt;
    private $chat_stmt;
    private $player_stmt;
    private $newplayer_stmt;
    private $updateplayer_stmt;
    private $playtime_stmt;
    private $playerlevel_stmt;
    private $itemtrack_stmt;
    private $waypoint_stmt;
    private $landwipe_stmt;
    // schedule server command execution
    private $sched_commands = array();
    private $servermsg_cnt = 0;
    private $block_collapse = 0;
    private $adm_list = array();
    private $trader_list = array();
    private $reboot_timer = 864000; // 24 hours
    private $event_lasttime = 0;
    private $pending_event = false;
    private $ping_check = array();
    // useless string tokens
    private $utokens = array('lifetime=', 'entityid=', 'name=', 'type=', 'dead=', 'pos=', '(', ')', '[', ']', ':', '\'',
        'rot=', 'remote=', 'steamOwner=', 'health=', 'deaths=', 'zombies=', 'players=', 'slot=',
        'qnty=', 'quality=', 'parts=', 'score=', 'level=', 'tag=', 'location=', 'item=',
        'steamid=', 'ip=', 'id=', 'ping=', 'OwnerID=', 'PlayerID=', 'PlayerName=', 'online=',
        'playtime=', 'seen=');

    public function __construct() {
        $this->socket = fsockopen($this->host, $this->port) or die('Could not connect to: ' . $this->host);

        stream_set_blocking($this->socket, false);

        $this->db = new SQLite3('7adm.db');
        $this->db->enableExceptions(true);

        try {
            $this->db->exec("PRAGMA journal_mode = WAL;" .
                    "PRAGMA synchronous = NORMAL;" .
                    "CREATE TABLE IF NOT EXISTS players (" .
                    "id        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL," .
                    "nick      TEXT," .
                    "steamid   TEXT NOT NULL UNIQUE," .
                    "lastpos_x FLOAT," .
                    "lastpos_y FLOAT," .
                    "lastpos_z FLOAT," .
                    "lastseen  TIMESTAMP DEFAULT (strftime('%s', 'now'))," .
                    "laststate TEXT," .
                    "zkill     INTEGER DEFAULT 0," .
                    "pkill     INTEGER DEFAULT 0," .
                    "playtime  INTEGER DEFAULT 0," .
                    "level     INTEGER DEFAULT 1," .
                    "ping      INTEGER DEFAULT 0" .
                    ");" .
                    "CREATE TABLE IF NOT EXISTS server_admins (" .
                    "id        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL," .
                    "steamid   TEXT NOT NULL UNIQUE," .
                    "obs       TEXT" .
                    ");" .
                    "CREATE TABLE IF NOT EXISTS player_bans (" .
                    "id        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL," .
                    "steamid   TEXT NOT NULL UNIQUE," .
                    "nick      TEXT," .
                    "reason    TEXT" .
                    ");" .
                    "CREATE TABLE IF NOT EXISTS player_kit (" .
                    "id        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL," .
                    "steamid   TEXT NOT NULL UNIQUE," .
                    "stamp     TIMESTAMP DEFAULT (strftime('%s', 'now'))" .
                    ");" .
                    "CREATE TABLE IF NOT EXISTS player_chat (" .
                    "id        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL," .
                    "nick      TEXT," .
                    "message   TEXT" .
                    ");" .
                    "CREATE TABLE IF NOT EXISTS player_track (" .
                    "id        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL," .
                    "steamid   TEXT NOT NULL," .
                    "stamp     TIMESTAMP DEFAULT (strftime('%s', 'now'))," .
                    "pos_x     FLOAT," .
                    "pos_y     FLOAT," .
                    "pos_z     FLOAT," .
                    "event     TEXT" .
                    ");" .
                    "CREATE INDEX IF NOT EXISTS idx_player_track ON player_track(steamid,stamp,pos_x,pos_y,pos_z);" .
                    "CREATE TABLE IF NOT EXISTS inventory_track (" .
                    "id        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL," .
                    "steamid   TEXT NOT NULL," .
                    "stamp     TIMESTAMP DEFAULT (strftime('%s', 'now'))," .
                    "itemtype  TEXT," .
                    "quantity  INTEGER," .
                    "quality   INTEGER" .
                    ");" .
                    "CREATE INDEX IF NOT EXISTS idx_inventory_track ON inventory_track(steamid,stamp,itemtype,quantity,quality);" .
                    "CREATE TABLE IF NOT EXISTS waypoints (" .
                    "id        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL," .
                    "ownerid   TEXT NOT NULL," .
                    "pos_x     FLOAT," .
                    "pos_y     FLOAT," .
                    "pos_z     FLOAT," .
                    "public    TEXT," .
                    "type      TEXT," .
                    "name      TEXT" .
                    ");" .
                    "CREATE TABLE IF NOT EXISTS player_homes (" .
                    "id        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL," .
                    "steamid   TEXT NOT NULL," .
                    "lastused  TIMESTAMP DEFAULT 0," .
                    "pos_x     FLOAT," .
                    "pos_y     FLOAT," .
                    "pos_z     FLOAT" .
                    ");" .
                    "CREATE UNIQUE INDEX IF NOT EXISTS idx_player_homes ON player_homes(steamid);" .
                    "CREATE TABLE IF NOT EXISTS server_messages (" .
                    "id        INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL," .
                    "message   TEXT" .
                    ");"
            );

            $this->track_stmt = $this->db->prepare("insert into player_track(steamid,pos_x,pos_y,pos_z,event) values(?,?,?,?,?)");
            $this->newplayer_stmt = $this->db->prepare("insert or ignore into players(nick,steamid,lastpos_x,lastpos_y,lastpos_z,laststate) values(?,?,?,?,?,?)");
            $this->updateplayer_stmt = $this->db->prepare("update players set nick=? where steamid=?");
            $this->player_stmt = $this->db->prepare("update players set lastpos_x=?,lastpos_y=?,lastpos_z=?,lastseen=strftime('%s', 'now'),ping=? where steamid=?");
            $this->chat_stmt = $this->db->prepare("insert into player_chat(nick,message) values(?,?)");
            $this->itemtrack_stmt = $this->db->prepare("insert into inventory_track(steamid,itemtype,quantity,quality) values(?,?,?,?)");
            $this->waypoint_stmt = $this->db->prepare("insert into waypoints(ownerid,pos_x,pos_y,pos_z,public) values(?,?,?,?,?)");
            $this->playtime_stmt = $this->db->prepare("update players set playtime=? where steamid=?");
            $this->playerlevel_stmt = $this->db->prepare("update players set level=?, zkill=?, pkill=? where steamid=?");
            $this->landwipe_stmt = $this->db->prepare("update players set laststate=? where steamid=?");
        } catch (Exception $ex) {
            echo 'Database statement exception: ' . $ex->getMessage();
        }

        // copy admins from database
        $adm = $this->db->query("select * from server_admins");

        while ($res = $adm->fetchArray(SQLITE3_ASSOC))
            array_push($this->adm_list, $res["steamid"]);

        $this->antihack_load();

        $this->event_lasttime = time();
        $this->pending_event = false;
    }

    public function __destruct() {
        $this->db->close();
    }

    public function server_command($cmd) {
        // NOTE: only one command allowed
        fputs($this->socket, $cmd . "\r\n");
    }

    public function kick($id, $reason) {
        $this->server_command("kick " . $id . " \"" . $reason . "\"");
    }

    public function ban($id, $reason) {
        $this->server_command("ban add " . $id . " 1 year \"" . $reason . "\"");
    }

    public function message($msg, $color = "ffffff") {
        $this->server_command("say \"[ffff00][zBot]: [" . $color . "]" . $msg . "[ffffff]\"");
    }

    public function message_private($msg, $id, $color = "ffffff") {
        $this->server_command("pm " . $id . " \"[ffff00][zBot private]: [" . $color . "]" . $msg . "[ffffff]\"");
    }

    public function server_rankings() {
        static $op = 0;

        if ($op == 0) {
            $data = file_get_contents("https://7daystodie-servers.com/api/?object=servers&element=voters&key=TImquXOwJxMX5iJZANNDGDfvRtzvQdzHATx&month=current&format=json");

            $this->message(".:: Ranking  de votos do Server ::.", "00ff00");

            if ($data) {
                $json = json_decode($data, true);

                foreach ($json['voters'] as $votes) {
                    $this->message($votes['nickname'] . ": [00ffff]" . $votes['votes'] . " votos", "ffff00");
                    //print($votes['nickname'].": ".$votes['votes']." votos\n");
                }
            }
        } else
        if ($op == 1) {
            $stmt = $this->db->prepare('select nick,playtime from players order by playtime desc limit 5');

            $stmt->reset();
            $result = $stmt->execute();

            if ($result) {
                $this->message(".::TOP 5 Viciados do server::.", "00ff00");

                while ($res = $result->fetchArray())
                    $this->message($res[0] . ": [00ffff]" . round($res[1] / 60) . " horas", "ffff00");
            }
        } else
        if ($op == 2) {
            $stmt = $this->db->prepare('select nick,zkill from players order by zkill desc limit 5');

            $stmt->reset();
            $result = $stmt->execute();

            if ($result) {
                $this->message(".::TOP 5 Matadores de zumbi do server::.", "00ff00");

                while ($res = $result->fetchArray())
                    $this->message($res[0] . ": [00ffff]" . $res[1] . " zumbis", "ffff00");
            }
        } else
        if ($op == 3) {
            $stmt = $this->db->prepare('select nick,pkill from players order by pkill desc limit 5');

            $stmt->reset();
            $result = $stmt->execute();

            if ($result) {
                $this->message(".::TOP 5 colecionador de cabecas::.", "00ff00");

                while ($res = $result->fetchArray())
                    $this->message($res[0] . ": [00ffff]" . $res[1] . " players", "ffff00");
            }
        }
        if ($op == 4) {
            $stmt = $this->db->prepare('select nick,level from players order by level desc limit 5');

            $stmt->reset();
            $result = $stmt->execute();

            if ($result) {
                $this->message(".::TOP 5 player level::.", "00ff00");

                while ($res = $result->fetchArray())
                    $this->message($res[0] . ": [00ffff]" . $res[1] . " pontos", "ffff00");
            }
        }

        $op++;

        if ($op >= 4)
            $op = 0;
    }

    public function server_message() {
        $msg = $this->db->querySingle("select message from server_messages order by random() limit 1");

        if ($msg)
            $this->message($msg);
    }

    // track player coordinates and level
    public function track_playerpos($arg) {
        try {
            // tracks player position on map
            $this->track_stmt->reset();
            $this->track_stmt->bindValue(1, $arg[15], SQLITE3_TEXT);            // steamid
            $this->track_stmt->bindValue(2, (float) $arg[2], SQLITE3_FLOAT);     // x
            $this->track_stmt->bindValue(3, (float) $arg[4], SQLITE3_FLOAT);     // y
            $this->track_stmt->bindValue(4, (float) $arg[3], SQLITE3_FLOAT);     // z
            $this->track_stmt->bindValue(5, "walk", SQLITE3_TEXT);              // event type
            $this->track_stmt->execute();

            // update player last position and ping
            $this->player_stmt->reset();
            $this->player_stmt->bindValue(1, (float) $arg[2], SQLITE3_FLOAT);    // x
            $this->player_stmt->bindValue(2, (float) $arg[4], SQLITE3_FLOAT);    // y
            $this->player_stmt->bindValue(3, (float) $arg[3], SQLITE3_FLOAT);    // z
            $this->player_stmt->bindValue(4, (int) $arg[17], SQLITE3_INTEGER);   // ping
            $this->player_stmt->bindValue(5, $arg[15], SQLITE3_TEXT);           // steamid
            $this->player_stmt->execute();

            // update player level
            $this->playerlevel_stmt->reset();
            $this->playerlevel_stmt->bindValue(1, (int) $arg[14], SQLITE3_INTEGER);   // level
            $this->playerlevel_stmt->bindValue(2, (int) $arg[11], SQLITE3_INTEGER);   // zombies kills
            $this->playerlevel_stmt->bindValue(3, (int) $arg[12], SQLITE3_INTEGER);   // player kills
            $this->playerlevel_stmt->bindValue(4, $arg[15], SQLITE3_TEXT);           // steamid
            $this->playerlevel_stmt->execute();
        } catch (Exception $ex) {
            echo 'Caught exception: ' . $ex->getMessage();
        }

        // teleport falling player
        if (round($arg[3]) < -10) {
            $cmd = sprintf("tele %s %d -1 %d", $arg[15], round($arg[2]), round($arg[4]));
            $this->server_command($cmd);

            $this->message_private("Voce foi teleportado do limbo!", $arg[15], "00ffff");
        }

        $this->ping_check[$arg[15]] = array_slice($this->ping_check[$arg[15]], -5, 5);
        $this->ping_check[$arg[15]][] = $arg[17];

        // query player inventory
        $this->server_command("showinventory " . $arg[15] . " " . $arg[15]);
    }

    public function track_playtime($arg) {
        $playtime = (int) chop($arg[5], ' m');

        // update player playtime info
        $this->playtime_stmt->reset();
        $this->playtime_stmt->bindValue(1, $playtime, SQLITE3_INTEGER);   // playtime
        $this->playtime_stmt->bindValue(2, $arg[2], SQLITE3_TEXT);   // steamid
        $this->playtime_stmt->execute();
    }

    public function track_inventory($arg) {
        $this->itemtrack_stmt->reset();
        $this->itemtrack_stmt->bindValue(1, $arg[1], SQLITE3_TEXT);  // steamid
        $this->itemtrack_stmt->bindValue(2, $arg[4], SQLITE3_TEXT);     // itemtype
        $this->itemtrack_stmt->bindValue(3, $arg[5], SQLITE3_INTEGER);  // quantity
        $this->itemtrack_stmt->bindValue(4, $arg[6], SQLITE3_INTEGER);  // quality
        $this->itemtrack_stmt->execute();
    }

    public function track_entity($arg) {
        // remove dead zombies bodies from server entity list
        if (strstr($arg[1], 'EntityZombie')) {
            // corpse is present, remove it from entity list
            if ($arg[12] == 'True')
                $this->server_command('kill ' . $arg[3]);
        }
    }

    public function steamid2nick($steamid) {
        $nick = $this->db->querySingle("select nick from players where steamid='" . $steamid . "'");

        if ($nick)
            return $nick;
        else
            return $steamid;
    }

    public function nick2steamid($nick) {
        $id = $this->db->querySingle("select steamid from players where nick='" . $nick . "'");

        if ($id)
            return $id;
        else
            return $nick;
    }

    public function player_coords($nick) {
        return $this->db->querySingle("select lastpos_x, lastpos_y, lastpos_z from players where nick='" . $nick . "'", true);
    }

    // bool true/false
    public function is_admin($id) {
        return in_array($id, $this->adm_list);
    }

    public function admin_debuff() {
        $buffs = array('freezing', 'hypo1', 'hypo2', 'hypo3', 'overheated', 'heat1', 'heat2', 'drowning', 'bleeding', 'burning', 'burningSmall', 'infection', 'infection2', 'infection3', 'foodPoisoning', 'sprainedLeg', 'internalBleeding', 'dysentery', 'dysentery2', 'brokenLeg', 'AlcoholPoisoning');

        foreach ($this->adm_list as $adm)
            foreach ($buffs as $b)
                $this->server_command('debuffplayer ' . $adm . ' ' . $b);
    }

    public function player_login($arg) {
        // check for steam family share
        if ($arg[4] == 'connected') {
            $arg = explode(', ', str_replace($this->utokens, '', $this->line));

            $pdata = $this->db->querySingle("select nick from players where steamid='" . $arg[3] . "'");
            $geoinfo = json_decode(file_get_contents('http://freegeoip.net/json/' . $arg[5]));

            if ($pdata) {
                // show player nickname change
                if ($pdata != $arg[2])
                    $this->message("Player [00ff00]" . $pdata . "[ffffff] trocou de nick para [00ff00]" . $arg[2]);

                // known player connected on the server
                $this->updateplayer_stmt->reset();
                $this->updateplayer_stmt->bindValue(1, $arg[2], SQLITE3_TEXT);  // nick
                $this->updateplayer_stmt->bindValue(2, $arg[3], SQLITE3_TEXT);  // steamid
                $this->updateplayer_stmt->execute();

                $this->message_private("Bem vindo de volta, " . $arg[2] . "!", $arg[3], "ff00ff");
            }
            else {
                // insert new player in the database
                $this->newplayer_stmt->reset();
                $this->newplayer_stmt->bindValue(1, $arg[2], SQLITE3_TEXT);  // nick
                $this->newplayer_stmt->bindValue(2, $arg[3], SQLITE3_TEXT);  // steamid
                $this->newplayer_stmt->bindValue(3, 0.0, SQLITE3_FLOAT);     // x
                $this->newplayer_stmt->bindValue(4, 0.0, SQLITE3_FLOAT);     // y
                $this->newplayer_stmt->bindValue(5, 0.0, SQLITE3_FLOAT);     // z
                $this->newplayer_stmt->bindValue(6, "alive", SQLITE3_TEXT);  // player state
                $this->newplayer_stmt->execute();
            }

            // non-admin geolocation and shared account test
            if ($this->is_admin($arg[3]) == false) {
                // kick player using shared account
                if ($arg[3] != $arg[4]) {
                    $this->kick($arg[3], "Conta compartilhada steam nao permitida!");
                    return;
                }

                // check where player is from
                if ($geoinfo) {
                    if ($geoinfo->country_code) {
                        $whitelist = array('BR', 'CO', 'AR', 'PE', 'VE', 'CL', 'EC', 'BO', 'PY', 'UY', 'GY', 'SR', 'GF', 'FK');

                        // ban players not in coutry whitelist
                        if (!in_array($geoinfo->country_code, $whitelist)) {
                            $this->ban($arg[3], "SOUTH AMERICA PLAYERS ONLY");
                            return;
                        }
                    }

                    // discard empty region
                    $region = ($geoinfo->region_name ? $geoinfo->region_name . '/' : '') . $geoinfo->country_name;

                    $this->message($arg[2] . ' [00ff00](' . $region . ')[ffffff] conectou-se ao servidor!');
                }
            }
        }
    }

    public function track_wipe($arg) {
        $bx = 0.0;
        $by = 1024.0;
        $ax = -1024.0;
        $ay = 0.0;

        $cx = (float) $arg[2];
        $cy = (float) $arg[4];

        $id = $arg[15];
        $state = $this->db->querySingle("select laststate from players where steamid='" . $id . "'", false);

        // player is in wipe area range
        if ((($cx > $ax) && ($cx < $bx)) && (($cy > $ay) && ($cy < $by))) {
            if ($state == 'alive') {
                $this->landwipe_stmt->reset();
                $this->landwipe_stmt->bindValue(1, 'landwipe', SQLITE3_TEXT);
                $this->landwipe_stmt->bindValue(2, $id, SQLITE3_TEXT);
                $this->landwipe_stmt->execute();

                $this->message_private("ATENCAO: Voce entrou na area de Wipe! Nao construa nada aqui!", $id, "ff0000");
            }
        } else {
            if ($state == 'landwipe') {
                $this->landwipe_stmt->reset();
                $this->landwipe_stmt->bindValue(1, 'alive', SQLITE3_TEXT);
                $this->landwipe_stmt->bindValue(2, $id, SQLITE3_TEXT);
                $this->landwipe_stmt->execute();

                $this->message_private("ATENCAO: Voce saiu da area de wipe!", $id, "00ff00");
            }
        }
    }

    public function ping_checker() {
        foreach ($this->ping_check as $id => $values) {
            $max = 0;
            for ($x = 0; $x < sizeof($values); $x++)
                $max += $values[$x];

            if (($max / sizeof($this->ping_check)) > 450) {
                $nick = $this->steamid2nick($id);

                $this->ping_check[$id] = array();
                $this->server_command("kick " . $id . " \"Limite de ping 400 atingido!\"");

                $this->message("Player " . $nick . " foi kickado por causa do ping muito alto!");
            }
        }
    }

    public function player_chat($arg) {
        // records player chat on the database
        $this->chat_stmt->reset();
        $this->chat_stmt->bindValue(1, $arg[1], SQLITE3_TEXT);
        $this->chat_stmt->bindValue(2, $arg[2], SQLITE3_TEXT);
        $this->chat_stmt->execute();
    }

    public function run_events() {
        // dont overrun pending events
        //if($this->pending_event)
        //    return;

        if ((time() - $this->event_lasttime) >= 5) {
            $this->event_lasttime = time();
            var_dump("EVENT!!");

            if (count($this->sched_commands) > 0) {
                // scheduled commands execution
                foreach ($this->sched_commands as $seq => $cmd) {
                    if ($this->cmdseq > $seq) {
                        $this->server_command($cmd);
                        unset($this->sched_commands[$seq]);
                    }
                }
            }

            $this->ping_checker();

            switch ($this->cmdseq % 3) {
                case 0:
                    $this->server_command("listplayers");
                    $this->pending_event = true;
                    break;
                case 1:
                    $this->server_command("gettime");
                    break;
                case 2:
                    $this->server_command("listents");
                    break;

                default:
                    break;
            }

            $this->cmdcall();
        }
    }

    public function daily_gift() {
        $gifts[] = array('Sniper 250', 'gunSniperRifle 1 250');
        $gifts[] = array('50 Bullets 7.62', '762mmBullet 50');
        $gifts[] = array('5 Medkits', 'firstAidKit 5');
        $gifts[] = array('AK-47 100', 'gunAK47 1 100');
        $gifts[] = array('Shotgun 300', 'gunPumpShotgun 1 300');
        $gifts[] = array('NailGun 600', 'nailgun 1 600');
        $gifts[] = array('Magnum .44 99', 'gun44Magnum 1 99');
        $gifts[] = array('6 Celulas Solares 600', 'solarCell 6 600');
        $gifts[] = array('Capacete Minerador 50', 'miningHelmet 1 50');
        $gifts[] = array('Auger 300', 'auger 1 300');

        $this->message("Grande sorteio do dia!! Fique ligado o bot vai escolher alguem!", "e9d528");

        sleep(3);

        switch (rand(0, 2)) {
            case 0: $this->message("E o premio vai para... quem? umm perai... ewww..", "e9d528");
                break;
            case 1: $this->message("Humm to tentando escolher... ta quase...", "e9d528");
                break;
            case 2: $this->message("Esse aqui? nao, espera.. ah sim esse aqui oh", "e9d528");
                break;
        }

        sleep(3);

        switch (rand(0, 5)) {
            case 0: $this->message("Po cade o nome do cara? tava aqui... ah achei!", "e9d528");
                break;
            case 1: $this->message("Ueh? como assim um zumbi comeu o premio? Fodel, ninguem ganhou :(", "e9d528");
                return;
            case 3: $this->message("Oiala, o cara la ta roubando o premio! pega!! ladraaaaao!! Ninguem ganhou :(", "e9d528");
                return;
            case 4: $this->message("Eita, como assim? esse nao merece o premio nao, nao vou entregar! Ninguem ganhou :(", "e9d528");
                return;
            case 5: $this->message("Deu sorte, quase perdi o premio! foi por pouco!", "e9d528");
                break;
        }
        sleep(5);

        $id = $this->db->querySingle("select steamid from players where steamid not in (select steamid from server_admins) and lastseen > strftime('%s', 'now')-60 order by random() limit 1");
        $nick = $this->steamid2nick($id);

        $g = $gifts[mt_rand(0, count($gifts) - 1)];

        $this->message("Parabens [00ff00]" . $nick . "[e9d528] !! Voce foi o ganhador do premio " . $g[0] . "!", "e9d528");

        $this->server_command("give " . $id . " " . $g[1]);

        sleep(3);

        $this->message("Junte logo do chao ou vai sumir e perder!", "e9d528");
    }

    public function topvoters() {
        
    }

    public function cmdcall() {
        //if($this->cmdseq==0)
        //    $this->message("7DTD-zBot inicializado :)", "ffff00");

        if ($this->cmdseq == 1)
            $this->check_stacks();

        // update play times
        if ($this->cmdseq == 0)
            $this->server_command("listknownplayers");

        // TODO: debuff active admins
        ///if($this->cmdseq%10==0)
        //    $this->admin_debuff();
        // server rankings
        if ($this->cmdseq % 180 == 0 && $this->cmdseq > 10)
            $this->server_rankings();

        // server gifts
        if ($this->cmdseq % 414000 == 0 && $this->cmdseq > 10)
            $this->daily_gift();

        // bloodmoon party messages
        if ($this->cmdseq % 90 == 0 && $this->cmdseq > 10) {
            if (($this->next_bloodmoon - $this->server_day) == 1)
                $this->message("Amanha eh a proxima BLOODMOON!", "e9d528");
            else
            if ($this->next_bloodmoon == $this->server_day)
                $this->message("HOJE eh BLOODMOON!! Voce pode ser o azarado da noite a receber a mega-horda!!!", "e9d528");
        }
        else
        // random server messages
        if ($this->cmdseq % 30 == 0 && $this->cmdseq > 1)
            $this->server_message();

        if ($this->bloodmon_active) {
            if ($this->cmdseq % 5 == 0)
                $this->party_zombie_spawn();
        }

        if ($this->block_collapse >= 50)
            $this->message("Desmoronamento grande detectado, causa um grande lag no servidor.", "ff0000");

        $this->cmdseq++;
        $this->block_collapse = 0;

        if ($this->reboot_timer !== false) {
            if ($this->reboot_timer == 300)
                $this->message("Servidor reiniciando em menos de 5 minutos...", "00ff00");
            if ($this->reboot_timer == 240)
                $this->message("Servidor reiniciando em menos de 4 minutos...", "00ff00");
            if ($this->reboot_timer == 180)
                $this->message("Servidor reiniciando em menos de 3 minutos...", "00ff00");
            if ($this->reboot_timer == 120)
                $this->message("Servidor reiniciando em menos de 2 minutos...", "ffff00");
            if ($this->reboot_timer == 60)
                $this->message("Servidor reiniciando em menos de 1 minuto...", "ffff00");

            if ($this->reboot_timer < 60 && $this->reboot_timer > 15)
                $this->message("Servidor reiniciando em " . $this->reboot_timer . " segundos...", "ff0000");

            if ($this->reboot_timer < 15) {
                for ($x = 15; $x > 0; $x--) {
                    $this->message($x, "ff0000");
                    sleep(1);
                }

                $this->server_command("saveworld");
                $this->server_command("kickall \"Restart do servidor!\"");
                $this->server_command("shutdown");

                sleep(60);

                $this->reboot_timer = 18000; // 5 hours
                $this->reconnect();
            }

            $this->reboot_timer-=5;
        }
    }

    public function parse_response() {
        // login prompt
        if (!strcmp($this->line, 'Please enter password:')) {
            fputs($this->socket, $this->pass . "\r\n");
        } else {
            // common single line server output (using spaces)
            $arg = str_replace($this->utokens, '', $this->line);
            $arg = explode(' ', str_replace(',', ' ', $arg));

            if (substr($arg[0], 0, 8) == 'Total of')
                $this->pending_event = false;

            // commands without timestamp
            switch ($arg[0]) {
                case 'Day': {
                        $day = (int) $arg[1];

                        $this->next_bloodmoon = $day;

                        // bloodmoon is on every 7 divisible days
                        while ($this->next_bloodmoon % 7 != 0)
                            $this->next_bloodmoon++;

                        $this->server_day = $day;
                        return;
                    }

                default:
                    break;
            }

            // command has timestamp on start
            switch ($arg[3]) {
                case 'Chat': {
                        // scan player name and player chat
                        $line = preg_split("/'([^']*)':/i", $this->line, null, 2);
                        $line = array_map('trim', $line);

                        // ignore server side generated messages
                        if ($line[1] == "Server")
                            return;

                        // check for admin commands
                        if ($this->admin_cmds($line))
                            return;

                        // check for bot commands
                        if ($this->bot_cmds($line))
                            return;

                        // normal player chat
                        $this->player_chat($line);
                        return;
                    }
                    break;

                case 'Time': {
                        //var_dump(explode(' ', $this->line));
                        return;
                    }
                    break;

                case 'Sunset': {
                        $this->message("Comecou a bloodmoon! Sorteando um sortudo, digo azarado a receber a mega horda!", "e9d528");
                        $this->bloodmon_active = true;

                        sleep(5);

                        $id = $this->db->querySingle("select steamid from players where steamid not in (select steamid from server_admins) and level>30 and lastseen > strftime('%s', 'now')-60 order by random() limit 1");
                        $nick = $this->steamid2nick($id);

                        $this->message("E o azarado da noite.... vai ser.... [00ff00]" . $nick . "[e9d528] !! Parabens, uma big horda te espera!!", "e9d528");

                        $this->server_command("sme " . $id . " 50 @ 2 3 2 3 2 3 2 3 2 3 2 3 2 3 2");
                        $this->server_command("sme " . $id . " 30 @ 39 40 41 39 40 41 39 40 41 39 16 16 16 16 16");
                        $this->server_command("sme " . $id . " 50 @ 32 32 32 32 32 32 32 32 32 32");
                        $this->server_command("sh " . $id . " 99");

                        return;
                    }
                    break;

                case 'Sunrise': {
                        $this->message("ACABOU A BLOODMOON! CONTANDOS OS CORPOS.... (ou nao...)", "e9d528");
                        $this->bloodmon_active = false;
                        return;
                    }
                    break;

                case 'Player': {
                        $this->player_login($arg);
                        return;
                    }
                    break;

                case 'Kicking': {
                        if ($arg[5] == 'Banned')
                            $this->message("Tentativa de login no server: [ff0000]" . $arg[count($arg) - 1] . " [ffff00]Este player está BANIDO do servidor!", "ffff00");
                        return;
                    }
                    break;

                default:
                    //print_r($arg);
                    break;
            }

            // multiple lines splitted by comma (sequence of) output
            $arg = str_replace($this->utokens, '', $this->line);
            $arg = explode(',', $arg);
            $arg = array_map('trim', $arg);

            // player inventory tracker (slow routine)
            if (substr($arg[0], 0, 12) == 'tracker_item') {
                $this->track_inventory($arg);
                $this->check_invalid($arg);

                // inventory scan ended
                if ($arg[2] == 'SHOWINVENTORY DONE')
                    $this->pending_event = false;

                return;
            }

            switch (count($arg)) {
                // player info lines
                case 18:
                    $this->track_wipe($arg);
                    $this->track_bloodmoon($arg);
                    $this->track_playerpos($arg);
                    return;

                // entity lists
                case 14:
                    $this->track_entity($arg);
                    return;

                // track player playtime
                case 7:
                    $this->track_playtime($arg);
                    return;

                default:
                    if (strstr($arg[1], 'EntityFallingBlock'))
                        $this->block_collapse++;

                    print($this->line . "\n");
                    return;
            }
        }
    }

    public function reconnect() {
        fclose($this->socket);

        sleep(1);

        // try reconnect to server
        $this->socket = fsockopen($this->host, $this->port);

        stream_set_blocking($this->socket, false);
    }

    public function fetch_data() {
        usleep(1000);

        if (feof($this->socket))
            $this->reconnect();

        $this->line = trim(fgets($this->socket, 1024));
        if (empty($this->line))
            return false;

        return true;
    }

}

$x = new ServerAdm();

for (;;) {
    if ($x->fetch_data())
        $x->parse_response();

    $x->run_events();
}
