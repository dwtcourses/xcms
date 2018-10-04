<?php

/**
 *  \details &copy; 2018 Open Ximdex Evolution SL [http://www.ximdex.org]
 *
 *  Ximdex a Semantic Content Management System (CMS)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  See the Affero GNU General Public License for more details.
 *  You should have received a copy of the Affero GNU General Public License
 *  version 3 along with Ximdex (see LICENSE file).
 *
 *  If not, visit http://gnu.org/licenses/agpl-3.0.html.
 *
 *  @author Ximdex DevTeam <dev@ximdex.com>
 *  @version $Revision$
 */

namespace Ximdex\Models;

use Ximdex\Logger;
use Ximdex\Models\ORM\BatchsOrm;
use Ximdex\Runtime\Db;

include_once XIMDEX_ROOT_PATH . '/src/Sync/conf/synchro_conf.php';

/**
 * 	@brief Handles operations with Batchs.
 *
 * 	A Batch is a set of documents which have to be published together for obtain the correct graph of the portal.
 * 	This class includes the methods that interact with the Database.
 */
class Batch extends BatchsOrm
{
    const TYPE_UP = 'Up';
    const TYPE_DOWN = 'Down';
    const WAITING = 'Waiting';
    const INTIME = 'InTime';
    const ENDED = 'Ended';
    const CLOSING = 'Closing';
    const NOFRAMES = 'NoFrames';

    public function set($attribute, $value)
    {
        if ($attribute == 'State') {
            Logger::info('Changing state for batch: ' . $this->get('IdBatch') . ' from ' . $this->get('State') . ' to ' . $value);
        }
        parent::set($attribute, $value);
    }
    
    /**
     *  Adds a row to Batchs table
     *  
     *  @param int timeOn
     *  @param string type
     *  @param int idNodeGenerator
     *  @param int priority
     *  @param int idBatchDown
     *  @param int idPortalFrame
     *  @param int $userId
     *  @param int $playing
     *  @return int|null
     */
    public function create($timeOn, $type, $idNodeGenerator, $priority, $idBatchDown = NULL, $idPortalFrame = 0, $userId = NULL, 
        int $playing = 1)
    {
        setlocale(LC_NUMERIC, 'C');
        $this->set('TimeOn', $timeOn);
        $this->set('State', Batch::WAITING);
        $this->set('Playing', $playing);
        $this->set('Type', $type);
        $this->set('IdBatchDown', $idBatchDown);
        $this->set('IdNodeGenerator', $idNodeGenerator);
        $this->set('Priority', $priority);
        $this->set('IdPortalFrame', $idPortalFrame);
        $this->set('UserId', $userId);
        $idBatch = parent::add();
        if ($idBatch > 0) {
            return $idBatch;
        }
        Logger::error("Batch type $type for node $idNodeGenerator");
        return null;
    }

