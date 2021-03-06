<?php

/**
 *  \details &copy; 2019 Open Ximdex Evolution SL [http://www.ximdex.org]
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

namespace Ximdex\Sync;

use Ximdex\Logger;
use Ximdex\IO\Connection\ConnectionManager;
use Ximdex\IO\Connection\Connector;
use Ximdex\Models\Batch;
use Ximdex\Models\PortalFrames;
use Ximdex\Models\Pumper;
use Ximdex\Models\Server;
use Ximdex\Models\ServerFrame;
use Ximdex\Models\NodeFrame;
use Ximdex\Runtime\App;
use Ximdex\Cli\CliParser;

set_time_limit(0);
set_error_handler(null, E_ALL);

/**
 * Constants definition
 */
define('PUMPER_ERROR_LEVEL_OK', 0);
define('PUMPER_ERROR_LEVEL_SOFT', 1);
define('PUMPER_ERROR_LEVEL_HARD', 2);
define('PUMPER_ERROR_LEVEL_FATAL', 3);

class DexPumperCli extends CliParser
{
    public $_metadata = array(
        array(
            'name' => '--iduser', 'mandatory' => false, 'default' => 'ximdex',
            'message' => 'Username (or identifier)', 'type' => TYPE_STRING
        ),
        array(
            'name' => '--verbose', 'mandatory' => false, 'default' => 5,
            'message' => 'Indicates the verbosity level of the log', 'type' => TYPE_INT
        ),
        array(
            'name' => '--maxvoidcycles', 'mandatory' => false, 'default' => 100,
            'message' => 'Number of empty cycles before the pumper end',
            'type' => TYPE_INT
        ),
        array(
            'name' => '--sleeptime', 'mandatory' => false, 'default' => 10,
            'message' => 'Number of seconds to wait between empty cycles',
            'type' => TYPE_INT
        ),
        array(
            'name' => '--pumperid', 'mandatory' => true,
            'message' => 'Identifier of pumper to be processed',
            'type' => TYPE_INT, 'group' => array(
                'name' => 'operation', 'value' => 1
            )
        ),
        array(
            'name' => '--localbasepath', 'mandatory' => true,
            'message' => 'Base path of the documents', 'type' => TYPE_STRING,
            'group' => array(
                'name' => 'operation', 'value' => 1
            )
        ),
        array(
            'name' => '--tryserver', 'mandatory' => true,
            'message' => 'Identifier of server to be tested',
            'type' => TYPE_INT, 'group' => array(
                'name' => 'operation', 'value' => 2
            )
        ),
        array(
            'name' => '--delay', 'mandatory' => false,
            'message' => 'Delay time to start the main process',
            'type' => TYPE_INT, 'group' => array(
                'name' => 'operation', 'value' => 1
            )
        )
    );
    
    public function __construct(int $paramsCount, array $params)
    {
        $this->_metadata[0]['message'] = _('Username (or identifier)');
        $this->_metadata[1]['message'] = _('Indicates the verbosity level of the log');
        $this->_metadata[2]['message'] = _('Number of empty cycles before the pumper end');
        $this->_metadata[3]['message'] = _('Identifier of pumper to be processed');
        $this->_metadata[4]['message'] = _('Base path of the documents');
        $this->_metadata[5]['message'] = _('Identifier of server to be tested');
        parent::__construct($paramsCount, $params);
    }
}

class DexPumper
{
    const RETRIES_TO_HARD_ERROR = 2;
    
    const RETRIES_TO_FATAL_ERROR = 5;
    
    private $idUser;
    
    private $verbose;
    
    private $maxVoidCycles;
    
    private $sleepTime;
    
    private $localBasePath;
    
    private $pumper;
    
    private $connection;
    
    private $serverFrame;
    
    private $server;
    
    private $delay;
    
