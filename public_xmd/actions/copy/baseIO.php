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

use Ximdex\Models\Node;
use Ximdex\Models\NodeType;
use Ximdex\Runtime\Constants;

Ximdex\Modules\Manager::file('/inc/ExportXml.class.php', 'ximIO');
Ximdex\Modules\Manager::file('/inc/ImportXml.class.php', 'ximIO');
Ximdex\Modules\Manager::file('/inc/FileUpdater.class.php', 'ximIO');
	
function copyNode($source, $dest, $recursive)
{
	$messages = new Ximdex\Utils\Messages();
	
	// Checking if source is allowed on the destiny to save addicional operations
	$sourceNode = new Node($source);
	if (! $sourceNode->get('IdNode')) {
		$messages->add(_('Source node does not exist'), MSG_TYPE_ERROR);
		return $messages;
	}else{
	  $lastName = $sourceNode->get('Name');
	}
	$destNode = new Node($dest);
	if (! $destNode->get('IdNode')) {
		$messages->add(_('Destination node does not exist'), MSG_TYPE_ERROR);
		return $messages;
	}
	
	// Checking both nodes belong to same project or if the node we want to copy is the root of a complete project 
	if (($sourceNode->getProject() != $sourceNode->getID()) && ($sourceNode->getProject() != $destNode->getProject())) {
		$messages->add(_('You cannot make a copy of nodes between differents projects'), MSG_TYPE_ERROR);
		return $messages;
	}
	$nodeAssociations = NULL;
	
	// 1.- Getting data to export
	$xmlExporter = new ExportXml($source);
	if ($xmlExporter->messages->count(MSG_TYPE_ERROR) > 0) {
		return $xmlExporter->messages;
	}
	$files = null;
	$xml = $xmlExporter->getXml($recursive, $files);
	unset($xmlExporter);

	// Checking if processFirstNode should be called as true or false
	if ($destNode->nodeType->get('IdNodeType') == $sourceNode->nodeType->get('IdNodeType')) {
		$processFirstNode = true;
	} else {
		$dbObj = new \Ximdex\Runtime\Db();
		$query = sprintf('SELECT Amount from NodeAllowedContents'
			. ' WHERE IdNodeType = %s AND NodeType = %s',
			$dbObj->sqlEscapeString($destNode->nodeType->get('IdNodeType')), 
			$dbObj->sqlEscapeString($sourceNode->nodeType->get('IdNodeType')));
		$dbObj->query($query);
		$amount = $dbObj->getValue('Amount');
		if ($amount == 1) {
			$children = $destNode->GetChildren($sourceNode->nodeType->get('IdNodeType'));
			$dest = $children[0];
			$processFirstNode = false;
		} else {
			$processFirstNode = true;
		}
	}

	// 2.- Importing the corresponding database part 
	$importer = new ImportXml($dest, NULL, $nodeAssociations,  Constants::RUN_IMPORT_MODE, $recursive, NULL, $processFirstNode);
	$importer->mode = COPY_MODE;
	$importer->copy($xml);
	foreach ($importer->messages as $message) {
		$messages->add($message, MSG_TYPE_WARNING);
	}
	
    //Add info about what was imported 
	// 3.- Importing contents (from a files array ?), it is not necessary, i have got it in database
	$fileImport = new FileUpdater(0);
	$fileImport->updateFiles(Constants::IMPORT_FILES);
	unset($fileImport);

	// 4.- Cleaning transform table to repeat the copy
	$dbConn = new \Ximdex\Runtime\Db();
	$query = sprintf('SELECT idXimIOExportation FROM XimIOExportations WHERE timeStamp = \'%s\'', Constants::REVISION_COPY);
	$dbConn->query($query);
	if ($dbConn->numRows == 1) {
		$idXimIOExportation = $dbConn->getValue('idXimIOExportation');
		$statusQuery = sprintf('SELECT `status`, count(*) as total'
						. ' FROM XimIONodeTranslations'
						. ' WHERE IdExportationNode != IdImportationNode AND idXimIOExportation = %d'
						. ' GROUP BY `status`'
						. ' ORDER BY `status` DESC'
						,$idXimIOExportation);
		$dbConn->query($statusQuery);
		if ($dbConn->EOF) {
			$messages->add(_('An error occurred during copy process, information about process could be obtained'), MSG_TYPE_ERROR);
		} else {
			while(!$dbConn->EOF) {
				switch ($dbConn->getValue('status')) {
					case '1':
						$messages->add(sprintf(_('%d nodes have been successfully copied'), $dbConn->GetValue('total')), MSG_TYPE_NOTICE);
						break;
					case '-1':
						$messages->add(sprintf(_('%d nodes have not been copied because of lack of permits')
						  , $dbConn->GetValue('total')), MSG_TYPE_WARNING);
						break;
					case '-2':
						$messages->add(sprintf(_('%d nodes have not been copied because of lack of XML info')
						  , $dbConn->GetValue('total')), MSG_TYPE_WARNING);
						break;
					case '-3':
						$messages->add(sprintf(_('%d nodes have not been copied because its parents have not been imported')
						  , $dbConn->GetValue('total')), MSG_TYPE_WARNING);
						break;
					case '-4':
						$messages->add(sprintf(_('%d nodes have not been copied because they are not allowed into the requested parent')
						  , $dbConn->GetValue('total')), MSG_TYPE_WARNING);
						break;
				}
				$dbConn->next();
			}
		}
		$query = sprintf('DELETE FROM XimIONodeTranslations WHERE idXimIOExportation = %d', $idXimIOExportation);
		$dbConn->execute($query);
		$query = sprintf('DELETE FROM XimIOExportations WHERE idXimIOExportation = %d', $idXimIOExportation);
		$dbConn->execute($query);
	}
	$targetNode = new Node($importer->idfinal);
	$newName = $targetNode->getNodeName();
	$nodeType = new NodeType($targetNode->nodeType->get('IdNodeType'));
	if ($lastName != $newName && null != $newName && ('XmlContainer' == $nodeType->GetName() || 'XimletContainer' == $nodeType->getGetName())) {
        $childrens =  $targetNode->getChildren();
        $total = count($childrens);
        for($i = 0; $i < $total; $i++) {
            $children = $childrens[$i];
			$node_child = new Node($children);
			$name_child = $node_child->getNodeName();
			$name_child = str_replace($lastName, $newName, $name_child);
			$node_child->setNodeName($name_child);
        }
    }
    return $messages;
}
