<?php

Trait BloodMoonParty {

    public function party_zombie_spawn() {
        // select a random spot to spawn zombie
        $coords = $this->db->querySingle("select * from waypoints where type='bloodmoon' order by random() limit 1", true);

        $radius = 200;
        $zombie = '3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19';

        //if($coords)
        //    $this->server_command(sprintf('sme %d %d %d @ %s', round($coords['pos_x']), round($coords['pos_y']), $radius, $zombie));
    }

    // argument is passed by reference, so we can modify it
    public function track_bloodmoon(&$arg) {
        //7845.6, -5240.5
        //8110.3, -4926.6
        $ax = 7845.6;
        $ay = -5240.5;
        $bx = 8110.3;
        $by = -4926.6;
        $cx = (float) $arg[2];
        $cy = (float) $arg[4];

        // player is in arena range
        if ((($cx > $ax) && ($cx < $bx)) && (($cy > $ay) && ($cy < $by))) {
            if ($this->bloodmon_active == false) {
                $coords = $this->player_coords($arg[1]);
                $id = $arg[15];

                if ($this->is_admin($id))
                    return;

                // dont allow player inside bloodmoon arena city
                $this->server_command(sprintf('tele %s %d %d %d', $id, $coords['lastpos_x'], $coords['lastpos_y'], $coords['lastpos_y']));

                // dont overwrite old coordinates, so we can teleport player back
                $arg[2] = $coords['lastpos_x'];
                $arg[3] = $coords['lastpos_z'];
                $arg[4] = $coords['lastpos_y'];

                $this->message_private("Esta cidade so pode ser frequentada no dia da bloodmoon!", $id, "ffff00");
            }
        }
        else {
            if ($this->bloodmon_active) {
                $id = $arg[15];

                var_dump("Fora da arena bloodmoon!");
                var_dump($arg);

                // dont allow player outside arena on bloodmoon day
                //$this->server_command(sprintf('tele %s %d %d %d', $id, $coords['lastpos_x'], $coords['lastpos_y'], $coords['lastpos_y']));
                //$this->message_private("Voce nao pode sair da arena no dia da bloodmoon!", $id, "ffff00");
            }
        }
    }

}