    /**
     *  Gets the field IdBatch from Batchs table which matching the value of nodeId
     *  
     *  @param int nodeId
     *  @return array
     */
    public function getAllBatchsFromNode($nodeId)
    {
        $dbObj = new \Ximdex\Runtime\Db();
        $time = time();
        $dbObj->Query("SELECT IdBatch FROM Batchs WHERE Type = '" . Batch::TYPE_UP . "' AND TimeOn > $time AND IdNodeGenerator = $nodeId
						ORDER BY TimeOn ASC");
        $arrayBatchs = array();
        while (!$dbObj->EOF) {
            $arrayBatchs[] = $dbObj->GetValue("IdBatch");
            $dbObj->Next();
        }
        return $arrayBatchs;
    }

    /**
     *  Increases the number of cycles of a Batch
     *  
     *  @param int majorCycle
     *  @param int minorCycle
     *  @return array
     */
    public function calcCycles($majorCycle, $minorCycle)
    {
        if (is_null($majorCycle) || is_null($minorCycle)) {
            Logger::error("ERROR Params $majorCycle - $minorCycle");
            return null;
        }
        if ($minorCycle > $majorCycle) {
            $majorCycle++;
            $minorCycle = 0;
            Logger::info("Up to major cycle $majorCycle batch " . $this->get('IdBatch'));
        } else {
            $minorCycle++;
        }
        return array($majorCycle, $minorCycle);
    }

    /**
     *  Gets the field IdBatch from Batchs join Nodes which matching the value of State
     *  
     *  @param string stateCryteria
     *  @return array|false
     */
    public function getNodeGeneratorsFromBatchs($stateCryteria = null)
    {
        $dbObj = new \Ximdex\Runtime\Db();
        $where = "";
        if ($stateCryteria) {
            if ($stateCryteria != "Any") {
                $where .= " AND Batchs.State = '" . $stateCryteria . "'";
            }
        }
        $query = "SELECT Batchs.IdNodeGenerator, Nodes.Name FROM Batchs, Nodes where Batchs.IdNodeGenerator = Nodes.IdNode " 
            . $where . " group by Batchs.IdNodeGenerator";
        $dbObj->Query($query);
        if (!$dbObj->numErr) {
            if ($dbObj->numRows > 0) {
                $arrayNodes = array();
                while (!$dbObj->EOF) {
                    $arrayNodes[$dbObj->row['IdNodeGenerator']]['Name'] = $dbObj->row['Name'];
                    $nodeGeneratorObj = new Node($dbObj->row['IdNodeGenerator']);
                    $arrayNodes[$dbObj->row['IdNodeGenerator']]['Path'] = $nodeGeneratorObj->getPath();
                    $dbObj->Next();
                }
                return $arrayNodes;
            }
        } else {
            Logger::info("Error in DB: " . $dbObj->desErr);
        }
        return false;
    }

    /**
     *  Gets the Batchs which matching some criteria
     *  
     *  @param string stateCriteria
     *  @param int activeCriteria
     *  @param int downCriteria
     *  @param int limitCriteria
     *  @param int idNodeGenerator
     *  @param int dateUpCriteria
     *  @param int dateDownCriteria
     *  @return array|false
     */
    public function getAllBatchs($stateCriteria = null, $activeCriteria = null, $downCriteria = null, $limitCriteria = null
        , $idNodeGenerator = null, $dateUpCriteria = 0, $dateDownCriteria = 0)
    {
        $dbObj = new \Ximdex\Runtime\Db();
        $where = " WHERE 1 ";
        if ($stateCriteria) {
            if ($stateCriteria != "Any") {
                $where .= " AND State = '" . $stateCriteria . "'";
            }
        }
        if ($activeCriteria !== null) {
            if ($activeCriteria != "Any") {
                $where .= " AND Playing = '" . $activeCriteria . "'";
            }
        }
        if ($downCriteria !== null) {
            if ($downCriteria != "Any") {
                $where .= " AND Type = '" . Batch::TYPE_UP . "'";
            }
        }
        if ($idNodeGenerator !== null) {
            $where .= " AND IdNodeGenerator = " . $idNodeGenerator;
        }
        if ($dateUpCriteria != 0) {
            $where .= " AND TimeOn >= " . $dateUpCriteria;
        }
        if ($dateDownCriteria != 0) {
            $where .= " AND TimeOn <= " . $dateDownCriteria;
        }
        if ($limitCriteria !== null) {
            $limit = " LIMIT 0," . $limitCriteria;
        }
        $query = "SELECT IdBatch, TimeOn, State, Playing, Type, IdBatchDown, " .
                "IdNodeGenerator FROM Batchs" . $where . " ORDER BY Priority DESC, TimeOn DESC" .
                $limit;
        $dbObj->Query($query);
        if (!$dbObj->numErr) {
            if ($dbObj->numRows > 0) {
                $arrayBatchs = array();
                while (!$dbObj->EOF) {
                    $arrayBatchs[] = $dbObj->row;
                    $dbObj->Next();
                }
                return $arrayBatchs;
            }
        } else {
            Logger::info("Error en BD: " . $dbObj->desErr);
        }
        return false;
    }

    /**
     *  Gets the Batch of type Down associated to a Batch of type Up
     *  
     *  @param int batchId
     *  @return array
     */
    public function getDownBatch($batchId)
    {
        $dbObj = new \Ximdex\Runtime\Db();
        $query = "SELECT downBatchs.IdBatch, downBatchs.TimeOn FROM Batchs upBatchs, " .
                "Batchs AS downBatchs WHERE downBatchs.IdBatch = upBatchs.IdBatchDown " .
                "AND upBatchs.IdBatch = $batchId";
        $dbObj->Query($query);
        $arrayBatch = array();
        while (!$dbObj->EOF) {
            $arrayBatch = array(
                'IdBatch' => $dbObj->GetValue("IdBatch"),
                'TimeOn' => $dbObj->GetValue("TimeOn")
            );
            $dbObj->Next();
        }
        return $arrayBatch;
    }

    /**
     *  Gets the Batch of type Up associated to a Batch of type Down
     *  
     *  @param int batchId
     *  @return array
     */
    public function getUpBatch($batchId)
    {
        $result = parent::find('IdBatch', 'IdBatchDown = %s', array('IdBatchDown' => $batchId), MONO);
        if (is_null($result)) {
            return null;
        }
        return $result;
    }

    /**
     *  Sets the field Playing for a Batch
     *  
     *  @param int idBatch
     *  @param int playingValue
     *  return bool
     */
    public function setBatchPlayingOrUnplaying($idBatch, $playingValue = 1)
    {
        if ($playingValue == 2) {
            $playingValue = ($this->get('Playing') == 0) ? 1 : 0;
        }
        parent::__construct($idBatch);
        $this->set('Playing', $playingValue);
        $updatedRows = parent::update();
        if ($updatedRows == 1) {
            Logger::info("Setting playing Value = $playingValue for batch $idBatch");
            return true;
        } else {
            Logger::error('Cannot set playing value for batch' . $idBatch);
        }
        return false;
    }

    /**
     *  Sets the field Priority for a Batch
     *  
     *  @param int idBatch
     *  @param string mode
     *  return bool
     */
    public function prioritizeBatch($idBatch, $mode = 'up')
    {
        //Hack: fix bad compose of float field in SQL update
        setlocale (LC_NUMERIC, 'C');
        parent::__construct($idBatch);
        $priority = (float) $this->get('Priority'); 
        if ($mode === 'up') {
            $priority += 0.3;
            if ($priority > 1) {
                $priority = 1;
            }
        } else {
            $priority -= 0.3;
            if ($priority < 0) {
                $priority = 0;
            }
        }
        $this->set('Priority', $priority);
        $hasUpdated = parent::update();
        if ($hasUpdated) {
            Logger::info("Setting priority Value = $priority for batch $idBatch");
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Retrieve a total of batchs in processing status
     * 
     * @param string $state
     * @return int
     */
    public static function countBatchsInProcess(string $state = Batch::INTIME)
    {
        $sql = 'SELECT COUNT(IdBatch) AS total FROM Batchs WHERE TimeOn < UNIX_TIMESTAMP() AND State = \'' . $state 
            . '\' AND ServerFramesTotal > 0 AND Playing = 1';
        $dbObj = new Db();
        $dbObj->Query($sql);
        if ($dbObj->numRows) {
            return $dbObj->GetValue('total');
        }
        return 0;
    }
}