    public function __construct(array $params)
    {
        // Collect parameters
        if (isset($params['--tryserver'])) {
            $this->tryserver = (int) $params['--tryserver'];
        }
        $this->idUser = (int) $params['--iduser'];
        $this->verbose = trim($params['--verbose']);
        $this->maxVoidCycles = (int) $params['--maxvoidcycles'];
        $this->sleepTime = (int) $params['--sleeptime'];
        $this->pumper = new Pumper((int) $params['--pumperid']);
        if (! $this->pumper->get('PumperId')) {
            $this->fatal('Pumper Id NOT found for id: ' . $params['--pumperid']);
        }
        $this->debug('NEW PUMPER: ' . $params['--pumperid']);
        $this->localBasePath = trim($params['--localbasepath']);
        if (! empty($params['--delay'])) {
            $this->delay = (int) $params['--delay'];
        }
    }
    
    public function start()
    {
        if ($this->delay) {
            Logger::info("Waiting {$this->delay} seconds to start this pumper");
            sleep($this->delay);
        }
        $cycle = 0;
        $this->registerPumper();
        while (true) {
            $this->checkServer();
            if (! $cycle) {
                $this->debug('STARTING PUMPER CYCLE');
            } else {
                $this->debug('PUMPER CYCLE');
            }
            $serverFrame = new ServerFrame();
            $pumperID = $this->pumper->get('PumperId');
            $serverFrameInfo = $serverFrame->getPublishableNodesForPumper($pumperID);
            $countNodes = count($serverFrameInfo);
            if ($countNodes) {
                $this->info($countNodes . ' nodes for pumping with PumperID: ' . $pumperID);
            }
            
            // Exit condition here when cycles reach max void cycles
            if (empty($serverFrameInfo)) {
                $this->checkServer();
                $cycle++;
                if ($cycle <= $this->maxVoidCycles) {
                    $this->updateTimeInPumper();
                    $this->activeWaiting();
                    
                    // Manual stop for pumpers in sleeping mode
                    $stopper_file_path = XIMDEX_ROOT_PATH . App::getValue('TempRoot') . '/pumpers.stop';
                    if (file_exists($stopper_file_path)) {
                        Logger::warning('[PUMPERS] ' . 'STOP: Detected file ' . $stopper_file_path);
                        $this->unRegisterPumper();
                        exit();
                    }
                    $this->debug('cycle ' . $cycle . ' without working. Sleeping...');
                    continue;
                } else {
                    $this->info('Max cycles ' . $cycle . ' for pumper with ID: ' . $pumperID . '. Bye!');
                    break;
                }
            }
            $serverFrameInfo = $serverFrameInfo[0];
            $task = $serverFrameInfo['IdSync'];
            $state_task = $serverFrameInfo['State'];
            $this->serverFrame = new ServerFrame($task);
            $IdSync = $this->serverFrame->get('IdSync');
            if (empty($IdSync)) {
                $this->fatal('ServerFrame not found: ' . $task);
            }
            $this->info('ServerFrame ' . $IdSync . ' to process');
            $this->getHostConnection();
            if ($state_task == ServerFrame::DUE2IN) {
                $this->uploadFile(UPLOAD_FILE_AS_HIDDEN_WHILE_PUMPING);
            } elseif ($state_task == ServerFrame::DUE2OUT) {
                $this->removeRemoteFile();
            } elseif ($state_task == ServerFrame::PUMPED) {
                $this->renamePumpedFiles();
            }
        }
        $this->unRegisterPumper();
    }
    
    private function uploadFile(bool $asHidden = false)
    {
        $localPath = $this->localBasePath . '/';
        $initialDirectory = $this->server->get('InitialDirectory');
        $IdSync = (int) $this->serverFrame->get('IdSync');
        $remotePath = $this->serverFrame->get('RemotePath');
        $fileName = $this->serverFrame->get('FileName');
        $this->info('ServerFrame ' . $IdSync . ' DUE2IN: upload as hidden file');
        $originFile = $localPath . $IdSync;
        if ($asHidden) {
            $state = ServerFrame::PUMPED;
            $targetFile = '.' . $IdSync . '_' . $fileName;
        } else {
            $state = ServerFrame::IN;
            $targetFile = $fileName;
        }
        $uploading = $this->taskUpload($originFile, $initialDirectory, $remotePath, $targetFile);
        if (! $asHidden and $uploading) {
            Logger::info('Published: ' . rtrim($remotePath, '/') . '/' . $targetFile . ' into server '
                . $this->connection->getServer()->get('Description') . ' (Sync: ' . $IdSync . ')', true);
        }
        $this->updateTask($uploading, $state);
    }
    
