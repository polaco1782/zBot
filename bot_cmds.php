<?php

Trait BotCmds {

    // generic bot commands, non admin
    public function bot_cmds($arg) {
        $id = $this->nick2steamid($arg[1]);

        if ($arg[2] == '/ajuda' || $arg[2] == '/help') {
            $help = array('/sethome' => 'seta o local atual como a sua home',
                '/home' => 'teleporta voce para sua home',
                '/x' => 'Mostra quem esteve ao redor da sua posicao 50x50 blocks',
                '/day7' => 'Mostra o dia da proxima bloodmoon',
                '/kit' => 'Receba o kit inicial, disponivel apenas uma vez!'
            );

            $this->message_private("Comandos disponiveis no servidor:", $id, "00ff00");

            foreach ($help as $h => $hh)
                $this->message_private("[ffff00] " . $h . "[ff00ff] " . $hh, $id);

            $this->message_private("O Bot entende algumas palavras do chat, como lua, base, raid, etc..", $id, "00ff00");
            $this->message_private("Ah, e nao xingue o server, ele nao gosta disso e pode descontar a raiva em voce!", $id, "00ff00");

            return true;
        } else
        if ($arg[2] == '/evento') {
            if ($this->bloodmon_active) {
                // select a random spot to spawn zombie
                $coords = $this->db->querySingle("select * from waypoints where type='bloodmoon' order by random() limit 1", true);

                if ($coords) {
                    //$this->message("Player ".$arg[1]." se teleportou para /evento !", "e9d528");
                    $this->server_command(sprintf('tele %d %d %d %d', $id, round($coords['pos_x']), round($coords['pos_z']), round($coords['pos_y'])));
                }
            } else
                $this->message_private("Evento bloodmoon não está ativo!!", $id, "00ff00");

            return true;
        }
        else
        if ($arg[2] == '/kit') {
            $kit = $this->db->querySingle("select steamid from player_kit where steamid='" . $id . "'", false);

            if ($kit) {
                $this->message_private("O comando /kit só pode ser usado apenas uma vez!!", $id, "00ffff");
                return true;
            }

            $this->message_private("Voce recebeu o kit starter!! Segure E apertado, os items estão no chão nos seus pés!! Pegue logo!!", $id, "00ffff");

            $this->server_command("give " . $id . " pickaxeIron 1 200");
            $this->server_command("give " . $id . " shovelIron 1 200");
            $this->server_command("give " . $id . " fireaxeIron 1 200");
            $this->server_command("give " . $id . " keystoneBlock 1");
            $this->server_command("give " . $id . " woodenBow 1 200");
            $this->server_command("give " . $id . " arrow 64");
            $this->server_command("give " . $id . " firstAidKit 3");
            $this->server_command("give " . $id . " canBeef 3");
            $this->server_command("give " . $id . " bottledWater 3");

            $stmt = $this->db->prepare("insert into player_kit(steamid) values(?)");

            $stmt->reset();
            $stmt->bindValue(1, $id, SQLITE3_TEXT);
            $stmt->execute();

            return true;
        } else
        if ($arg[2] == '/sethome') {
            $coords = $this->player_coords($arg[1]);

            // quadratic scan area, ignore admins and player steamID
            $stmt = $this->db->prepare('select count(pt.steamid) as total from player_track pt ' .
                    'inner join players p on p.steamid = pt.steamid ' .
                    'where (pt.pos_x between ? and ?) and (pt.pos_y between ? and ?) ' .
                    'and (strftime(\'%s\', \'now\')-pt.stamp) < 900 ' .
                    'and pt.steamid not in (select steamid from server_admins) ' .
                    'and pt.steamid <> ? ');

            $stmt->reset();
            $stmt->bindValue(1, (float) $coords['lastpos_x'] - 250.0, SQLITE3_FLOAT);
            $stmt->bindValue(2, (float) $coords['lastpos_x'] + 250.0, SQLITE3_FLOAT);
            $stmt->bindValue(3, (float) $coords['lastpos_y'] - 250.0, SQLITE3_FLOAT);
            $stmt->bindValue(4, (float) $coords['lastpos_y'] + 250.0, SQLITE3_FLOAT);
            $stmt->bindValue(5, $id, SQLITE3_TEXT);
            $result = $stmt->execute();

            $res = $result->fetchArray();

            if ((int) $res[0] > 0) {
                $this->message_private("Voce nao pode definir o /home perto de inimigos!", $id, "ff0000");
                return true;
            }

            $stmt = $this->db->prepare('replace into player_homes(steamid,pos_x,pos_y,pos_z) values(?,?,?,?)');

            $stmt->reset();
            $stmt->bindValue(1, $id, SQLITE3_TEXT);
            $stmt->bindValue(2, (float) $coords['lastpos_x'], SQLITE3_FLOAT);
            $stmt->bindValue(3, (float) $coords['lastpos_y'], SQLITE3_FLOAT);
            $stmt->bindValue(4, (float) $coords['lastpos_z'], SQLITE3_FLOAT);
            $stmt->execute();

            $this->message_private("Sua /home foi definida!", $id, "00ff00");

            return true;
        } else
        if ($arg[2] == '/home') {
            // disable teleports on bloodmoon day
            if (($this->next_bloodmoon == $this->server_day) || $this->bloodmon_active) {
                switch (rand(0, 6)) {
                    case 0: $msg = "Humm tem algo de errado com o tel... AAGHGHH BLAM PAFT! aajuuuud....";
                        break;
                    case 1: $msg = "Vivo informa, nao foi possivel completar o seu teleport.";
                        break;
                    case 2: $msg = "O teleport quebrou, vai ter que voltar na pernada!";
                        break;
                    case 3: $msg = "Teleporter desativado no dia bloodmoon!";
                        break;
                    case 4: $msg = "Opa, sem teleport hj malandro";
                        break;
                    case 5: $msg = "O teleporter foi sabotado, cortaram o fio";
                        break;
                    case 5: $msg = "Ewww! Que nojo! tem meleca de zumbi no teleporter!";
                        break;
                    default: break;
                }

                // zombie surprise!
                if (rand(0, 10) == 5) {
                    $this->message_private("Os zumbis notaram a tentativa de teleport e estão a sua procura!", $id, "ff0000");
                    $this->server_command("sh " . $id . " 30");
                }

                if ($this->bloodmon_active)
                    $msg = "Ce ta de brincadeira?! Teleport no meio da bloodmoon??";

                $this->message_private($msg, $id, "ffff00");

                return true;
            }
            else {
                $coords = $this->db->querySingle("select * from player_homes where steamid='" . $id . "'", true);

                if (!$coords) {
                    $this->message_private("Voce nao tem um /sethome definido ainda!", $id, "ffff00");
                    return true;
                }

                if (time() > (int) $coords["lastused"] + (60 * 15)) {
                    $this->db->querySingle("update player_homes set lastused=strftime('%s', 'now') where steamid='" . $id . "'");

                    $this->sched_commands[$this->cmdseq + 1] = sprintf('tele %s %d %d %d', $id, $coords['pos_x'], $coords['pos_z'], $coords['pos_y']);
                    $this->message_private("Aguarde, logo voce será teleportado para a sua /home!", $id, "00ff00");
                } else {
                    $this->message_private("O comando /home so pode ser usado uma vez a 15 minutos", $id, "00ffff");
                }

                return true;
            }
        } else
        if ($arg[2] == '/x') {
            $coords = $this->player_coords($arg[1]);

            // quadratic scan area, ignore admins and player steamID
            $stmt = $this->db->prepare('select p.nick, count(p.steamid) from player_track pt ' .
                    'inner join players p on p.steamid = pt.steamid ' .
                    'where (pt.pos_x between ? and ?) and (pt.pos_y between ? and ?) ' .
                    'and (strftime(\'%s\', \'now\')-pt.stamp) < 86400*3 ' .
                    'and pt.steamid not in (select steamid from server_admins) ' .
                    'and pt.steamid <> ? ' .
                    'group by p.nick order by 2 desc');

            $stmt->reset();
            $stmt->bindValue(1, (float) $coords['lastpos_x'] - 50.0, SQLITE3_FLOAT);
            $stmt->bindValue(2, (float) $coords['lastpos_x'] + 50.0, SQLITE3_FLOAT);
            $stmt->bindValue(3, (float) $coords['lastpos_y'] - 50.0, SQLITE3_FLOAT);
            $stmt->bindValue(4, (float) $coords['lastpos_y'] + 50.0, SQLITE3_FLOAT);
            $stmt->bindValue(5, $id, SQLITE3_TEXT);
            $result = $stmt->execute();

            $this->message_private("Jogadores que estiveram neste local, 50x50 blocks, nos ultimos 3 dias", $id, "00ff00");

            $res = $result->fetchArray();
            if ($res)
                $this->message_private($res[1] . ": " . $res[0], $id, "00ffff");
            else
                $this->message_private("Nenhum resultado", $id, "00ff00");

            while ($res = $result->fetchArray())
                $this->message_private($res[1] . ": " . $res[0], $id, "ffff00");

            return true;
        }
        else
        if ($arg[2] == '/testez') {
            // $pos_x = $this->trader_list[0]['pos_x'];
            // $pos_y = $this->trader_list[0]['pos_y'];
            // $pos_z = $this->trader_list[0]['pos_z'];
            // $cmd = sprintf('tele %s %d %d %d', $id, $pos_x, $pos_z, $pos_y);
            // $this->server_command($cmd);
            $this->cmdseq = 414000;

            return true;
        } else
        if ($arg[2] == '/day7') {
            if ($this->next_bloodmoon == $this->server_day)
                $this->message("A bloodmoon eh essa noite!! se prepare!!", "e9d528");
            else
                $this->message("A proxima bloodmoon eh no dia " . $this->next_bloodmoon . "!", "e9d528");

            return true;
        }
        else
        if ($arg[2][0] == '/') {
            $this->message_private("Comando desconhecido! Digite /ajuda para ver os comandos disponiveis!", $id, "ffff00");

            return true;
        }

        //
        // chat text scanning, bot scans and replies messages
        //

        // test for chat words related to bloodmoon
        $needles = array('bloodmoon', 'blodmon', 'bloodmon', 'lua', 'sangue', 'sangrenta', '7th', 'day7',
            'menstruada', 'blood', 'blod', 'moon', 'luasangrenta', 'vermelha', 'laranja', 'horda');

        foreach ($needles as $needle) {
            if (strpos(strtolower($arg[2]), $needle) !== false) {
                if ($this->next_bloodmoon == $this->server_day)
                    $this->message("A bloodmoon eh essa noite!! se prepare!!", "e9d528");
                else
                    $this->message("A proxima bloodmoon eh no dia " . $this->next_bloodmoon . "!", "e9d528");
                return true;
            }
        }

        // test for chat words related to raid
        $needles = array('raid', 'atacada', 'base', 'invadida', 'invadiram', 'roubaram', 'roubada', 'sabota');

        foreach ($needles as $needle) {
            if (strpos(strtolower($arg[2]), $needle) !== false) {
                $this->message("Sua base foi roubada? Veja quem esteve no local usando o comando [00ff00]/x[e9d528] no chat!", "e9d528");
                return true;
            }
        }

        // test for bad words
        $needles = array('server lixo', 'sv lixo', 'serve lixo', 'lixo de server', 'servidor lixo', 'servidor bugado', 'merda de server', 'mierda de serv');

        foreach ($needles as $needle) {
            if (strpos(strtolower($arg[2]), $needle) !== false) {
                $coords = $this->player_coords($arg[1]);

                $this->message("O que?? me chamou de lixo?! Seu NOOB vc vai pagar caro por isso!", "e9d528");
                $this->server_command("buffplayer " . $id . " stunned");
                $this->server_command("buffplayer " . $id . " brokenLeg");
                $this->server_command("buffplayer " . $id . " bleeding");
                $this->server_command("buffplayer " . $id . " burning");
                $this->server_command("buffplayer " . $id . " internalBleeding");
                $this->server_command("buffplayer " . $id . " infection3");
                $this->server_command("buffplayer " . $id . " dysentery2");
                $this->server_command("buffplayer " . $id . " foodPoisoning");
                $this->server_command("buffplayer " . $id . " AlcoholPoisoning");

                $this->server_command(sprintf('tele %s %d %d %d', $id, $coords['lastpos_x'], 300, $coords['lastpos_y']));

                return true;
            }
        }

        // random bot gag
        if ((strtolower($arg[2]) == 'server' || strtolower($arg[2]) == 'bot') && count($arg) < 4) {
            switch (rand(0, 5)) {
                case 0: $msg = "ã? o que foi? onde? quem?";
                    break;
                case 1: $msg = "Nao foi eu. Juro.";
                    break;
                case 2: $msg = "To aqui.";
                    break;
                case 3: $msg = "Estou a sua disposicao mestre.";
                    break;
                case 4: $msg = "No momento o bot encontra-se ocupado, por favor deixa sua mensagem. beeeeep.";
                    break;
                case 5: $msg = "Bzzzzzzzzzt!";
                    break;
                default: break;
            }

            $this->message("BOT: " . $msg, "e9d528");

            return true;
        }

        // not a bot command or needed to log
        return false;
    }

}
