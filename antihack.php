<?php

Trait AntiHack {

    private $playerlevel_threshold = 10;
    // item stack probabilty
    private $istack_limit = 10000;
    private $istack_high;
    private $istack_med;
    private $istack_low;

    public function antihack_load() {
        $this->iquality_high = array('gun44Magnum', 'gunAK47', 'gunHuntingRifle',
            'gunMP5', 'gunPistol', 'gunPowder',
            'gunPumpShotgun', 'gunRocketLauncher',
            'gunSawedOffPumpShotgun', 'gunSniperRifle');

        // high dupe/hacking stack probabilty
        $this->istack_high = array('10mmBullet', '44MagBullet', '762mmBullet',
            '9mmBullet', 'shotgunShell', 'shotgunSlug',
            'rocket', 'forgedIron', 'forgedSteel',
            'firstAidKit', 'repairKit', 'antibiotics',
            'vitamins', 'meatStew');

        $this->istack_low = array('mechanicalParts', 'electricParts', 'electronicParts', 'scrapBrass', 'steelCrossbowBolt');
    }

    public function check_stack_high($res) {
        // ----- high dupe/hack probability stack ------
        if (in_array($res['itemtype'], $this->istack_high)) {
            // low level and big stack
            if ($res['level'] < $this->playerlevel_threshold || $res['playtime'] < 720) {
                // MAX stack size with low level, automatic ban
                if ($res['quantity'] == $this->istack_limit) {
//                    $this->ban($res['steamid'], "STACK LIMITE ".$this->istack_limit." INCOMPATIVEL COM O LEVEL!");
//                    $this->message("Player [00ff00]".$res['nick']."[ffffff] foi banido do servidor por ter um stack de ".$this->istack_limit." ".$res['itemtype']." incompativel com seu level/playtime.");

                    $this->message_private("ATENCAO[stacklimit]: player " . $res['nick'] . ', level ' . $res['level'] . ' possui um stack de ' .
                            $res['quantity'] . ' ' . $res['itemtype'], '76561198041518288', "ff0000");
                }
            } else
            if ($res['level'] < 50) {
                // half max stack size
                if ($res['quantity'] > ($this->istack_limit / 2))
                    $this->message_private("ATENCAO[high-100]: player " . $res['nick'] . ', level ' . $res['level'] . ' possui um stack de ' .
                            $res['quantity'] . ' ' . $res['itemtype'], '76561198041518288', "ff0000");
            }
            else
            if ($res['level'] < 100) {
                if ($res['quantity'] >= $this->istack_limit)
                    $this->message_private("ATENCAO[high-150]: player " . $res['nick'] . ', level ' . $res['level'] . ' possui um stack de ' .
                            $res['quantity'] . ' ' . $res['itemtype'], '76561198041518288', "ff0000");
            }
            else {
                // dont bother about high level players                        
            }
        }
    }

    public function check_stack_low() {
        // low dupe/hack probability stack, but keep an eye on it
        if (in_array($res['itemtype'], $this->istack_low)) {
            // low level and big stack
            if ($res['level'] < 10 && $res['quantity'] > ($this->istack_limit / 4)) {
                $this->message_private("ATENCAO[low]: player " . $res['nick'] . ', level ' . $res['level'] . ' possui um stack de ' .
                        $res['quantity'] . ' ' . $res['itemtype'], '76561198041518288', "00ff00");
            }
        }
    }

    public function check_stacks() {
        // scan and group with high stacks, order by higher stack value
        $sql = "select p.steamid,p.nick,p.level,p.playtime,it.itemtype,max(it.quantity) as quantity " .
                "from inventory_track it inner join players p on it.steamid=p.steamid " .
                "where (strftime('%s', 'now')-it.stamp) < 86400*2 " .
                "and quantity>200 group by p.steamid,it.itemtype";

        $stmt = $this->db->prepare($sql);

        $stmt->reset();
        $result = $stmt->execute();

        if ($result) {
            while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
                // check for high stacks
                $this->check_stack_high($res);
                $this->check_stack_low($res);
            }
        }



        //$stmt = select * from inventory_track where itemtype="gunSniperRifle" and quality>400;
        //steamid,itemtype,quantity,quality
//        if($arg[4]=='rocket' && $arg[5]>4500)
//        {
//            $player = $this->steamid2nick($arg[1]);
//            $this->message("ATENCAO: [00ff00]".$player."[ff0000] possui um stack de ".$arg[5]." ".$arg[4]." incompativel com seu tempo de jogo!", "ff0000");
//        }
//
//        if($arg[4]=='gunRocketLauncher' && $arg[6]==600)
//        {
//            $player = $this->steamid2nick($arg[1]);
//            $this->message("ATENCAO: [00ff00]".$player."[ff0000] possui item ".$arg[4]." qualidade ".$arg[6]." incompativel com seu tempo de jogo!", "ff0000");
//        }
//        if($arg[7]) var_dump($arg[7]);
//        if($arg[8]) var_dump($arg[8]);
//        if($arg[9]) var_dump($arg[9]);
//        if($arg[10]) var_dump($arg[10]);
        /*
         * Array
          (
          [0] => tracker_item456483
          [1] => tag=76561197962750436
          [2] => location=equipment
          [3] => slot=gloves
          [4] => item=plantFiberGloves
          [5] => qnty=1
          [6] => quality=8
          [7] => parts=(
          )
         */
    }

    public function check_invalid($arg) {
        // do not track admin inventory
        if ($this->is_admin($arg[1]))
            return;

        $invalid = array(// items
            'clubMaster', 'handMaster', 'handPlayer', 'handZombie', 'handZombie02', 'handBurningZombie',
            'handCopZombie', 'vomitZombieProjectile', 'stingHornet', 'handZombieDog', 'handStrongZombie',
            'handBear', 'zombieHandBear', 'handZombieHazMat', 'handZombieWorker', 'handZombieFootballPlayer',
            'handZombieStripper', 'shapedCharge', 'flare', 'partsMaster', 'gasMask', 'cigarette', 'backpackMedium',
            'sunblocker', 'redPill', 'yuccaCocktail', 'schematicMaster', 'invisibleRecipesForRepairCost',
            'leatherTanning', 'questMaster', 'challengeQuestMaster', 'treasureQuestMaster', 'trophy3', 'unit_iron',
            'unit_brass', 'unit_lead', 'unit_glass', 'unit_stone', 'unit_clay', 'Admin Tool', 'Admin Pill', 'Admin Machete',
            // blocks
            'bedrock', 'fertileDirt', 'fertileGrass',
            'terrainFiller', 'grassFromDirt', 'plainsGroundFromDirt', 'plainsGroundWGrass1', 'plainsGroundWGrass2',
            'burntForestGroundFromDirt', 'burntForestGroundWGrass1', 'burntForestGroundWGrass2', 'forestGroundFromDirt',
            'forestGroundWGrass1', 'forestGroundWGrass2', 'clayInSandstone', 'lootStone', 'cropsGrowingMaster',
            'cropsHarvestableMaster', 'stainlessSteelMaster', 'scrapIronNoUpgradeMaster', 'cobblestoneFrameMaster',
            'cobblestoneMaster', 'woodWeakNoUpgradeMaster', 'woodNoUpgradeMaster', 'redWoodMaster', 'treeMaster',
            'treeMasterGrowing', 'metalNoUpgradeMaster', 'stoneToAdobeMaster', 'concreteNoUpgradeMaster', 'steelMaster',
            'rConcreteMaster', 'pouredConcreteMaster', 'concreteFormMaster', 'brickNoUpgradeMaster', 'brickMaster',
            'adobeMaster', 'concreteMaster', 'rScrapIronMaster', 'rebarFrameMaster', 'stoneMaster', 'scrapIronMaster',
            'scrapIronFrameMaster', 'rWoodMetalMaster', 'rWoodMaster', 'woodMaster', 'woodFrameMaster',
            'pouredConcreteCNRRamp', 'pouredConcreteCNRFull', 'pouredConcreteEighth', 'pouredConcreteHalf',
            'pouredConcretePole', 'pouredConcretePyramid', 'pouredConcreteQuarter', 'pouredConcreteStairs25',
            'pouredConcreteWedge', 'pouredConcreteBlock', 'pouredConcreteWedgeTip', 'pouredConcretePlate',
            'pouredConcreteSupport', 'pouredConcreteRamp', 'pouredConcretePillar100', 'pouredConcreteCTRPlate',
            'pouredConcretePillar50', 'plantedBlueberry1', 'plantedPotato1', 'plantedPotato2', 'plantedBlueberry2',
            'lootWasteland', 'lootDesert', 'lootBurntForest', 'lootPlains', 'animalGore', 'cntStorageHealthInsecure',
            'decals1', 'redMetalBlockDuplicate', 'lootMP', 'shapedChargeBlockTest', 'signRoadArrowheadApache',
            'signRoadAZ260eastSpeed65', 'signRoadAZ260west', 'signRoadAZ260westSpeed65', 'signRoadAZ73north',
            'signRoadAZ73northSpeed65', 'signRoadAZ73south', 'signRoadAZ73southSpeed65', 'signRoadDestinationsEast',
            'signRoadDestinationsWest', 'signRoadApacheAZ260', 'signRoadBellLake', 'signRoadCoronado',
            'signRoadCoronadoCourtland', 'signRoadCourtlandApache', 'signRoadCourtlandAZ260', 'signRoadCourtlandBell',
            'signRoadCourtlandHuenink', 'signRoadCourtlandMaple', 'signRoadCourtlandTran', 'signRoadDavis',
            'signRoadEssig', 'signRoadLangTran', 'signRoadTran', 'signRoadCourtlandAZ260Duplicate', 'signAnselAdamsRiver',
            'signRoadMaple', 'plantedHop1', 'plantedHop2', 'plantedMushroom2', 'plantedYucca1', 'plantedYucca2',
            'pouredConcreteCNRRound', 'pouredConcreteCNRRoundTop', 'cntBackpackDropped', 'rockResource', 'rockResource02',
            'rockResourceBroke1', 'rockResourceBroke2', 'rockResource02Broke1', 'rockResource02Broke2',
            'rockResource02Broke3', 'treePlantedMountainPine1m', 'treePlantedMountainPine6m', 'treePlantedMountainPine8m',
            'treePlantedMountainPine13m', 'treePlantedMountainPine16m', 'treePlantedMountainPine19m',
            'treePlantedMaple1m', 'treePlantedMaple6m', 'treePlantedMaple13m', 'treePlantedMaple15m',
            'treePlantedMaple17m', 'GoreBlock1Prefab', 'GoreBlock1BonesPrefab', 'plantedCotton1',
            'plantedCotton3HarvestLargeLegacy', 'treePlantedMaple17mPlus', 'plantedCoffee1', 'plantedCoffee2',
            'plantedGoldenrod1', 'plantedGoldenrod2', 'plantedGoldenrod3HarvestLargeLegacy', 'waterMovingBucket',
            'waterStaticBucket', 'waterMoving', 'water', 'plantedAloe1', 'plantedAloe2', 'plantedChrysanthemum1',
            'plantedChrysanthemum2', 'lootForest', 'lootHouse', 'lootStreet', 'lootYard', 'lootGarage', 'plantedCorn1',
            'plantedCorn2', 'treePlantedGrass', 'decals2', 'decals3', 'corrugatedMetalSheetDuplicate',
            'pouredConcreteArrowSlitHalf', 'woodAsphaltShinglesRampDuplicate', 'dotsAsphaltShinglesRampDuplicate',
            'dotsGreenShinglesRampDuplicate2', 'corrugatedMetalBlockDuplicate', 'woodCNRInsideDuplicate',
            'plantedCotton2', 'commercialDoor3_v1Legacy', 'commercialDoor3_v2Legacy', 'commercialDoor3_v3Legacy',
            'window03', 'cobblestoneBlockHalf', 'cobblestoneBlockThreeQuarters', 'cobblestoneBlockQuarter',
            'candleTable', 'cobblestoneFrameRampQuarter', 'cobblestoneFrameRampHalf', 'cntStorageAmmoInsecure',
            'cntStorageBuildingInsecure', 'cntStorageExplosivesInsecure', 'cntStorageFoodInsecure',
            'cntStorageWeaponsInsecure', 'snowstorm1', 'sandstorm', 'smokestorm', 'artcube', 'treeHauntedTreeWasteland42',
            'cntDeskSafeInsecure', 'cntWallSafeInsecure', 'cntGunSafeInsecure', 'spotlightNailedDownLegacy',
            'shapeTestRamp', 'shapeTestWedge', 'shapeTestWedgeTip', 'shapeTestCap', 'pouredConcreteCNRInside',
            'treePlantedWinterPine1m', 'treePlantedWinterPine6m', 'treePlantedWinterPine13m', 'treePlantedWinterPine16m',
            'treePlantedWinterPine19m', 'elevatorTest', 'rustyIronPillar100Duplicate', 'whiteMetalBlockDuplicate',
            'greenRustyMetalWallBlockDuplicate', 'treePlantedWinterPine19mPlus', 'flagPoleWhiteRiver', 'loudspeaker');

        $i = array_search($arg[4], $invalid);

        // found an invalid item
        if ($i !== false) {
            $player = $this->steamid2nick($arg[1]);

//            $this->message("ATENCAO:[ffff00] Player ".$player." foi banido por ter item invalido no seu inventario! item=".$i, "ff0000");
//            $this->ban($arg[1], "HACK/ITEM DE ADMIN NO INVENTARIO");
//            $this->message_private("ATENCAO: player ".$arg[1].' possui item de adm no inventario: '.$invalid[$i], 76561198041518288, "ff0000");
        }
    }

    public function check_teleports() {
        
    }

}