    private function removeRemoteFile()
    {
        $IdSync = (int) $this->serverFrame->get('IdSync');
        $initialDirectory = $this->server->get('InitialDirectory');
        $fileName = $this->serverFrame->get('FileName');
        $remotePath = $this->serverFrame->get('RemotePath');
        $this->info('ServerFrame ' . $IdSync . ' DUE2OUT: Delete file from server');
        $targetFolder = $initialDirectory . $remotePath;
        $targetFile = $targetFolder . '/' . $fileName;
        if ($this->connection->getType() != Connector::TYPE_API and ! $this->connection->isFile($targetFile)) {
            
            // If the file has been deleted, does not nothing and return a soft ok
            $removing = true;
        } else {
            $removing = $this->taskDelete($targetFile);
            if ($removing) {
                Logger::info('Successfusly removed file ' . rtrim($remotePath, '/') . '/' . $fileName . ' (ID: '
                    . $this->serverFrame->get('NodeId') . ') from server ' . $this->connection->getServer()->get('Description'), true);
                if (rtrim($targetFolder, '/') != rtrim($initialDirectory, '/') and $this->connection->dirIsEmpty($targetFolder)) {
                    
                    // Remove the target folder if it is empty
                    if (! $this->connection->rm($targetFolder)) {
                        Logger::warning('Could not delete the path folder ' . $targetFolder);
                    }
                }
            }
        }
        $this->updateTask($removing, ServerFrame::OUT);
    }
    
    private function RenameFile(array $file)
    {
        $initialDirectory = $this->server->get('InitialDirectory');
        $remotePath = $file['RemotePath'];
        $IdSync = (int) $file['IdSync'];
        $fileName =  $file['FileName'];
        $targetFolder = $initialDirectory . $remotePath;
        if (substr($targetFolder, -1) != '/') {
            $targetFolder .= '/';
        }
        $originFile = $targetFolder . '.' . $IdSync . '_' . $fileName;
        $targetFile = $fileName;
        $this->info('Renaming file: ' . $originFile . ' -> ' . $targetFile  . ' (Sync: ' . $IdSync . ')');
        return $this->taskRename($originFile, $targetFolder,  $targetFile, $IdSync);
    }
    
    private function getFilesToRename(int $IdBatchUp, int $IdServer)
    {
        $IdSync = (int) $this->serverFrame->get('IdSync');
        $this->info('ServerFrame ' . $IdSync . ' rename hidden file to final file');
        $fields = 'IdSync, RemotePath, FileName';
        $state_pumped = ' state = \'' . ServerFrame::PUMPED . '\' ';
        $conditions = $state_pumped . ' AND IdBatchUp = %s AND IdServer = %s';
        return $this->serverFrame->find($fields, $conditions,  array($IdBatchUp, $IdServer) , MULTI, false);
    }
    
    private function renamePumpedFiles()
    {
        $idBatchUp = (int) $this->serverFrame->get('IdBatchUp');
        $batch = new Batch($idBatchUp);
        $batchId = (int) $batch->get('IdBatch');
        $batchState = $batch->get('State');
        if (! empty($batchId) && Batch::CLOSING == $batchState) {
            $idSync = (int) $this->serverFrame->get('IdSync');
            $this->info('ServerFrame ' . $idSync . ' pumping');
            $idServer = (int) $this->serverFrame->get('IdServer');
            $filesToRename = $this->getFilesToRename($idBatchUp, $idServer);
            $totalToRename = count($filesToRename);
            if (is_array($filesToRename) && $totalToRename > 0) {
                $this->info($totalToRename . ' files to rename with batch: ' . $batchId);
                foreach ($filesToRename as $file) {
                    $renameResult = $this->RenameFile($file);
                    if ($renameResult or $renameResult !== false) {
                        $this->updateTask(true, ServerFrame::IN, $file['IdSync']);
                    } else {
                        $this->updateTask(false, ServerFrame::DUE2INWITHERROR, $file['IdSync']);
                    }
                }
            }
        }
    }
    
