<?php

/**
 *  2Moons
 *  Copyright (C) 2012 Jan Kröpke
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package 2Moons
 * @author Jan Kröpke <info@2moons.cc>
 * @copyright 2012 Jan Kröpke <info@2moons.cc>
 * @license http://www.gnu.org/licenses/gpl.html GNU GPLv3 License
 * @version 1.8.0 (2013-03-18)
 * @info $Id$
 * @link http://2moons.cc/
 */

class BuildUtils
{
    const BUILD     = 1;
    const DESTROY   = 2;
    const AMOUNT    = 4;

	public static $bonusList = NULL;

	public static function getBonusList()
	{
        if(is_null(self::$bonusList))
        {
            self::$bonusList = array(
                'Attack',
                'Defensive',
                'Shield',
                'BuildTime',
                'ResearchTime',
                'ShipTime',
                'DefensiveTime',
                'Resource',
                'ResourceStorage',
                'ShipStorage',
                'FlyTime',
                'FleetSlots',
                'Planets',
                'SpyPower',
                'Expedition',
                'GateCoolTime',
                'MoreFound',
            );

            foreach(array_keys(Vars::getElements(Vars::CLASS_RESOURCE, array(Vars::FLAG_RESOURCE_PLANET, Vars::FLAG_ENERGY))) as $elementId)
            {
                self::$bonusList[]  = 'Resource'.$elementId;
            }

        }
		return self::$bonusList;
	}
	
	public static function getRestPrice($USER, $PLANET, $Element, $costResources = NULL)
	{
		if(!isset($costResources))
        {
			$costResources	= self::getElementPrice($USER, $PLANET, $Element);
		}
		
		$overflow	= array();
		
        foreach($costResources as $resourceElementId => $value)
        {
            $resourceElementObj    = Vars::getElement($resourceElementId);
            if(Vars::elementHasFlag($resourceElementObj, Vars::FLAG_RESOURCE_USER))
            {
                $available  = $USER[$resourceElementObj->name];
            }
            else
            {
                $available  = $PLANET[$resourceElementObj->name];
            }

			$overflow[$resourceElementId] = max($value - $available, 0);
		}

		return $overflow;
	}
	
	public static function getElementPrice(Element $elementObj, $elementLevel, $forDestroy = false)
    {
		$price	= array();
		foreach(Vars::getElements(Vars::CLASS_RESOURCE) as $resourceElementId => $resourceElementObj)
		{
			$value  = $elementObj->cost[$resourceElementId];
			
			if($elementObj->factor != 0 && $elementObj->factor != 1) {
                $value	*= pow($elementObj->factor, $elementLevel);
			}
			
			if($elementObj->class === Vars::CLASS_FLEET || $elementObj->class === Vars::CLASS_DEFENSE || $elementObj->class === Vars::CLASS_MISSILE)
            {
                $value	*= $elementLevel;
			}
			
			if($forDestroy === true)
            {
                $value	= round($value / 2);
			}

            $price[$resourceElementId]	= $value;
		}
		
		return $price; 
	}
	
	public static function requirementsAvailable($USER, $PLANET, Element $elementObj)
	{
		if(empty($elementObj->requirements)) return true;

		foreach($elementObj->requirements as $requireElementId => $requireElementLevel)
		{
            $requireElementObj = Vars::getElement($requireElementId);
            if(Vars::elementHasFlag($requireElementObj, Vars::FLAG_RESOURCE_USER))
            {
                if ($USER[$requireElementObj->name] < $requireElementLevel) return false;
            }
            else
            {
                if ($PLANET[$requireElementObj->name] < $requireElementLevel) return false;
            }

		}

		return true;
	}
	
