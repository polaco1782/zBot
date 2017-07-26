<?php

Trait AdminCmds {

    public function admin_cmds($arg) {
        // only admins allowed
        $id = $this->nick2steamid($arg[1]);
        if (!$this->is_admin($id))
            return false;

        // split command parameters
        $param = explode(' ', $arg[2]);

        if (count($param) >= 2) {
            if ($param[0] == '/ban') {
                $data = $this->db->querySingle("select * from players where nick like '%" . $param[1] . "%'", true);

                if ($data) {
                    $this->message('Player [00ff00]' . $data["nick"] . '[ff0000] foi banido do servidor', 'ff0000');
                    $this->ban($data["steamid"]);
                } else {
                    $this->message_private("Nick " . $param[1] . " nao encontrado no banco de dados!", $id, "ffff00");
                }

                return true;
            } else
            if ($param[0] == '/reboot') {
                $this->reboot_timer = ((int) $param[1]) * 60;
                $this->message('Reboot do servidor agendado para ' . ($this->reboot_timer / 60) . ' minuto(s)', 'ff0000');

                return true;
            } else
            if ($param[0] == '/waypoint') {
                $coords = $this->player_coords($arg[1]);
                $stmt = $this->db->prepare('insert into waypoints(ownerid,pos_x,pos_y,pos_z,public,type,name) values(?,?,?,?,?,?,?)');

                $stmt->reset();
                $stmt->bindValue(1, $id, SQLITE3_TEXT);
                $stmt->bindValue(2, (float) $coords['lastpos_x'], SQLITE3_FLOAT);
                $stmt->bindValue(3, (float) $coords['lastpos_y'], SQLITE3_FLOAT);
                $stmt->bindValue(4, (float) $coords['lastpos_z'], SQLITE3_FLOAT);
                $stmt->bindValue(5, "false", SQLITE3_TEXT);
                $stmt->bindValue(6, "teleport", SQLITE3_TEXT);
                $stmt->bindValue(7, $param[1], SQLITE3_TEXT);
                $stmt->execute();

                $this->message_private("Waypoint " . $param[1] . " adicionado", $id, "00ff00");
            } else
            if ($param[0] == '/bwaypoint') {
                $coords = $this->player_coords($arg[1]);
                $stmt = $this->db->prepare('insert into waypoints(ownerid,pos_x,pos_y,pos_z,public,type,name) values(?,?,?,?,?,?,?)');

                $stmt->reset();
                $stmt->bindValue(1, $id, SQLITE3_TEXT);
                $stmt->bindValue(2, (float) $coords['lastpos_x'], SQLITE3_FLOAT);
                $stmt->bindValue(3, (float) $coords['lastpos_y'], SQLITE3_FLOAT);
                $stmt->bindValue(4, (float) $coords['lastpos_z'], SQLITE3_FLOAT);
                $stmt->bindValue(5, "false", SQLITE3_TEXT);

                if ($param[1] == 'start') {
                    $stmt->bindValue(6, "bloodmoon_startpos", SQLITE3_TEXT);
                    $stmt->bindValue(7, $param[1], SQLITE3_TEXT);

                    $this->message_private("Inicio do terreno bloodmoon adicionado!", $id, "00ff00");
                } else
                if ($param[1] == 'end') {
                    $stmt->bindValue(6, "bloodmoon_endpos", SQLITE3_TEXT);
                    $stmt->bindValue(7, $param[1], SQLITE3_TEXT);

                    $this->message_private("Final do terreno bloodmoon adicionado!", $id, "00ff00");
                } else {
                    $stmt->bindValue(6, "bloodmoon", SQLITE3_TEXT);
                    $stmt->bindValue(7, $param[1], SQLITE3_TEXT);

                    $this->message_private("Waypoint bloodmoon " . $param[1] . " adicionado", $id, "00ff00");
                }

                $stmt->execute();
            }
        } else {
            if ($param[0] == '/check') {
                $this->check_stacks();

                return true;
            }
        }

        return false;
    }

}