    private function updateTimeInPumper()
    {
        $pumper = new Pumper($this->pumper->get('PumperId'));
        if (is_numeric($pumper->get('ProcessId')) and $pumper->get('ProcessId') != getmypid() and Pumper::isAlive($pumper)) {
            
            // Another pumper with the same ID is running, shuting down this
            Logger::warning('Exiting the pumping process in order to running a newer process');
            exit();
        }
        $this->pumper->set('CheckTime', time());
        $this->pumper->update();
    }
    
    private function activeWaiting(int $time = null)
    {
        if (! $time) {
            $time = $this->sleepTime;
        }
        sleep($time);
    }
    
    private function getHostConnection() : bool
    {
        if ($this->connection) {
            $this->connection->setError(null);
        }
        $this->updateTimeInPumper();
        if (is_null($this->connection)) {
            if (is_null($this->server)) {
                $this->server = new Server($this->pumper->get('IdServer'));
            }
            $idProtocol = $this->server->get('IdProtocol');
            try {
                $this->connection = ConnectionManager::getConnection($idProtocol, $this->server);
            } catch (\Exception $e) {
                Logger::error($e->getMessage());
                return false;
            }
        }
        $host = $this->server->get('Host');
        $port = $this->server->get('Port');
        $login = $this->server->get('Login');
        $passwd =  $this->server->get('Password');
        $res = true;
        if (! $this->connection->isConnected()) {
            Logger::info('Connecting to ' . $this->server->get('Host'));
            if ($this->connection->connect($host, $port)) {
                Logger::info('Logging to ' . $this->server->get('Host') . ' with user ' . $login);
                if (! $this->connection->login($login, $passwd)) {
                    $this->error('Can\'t log the user into host: ' . $host);
                    $res = false;
                } else {
                    Logger::info('Connected to ' . $this->server->get('Host'), false, 'magenta');
                }
            } else {
                $this->error('Can\'t connect to host: ' . $host);
                $res = false;
            }
        }
        if ($this->connection->getError()) {
            $this->error($this->connection->getError());
            $res = false;
        }
        if (! $res or ! $this->connection->isConnected()) {
            $msg_error = sprintf('Fail to connect or wrong login credentials for server: %s:%s with user: %s',  $host, $port, $login);
            $this->fatal($msg_error);
            $this->updateTask(false);
            
            // Update batch for pumper server frame
            if ($this->serverFrame) {
                if ($this->serverFrame->get('IdBatchUp')) {
                    $idBatch = $this->serverFrame->get('IdBatchUp');
                } elseif ($this->serverFrame->get('IdBatchDown')) {
                    $idBatch = $this->serverFrame->get('IdBatchDown');
                }
                if (isset($idBatch)) {
                    $batchManager = new BatchManager();
                    $batchManager->setBatchsActiveOrEnded(null, null, true, $idBatch);
                } else {
                    
                    // $this->error('Server frame ' . $this->serverFrame->get('IdSync') . ' without batch associated');
                    $this->unRegisterPumper();
                    exit(200);
                }
            }
            
            // To recover the pumper tasks with errors
            $server = new Server($this->pumper->get('IdServer'));
            $server->disableForPumping(true);
            
            // Update portal frames for pumper server
            try {
                PortalFrames::updatePortalFrames(null, $server);
            } catch (\Exception $e) {
                Logger::error($e->getMessage());
            }
            
            // Ending pumper
            $this->unRegisterPumper();
            exit(200);
        }
        $this->updateTimeInPumper();
        return true;
    }
    
    private function taskBasic(string $baseRemoteFolder, string $relativeRemoteFolder) : bool
    {
        $msg_not_found_folder =  _('Could not find the base folder') . ': ' . $baseRemoteFolder;
        $msg_cant_create_folder = _('Could not find or create the destination folder') . ' ' . $baseRemoteFolder . $relativeRemoteFolder;
        Logger::debug('Moving to remote folder ' . $baseRemoteFolder);
        if (! $this->connection->cd($baseRemoteFolder)) {
            $this->warning($msg_not_found_folder);
        }
        Logger::debug('Making remote folder ' . $baseRemoteFolder . $relativeRemoteFolder);
        if (! $this->connection->mkdir($baseRemoteFolder . $relativeRemoteFolder, 0755, true)) {
            $this->error($msg_cant_create_folder);
            return false;
        }
        return true;
    }
    