	public static function getBuildingTime($USER, $PLANET, Element $elementObj, $costResources = NULL, $forDestroy = false, $forLevel = NULL)
	{
		$config	= Config::get($USER['universe']);

        $time   = 0;

        if(!isset($costResources))
        {
			$costResources	= self::getElementPrice($USER, $PLANET, $elementObj, $forDestroy, $forLevel);
		}
		
		$elementCost	= 0;

		foreach(array_keys(Vars::getElements(Vars::CLASS_RESOURCE, Vars::FLAG_CALCULATE_BUILD_TIME)) as $resourceElementId)
        {
            $elementCost	+= $costResources[$resourceElementId];
        }

        switch($elementObj->class)
        {
            case Vars::CLASS_BUILDING:
                $time = $elementCost / ($config->game_speed * (1 + $PLANET[Vars::getElement(14)->name]));
                $time *= pow(0.5, $PLANET[Vars::getElement(15)->name]);
                $time += PlayerUtil::getBonusValue($time, 'BuildTime', $USER);
            break;
            case Vars::CLASS_FLEET:
                $time = $elementCost / $config->game_speed;
                $time *= 1 + $PLANET[Vars::getElement(21)->name];
                $time *= pow(0.5, $PLANET[Vars::getElement(15)->name]);
                $time += PlayerUtil::getBonusValue($time, 'ShipTime', $USER);
            break;
            case Vars::CLASS_DEFENSE:
                $time = $elementCost / $config->game_speed;
                $time *= 1 + $PLANET[Vars::getElement(21)->name];
                $time *= pow(0.5, $PLANET[Vars::getElement(15)->name]);
                $time += PlayerUtil::getBonusValue($time, 'DefensiveTime', $USER);
            break;
            case Vars::CLASS_TECH:
                if(!isset($USER['techNetwork']))
                {
                    $USER['techNetwork']  = PlayerUtil::getLabLevelByNetwork($USER, $PLANET);
                }

                $techLabLevel = 0;

                foreach($USER['techNetwork'] as $planetTechLevel)
                {
                    if(!isset($elementObj->requirements[31]) || $planetTechLevel >= $elementObj->requirements[31])
                    {
                        $techLabLevel += $planetTechLevel;
                    }
                }

                $time = $elementCost / (1000 * (1 + $techLabLevel)) / ($config->game_speed / 2500);
                $time += PlayerUtil::getBonusValue($time, 'ResearchTime', $USER);
            break;
            case Vars::CLASS_PERM_BONUS:
                $time = $elementCost / $config->game_speed;
            break;
        }
		
		if($forDestroy) {
			$time	= floor($time * 1300);
		} else {
			$time	= floor($time * 3600);
		}
		
		return max($time, $config->min_build_time);
	}
	
	public static function isElementBuyable($USER, $PLANET, Element $elementObj, $costResources = NULL, $forDestroy = false, $forLevel = NULL)
	{
		$rest	= self::getRestPrice($USER, $PLANET, $elementObj, $costResources, $forDestroy, $forLevel);
		return count(array_filter($rest)) === 0;
	}
	
	public static function maxBuildableElements($USER, $PLANET, Element $elementObj, $costResources = NULL)
	{
		if(!isset($costResources))
        {
			$costResources	= self::getElementPrice($USER, $PLANET, $elementObj);
		}

		$maxElement	= array(0);

        foreach($costResources as $resourceElementId => $value)
        {
            $resourceElementObj    = Vars::getElement($resourceElementId);
            if(Vars::elementHasFlag($resourceElementObj, Vars::FLAG_RESOURCE_USER))
            {
                $maxElement[]	= floor($USER[$resourceElementObj->name] / $value);
            }
            else
            {
                $maxElement[]	= floor($PLANET[$resourceElementObj->name] / $value);
            }
        }
		
		return min($maxElement);
	}
	
	public static function maxBuildableMissiles($USER, $PLANET, QueueManager $queueObj)
	{
        $currentMissiles    = 0;
        $missileElements    = Vars::getElements(Vars::CLASS_MISSILE);
		foreach($missileElements as $missileElementObj)
        {
            if(Vars::elementHasFlag($missileElementObj, Vars::FLAG_ATTACK_MISSILE))
            {
                $currentMissiles	+= $PLANET[$missileElementObj->name] * 2;
            }
            else
            {
                $currentMissiles	+= $PLANET[$missileElementObj->name];
            }
        }

        $queueObj->queryElementIds(array_keys($missileElements));

        $queueData      = $queueObj->queryElementIds(44);
        if(!empty($queueData))
        {
            $missileDepot = $queueData[count($queueData)-1]['amount'];
        }
        else
        {
            $missileDepot = $PLANET[Vars::getElement(44)->name];
        }

        $maxMissiles        = $missileDepot * 10 * max(Config::get($USER['universe'])->silo_factor, 1);

		$buildableMissileCount  = max(0, $maxMissiles, $currentMissiles);
        $buildableMissiles    = array();

        foreach($missileElements as $missileElementId => $missileElementObj)
        {
            if(Vars::elementHasFlag($missileElementObj, Vars::FLAG_ATTACK_MISSILE))
            {
                $buildableMissiles[$missileElementId]   = $buildableMissileCount / 2;
            }
            else
            {
                $buildableMissiles[$missileElementId]	= $buildableMissileCount;
            }
        }

		return $buildableMissiles;
	}

    public static function addToQueue(QueueManager $queueObj, $USER, $PLANET, Element $elementObj, $buildType, $amount = NULL)
    {
        $elementName        = $elementObj->name;
        $queueElementObj    = Vars::getElement($elementObj->queueId);
        $queueData          = $queueObj->queryElementIds($elementObj->elementID);

        if($buildType !== self::AMOUNT)
        {
            if(!empty($queueData))
            {
                $amount = $queueData[count($queueData)-1]['amount'];
            }
            elseif(Vars::elementHasFlag($elementObj, Vars::FLAG_RESOURCE_USER))
            {
                $amount = $USER[$elementName];
            }
            else
            {
                $amount = $PLANET[$elementName];
            }

            $amount += (int) $buildType === self::BUILD;
        }

        if(empty($amount))
        {
            return array('error' => true, 'code' => 1);
        }

        if($elementObj->maxLevel != 0 && $elementObj->maxLevel <= $amount)
        {
            return array('error' => true, 'code' => 2);
        }

        $queueCount = count($queueObj->queryQueueIds($elementObj->queueId));
        if($queueElementObj->maxCount != 0 && $queueCount >= $queueElementObj->maxCount)
        {
            return array('error' => true, 'code' => 3);
        }

        if($elementObj->class == Vars::CLASS_BUILDING)
        {
            $totalPlanetFields  	= CalculateMaxPlanetFields($PLANET);
            $queueBuildingData      = $queueObj->queryElementIds(array_keys(Vars::getElements(Vars::CLASS_BUILDING)));
            $queueBuildingCount     = count($queueBuildingData);
            if($buildType === self::BUILD && $PLANET["field_current"] >= ($totalPlanetFields - $queueBuildingCount))
            {
                return array('error' => true, 'code' => 4);
            }
        }

        if($buildType === self::AMOUNT)
        {
            $amount = min($amount, self::maxBuildableElements($USER, $PLANET, $elementObj));
        }

        if($elementObj->class == Vars::CLASS_MISSILE)
        {
            $maxMissiles    = self::maxBuildableMissiles($USER, $PLANET, $elementObj, $queueObj);
            $amount         = min($amount, $maxMissiles[$elementObj->elementID]);
        }

        $costResources  = self::getElementPrice($elementObj, $amount, $buildType === self::DESTROY);

        if($elementObj->class != Vars::CLASS_FLEET && $elementObj->class != Vars::CLASS_DEFENSE
            && $elementObj->class != Vars::CLASS_MISSILE && count($queueData) === 0 )
        {
            if($buildType !== self::AMOUNT && !self::isElementBuyable($USER, $PLANET, $elementObj, $costResources))
            {
                return array('error' => true, 'code' => 5);
            }

            foreach($costResources as $resourceElementId => $value)
            {
                $resourceElementObj    = Vars::getElement($resourceElementId);
                if(Vars::elementHasFlag($resourceElementObj, Vars::FLAG_RESOURCE_PLANET))
                {
                    $PLANET[$resourceElementObj->name]	-= $costResources[$resourceElementId];
                }
                elseif(Vars::elementHasFlag($resourceElementObj, Vars::FLAG_RESOURCE_USER))
                {
                    $USER[$resourceElementObj->name] 	-= $costResources[$resourceElementId];
                }
            }
        }

        $elementTime    = self::getBuildingTime($USER, $PLANET, $elementObj, $costResources);
        $elementEndTime = TIMESTAMP + $elementTime;

        $queueObj->add($elementObj, $amount, $elementEndTime);
        return array('error' => false, 'code' => 0);
    }
}