    private function taskUpload(string $localFile, string $baseRemoteFolder, string $relativeRemoteFolder, string $remoteFile) : ?bool
    {
        if (! file_exists($localFile)) {
            $this->error('The sync file: ' . $localFile . ' does not exist');
            return null;
        }
        $this->getHostConnection();
        if (! $this->taskBasic($baseRemoteFolder, $relativeRemoteFolder)) {
            return false;
        }
        $fullPath = $baseRemoteFolder . $relativeRemoteFolder;
        if (substr($relativeRemoteFolder, -1) != '/') {
            $fullPath .= '/';
        }
        $fullPath .= $remoteFile;
        Logger::debug('Checking the file path ' . $fullPath);
        if (UPLOAD_FILE_AS_HIDDEN_WHILE_PUMPING and $this->connection->isFile($fullPath)) {
            $this->warning('Uploading file ' . $fullPath . ' file already exist, overwriting');
        }
        $this->info('Copying ' . $localFile . ' in ' . $fullPath);
        if (! $this->connection->put($localFile, $fullPath)) {
            if ($this->connection->getError()) {
                $this->error($this->connection->getError());
            }
            if ($this->connection->getType() == Connector::TYPE_API and $this->connection->getCode() == 400) {
                return null;
            }
            $this->error('Could not upload the file: ' . $localFile . ' -> ' . $fullPath);
            return false;
        }
        return true;
    }
    
    private function taskDelete(string $remoteFile) : ?bool
    {
        if ($this->connection->getType() == Connector::TYPE_API) {
            if (! $this->serverFrame->get('IdNodeFrame')) {
                $this->error('Cannot load the node frame from the current server frame');
                return null;
            }
            $nodeFrame = new NodeFrame($this->serverFrame->get('IdNodeFrame'));
            $id = $nodeFrame->get('NodeId');
            if (! $id) {
                $this->error('Cannot load the node ID from the current node frame');
                return null;
            }
        } else {
            $id = null;
        }
        $res = $this->connection->rm($remoteFile, $id);
        if ($res === false) {
            $this->error($this->connection->getError());
            if ($this->connection->getType() == Connector::TYPE_API and $this->connection->getCode() == 400) {
                return null;
            }
        }
        return $res;
    }
    
    private function taskRename(string $targetFile, string $targetFolder, string $newFile, int $idSync = null) : ?bool
    {
        $this->getHostConnection();
        if (! $this->taskBasic($targetFolder, '')) {
            return false;
        }
        if (! $this->connection->isFile($targetFile)) {
            $this->warning('File ' . $targetFile . ' to rename does not exist');
            return null;
        }
        if (! $this->connection->rename($targetFile, $targetFolder . $newFile)) {
            $this->error('Could not rename the target document: ' . $targetFile . ' -> ' . $targetFolder . $newFile);
            return false;
        }
        Logger::info('Published: ' . rtrim($targetFolder, '/') . '/' . $newFile . ' into server '
            . $this->connection->getServer()->get('Description') . ' (Sync: ' . $idSync . ')', true);
        return true;
    }
    
    private function updateTask(?bool $result, string $status = null, int $idSync = null) : bool
    {
        if (! $idSync) {
            $serverFrame = $this->serverFrame;
        } else {
            $serverFrame = new ServerFrame($idSync);
            if (! $serverFrame->get('IdSync')) {
                $this->error('Cannot load the server frame ' . $idSync);
                return false;
            }
        }
        $this->info('Processing ' . $this->serverFrame->get('IdSync'));
        if (! $result) {
            $retries = $serverFrame->get('Retry');
            if ($result === null) {
                
                // The error is alocated in the local server, no more retries
                $this->fatal('Sync file problem with ID: ' . $serverFrame->IdSync . '. Server frame marked as errored');
                $serverFrame->set('State', ServerFrame::DUE2INWITHERROR);
                $serverFrame->set('ErrorLevel', ServerFrame::ERROR_LEVEL_HARD);
            }
            elseif ($retries >= self::RETRIES_TO_FATAL_ERROR) {
                $this->error('Maximum of retries reached (' . self::RETRIES_TO_FATAL_ERROR . ') for server frame: '
                    . $serverFrame->IdSync . '. Marked as errored');
                $serverFrame->set('State', ServerFrame::DUE2INWITHERROR);
                $serverFrame->set('ErrorLevel', ServerFrame::ERROR_LEVEL_HARD);
            }
            else {
                $retries++;
                $serverFrame->set('Retry', $retries);
                $serverFrame->set('ErrorLevel', ServerFrame::ERROR_LEVEL_SOFT);
                if ($this->connection->getType() == Connector::TYPE_API) {
                    $this->server->disableForPumping(true, SERVER_DELAY_TIME_PUMPER_IN_SOFT_ERROR, false);
                }
            }
            $serverFrame->update();
            return false;
        }
        if ($status !== null) {
            $serverFrame->set('State', $status);
            $serverFrame->set('Linked', 0);
            $serverFrame->set('ErrorLevel', null);
            $serverFrame->set('Retry', 0);
            $serverFrame->update();
            $server = new Server($this->pumper->get('IdServer'));
            $server->resetForPumping();
            $this->pumper->set('ProcessedTasks', $this->pumper->get('ProcessedTasks') + 1);
            $diffTime = $this->pumper->get('CheckTime') - $this->pumper->get('StartTime') + 1e-11;
            $pace = round($this->pumper->get('ProcessedTasks') / $diffTime, 10);
            $this->pumper->set('Pace', $pace);
        }
        $this->updateTimeInPumper();
        return true;
    }
    
    private function registerPumper()
    {
        if (Pumper::NEW == $this->pumper->get('State')) {
            $this->fatal('It has not been possible to register the pump when it has been ' . Pumper::NEW);
            $this->unRegisterPumper();
            exit(0);
        } else {
            $this->startPumper();
        }
    }
    
    private function unRegisterPumper()
    {
        if ($this->connection and $this->connection->isConnected() and $this->server) {
            $this->connection->disconnect();
            Logger::info('Disconnected from server ' . $this->server->get('Host'), false, 'magenta');
        }
        if (Pumper::NEW == $this->pumper->get('State')) {
            $this->fatal('It has not been possible to register the pump when it has been ' . Pumper::NEW);
            exit(0);
        }
        $this->pumper->set('State', Pumper::ENDED);
        $this->pumper->set('ProcessId', 'xxxx');
        $this->pumper->set('CheckTime', time());
        $this->pumper->update();
    }
    
    private function startPumper()
    {
        $time = time();
        $this->pumper->set('State', Pumper::STARTED);
        $this->pumper->set('ProcessId', getmypid());
        $this->pumper->set('CheckTime', $time);
        $this->pumper->update();
        $this->info('Start pumper demond ' . $time);
    }
    
    private function info(string $_msg, string $color = '')
    {
        Logger::info($_msg, false, $color);
    }
    
    private function error(string $_msg)
    {
        $this->msg_log('ERROR PUMPER: ' . $_msg);
        Logger::error($_msg);
    }
    
    private function fatal(string $_msg)
    {
        $this->msg_log('FATAL PUMPER: ' . $_msg);
        Logger::fatal($_msg);
    }
    
    private function debug(string $_msg)
    {
        if (App::debug()) {
            Logger::debug($_msg);
        }
    }
    
    private function warning(string $_msg)
    {
        $this->msg_log('WARNING PUMPER: ' . $_msg);
        Logger::warning($_msg);
    }
    
    private function msg_log(string $_msg)
    {
        $pumperID = (int) $this->pumper->get('PumperId');
        $_msg = '[PumperId: ' . $pumperID . '] ' . $_msg;
        error_log($_msg);
    }
    
    /**
     * Check if server state is active for pumping for each pumper operation
     */
    private function checkServer() : void
    {
        $server = new Server($this->pumper->get('IdServer'));
        if (! $server->get('ActiveForPumping')) {
            Logger::warning('The server ' . $server->get('Description') . ' has been disabled for pumping. Aborting pumper '
                . $this->pumper->get('PumperId'));
            $this->unRegisterPumper();
            exit();
        }
    }
}

global $argc, $argv;
$parameterCollector = new DexPumperCli($argc, $argv);
$dexPumper = new DexPumper($parameterCollector->getParametersArray());
Logger::generate('PUBLICATION', 'publication');
Logger::setActiveLog('publication');
$dexPumper->start();
