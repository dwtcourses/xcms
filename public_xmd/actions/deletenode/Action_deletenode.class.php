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

use Ximdex\Models\Node;
use Ximdex\Models\User;
use Ximdex\MVC\ActionAbstract;
use Ximdex\NodeTypes\NodeTypeConstants;
use Ximdex\Runtime\Session;
use Ximdex\Sync\SynchroFacade;
use Ximdex\NodeTypes\XsltNode;

Ximdex\Modules\Manager::file('/actions/browser3/inc/GenericDatasource.class.php');

class Action_deletenode extends ActionAbstract
{
	public function index()
	{
		$formType = 'simple';
		$nodes = $this->request->getParam('nodes');
		$nodes = GenericDatasource::normalizeEntities($nodes);
		$params = $this->request->getParam('params');
		$userID = Session::get('userID');
		$texto = '';
		if (count($nodes) == 1) {
			$idNode = (int) $this->request->getParam('nodeid');
		} else {
		    return;
		}
		$node = new Node($idNode);
		$children = $node->getChildren();
		if ($node->getNodeType() == NodeTypeConstants::XML_DOCUMENT or $node->GetNodeType() == NodeTypeConstants::HTML_DOCUMENT) {
            $dbObj = new \Ximdex\Runtime\Db();
            $query = 'select IdDoc from StructuredDocuments where TargetLink = ' . $idNode;
            $dbObj->Query($query);
            $symbolics = array();
            while(! $dbObj->EOF) {
                $n = new Node($dbObj->GetValue('IdDoc'));
                $symbolics[] = $n->GetPath();
                $dbObj->Next();
            }
            if (count($symbolics) > 0) {
                $values = array(
                    'path_symbolics' => $symbolics
                );
                $this->render($values, 'linked_document', 'default-3.0.tpl');
                return false;
            }
        }
		$user = new User($userID);
		$depList = array();
		if ($user->hasPermission('delete on cascade')) {
			$undeletableChildren = array();
			if ($node->nodeType->get('Name') != 'XmlContainer') {
				if ($node->nodeType->get('Name') != 'Channel') {
					$depList = $node->GetDependencies();
				}
				$undeletableChildren = $node->TraverseTree(5);
			} else {
				if (sizeof($children) > 0) {
					foreach ($children as $idChild) {
						$childNode = new Node($idChild);
						$depList = array_merge($depList, $childNode->GetDependencies());
					}
				}
			}
			if (sizeof($depList) > 0) {
				foreach($depList as $idDep) {
					$depNode = new Node($idDep);
					$undeletableChildren = array_unique(array_merge($undeletableChildren, $depNode->TraverseTree(5)));
				}
			}
			if (! empty($undeletableChildren)) {
				$texto = _('Because of system restrictions, the following nodes cannot be deleted: ');
				foreach( $undeletableChildren as $_undelete) {
					$node_t = new Node($_undelete);
					$name_t = $node_t->GetNodeName();
					$texto .= " $name_t($_undelete), ";
				}
			} else {
				$formType = 'dependencies';
			}
		} else {
			if (sizeof($children) && count($depList)) {
				$texto = $node->nodeType->get('Name') != 'XmlContainer' ?
					_('Selected document or folder is not empty. It also have external dependencies with other system documents and it cannot be deleted using your role. Please, undo dependencies or use a user with suitable permissions.') : _('Your role cannot delete the selected container because of it has language versions depending on it.');
				$formType = 'no_permisos';
			}

			// Error: if it has not permits to cascade deletion and node has children but has dependencies
			if (sizeof($children) && ! sizeof($depList)) {
				$texto = _('Selected document or folder is not empty and it cannot be deleted using your role. Please, undo dependencies or use a user with suitable permissions.');
				$formType = 'no_permisos';
			}

			// Error: if it has not permits to cascade deletion and node has not children but has dependencies
			if (! sizeof($children) && count($depList)) {
				$texto = _('Selected file has dependencies with other system documents and it cannot be deleted using your role. Please, undo dependencies or use a user with suitable permissions.');
				$formType = 'no_permisos';
			}

			// If it has not permits to cascade deletion and node has not children and has not dependencies.
			// Here it is allowed atomic deletion
			if (! sizeof($children) && ! sizeof($depList)) {
				$formType = 'simple';
			}
		}
		$this->addCss('/actions/deletenode/resources/css/style.css');
		$values = array(
			'id_node' => $idNode,
			'params' => $params,
			'nameNode' => $node->get('Name'),
			'formType' => $formType,
			'texto' => $texto,
		    'nodeTypeID' => $node->nodeType->getID(),
		    'node_Type' => $node->nodeType->GetName(),
			'go_method' => 'delete_node',
		);
		$depListTmp = array();
		if (sizeof($depList) > 0) {
			foreach($depList as $idDep) {
				$depNode = new Node($idDep);
				$depListTmp[$idDep]['name'] = $depNode->GetNodeName();
				$depListTmp[$idDep]['path'] = substr($depNode->GetPath(),16);
			}
		}
		if ($formType == 'no_permisos') {
			$values['titulo'] = $node->nodeType->get('Name') != 'XmlContainer' ?  _('List of pending documents')
				: _('To be able to delete this node, you should first delete the following language versions');
		} else if ($formType == 'dependencies') {

			// Looking for publication (pending and in) tasks for node
			$sync = new SynchroFacade();
			$pendingTasks = $sync->getPendingTasksByNode($idNode);
			$isPublished = $sync->isNodePublished($idNode);
			if (sizeof($children) > 0  && !($isPublished && count($pendingTasks) > 0)) {
				foreach ($children as $idChild) {
					$childNode = new Node($idChild);
					$children = array_merge($children, $childNode->TraverseTree());
				}
				foreach ($children as $idChild) {
					$pendingTasks =  $sync->getPendingTasksByNode($idChild);
					$isPublished = $sync->isNodePublished($idChild);
					if ($isPublished && count($pendingTasks) > 0) {
						break;
					}
				}
			}
			$values['depList'] = $depListTmp;
			$values['pendingTasks'] = count($pendingTasks);
			$values['isPublished'] = $isPublished;
		}
		$this->addJs('/actions/deletenode/resources/js/deletenode.js');
		$this->render($values, null, 'default-3.0.tpl');
	}

	public function delete_node()
	{
		$idNode	= (int) $this->request->getParam('nodeid');
		$node = new Node($idNode);
		
		// Get the project node
		$project = new Node($node->getProject());
		
		// docxap.xls node from project templates folder cannot be removed
		if ($node->getNodeName() == 'docxap.xsl' and $node->getNodeType() == NodeTypeConstants::XSL_TEMPLATE)
		{
	        $this->messages->add('Cannot delete the project docxap.xsl node', MSG_TYPE_ERROR);
	        $values = array('action_with_no_return' => true, 'messages' => $this->messages->messages);
	        $this->sendJSON($values);
	        return false;
		}
		$depList = array();
		$deleteDep = $this->request->getParam('unpublishnode');
		$userID = Session::get('userID');
		$parentID = $node->get('IdParent');
		$user = new User($userID);
		$canDeleteOnCascade = $user->hasPermission('delete on cascade');
        $children = $node->getChildren();
		if ($canDeleteOnCascade && $deleteDep) {
			if ($node->nodeType->get('Name') != 'Channel') {
				$depList = $node->getDependencies();
			}
			$undeletableChildren = $node->traverseTree(5);
			if ($node->nodeType->get('Name') == 'XmlContainer') {
				foreach($children as $child) {
					$childNode = new Node($child);
					$depList = array_merge($depList, $childNode->getDependencies());
				}
			} else {
 				if (is_array($depList)) {
					foreach($depList as $idDep) {
						$depNode = new Node($idDep);
						$undeletableChildren = array_unique(array_merge($undeletableChildren, $depNode->TraverseTree(5)));
					}
				}
			}
			
			// Deleting dependencies
			$error = false;
			$err = null;
			if (is_array($depList)) {
				foreach($depList as $depID) {
					$depNode = new Node($depID);
					if ($depNode->delete() === false) {
					    $this->messages->mergeMessages($depNode->messages);
					    $error = true;
					}
					if ($depNode->numErr) {
					    $err = _('An error occurred while deleting dependencies: ');
                        $err .= '<br>' . $depNode->get('IdNode'). ' ' . $depNode->getPath() . '<br>' . _('Error message: ') 
                            . $depNode->msgErr . '<br><br>';
                        $error = true;
					}
				}
			}
			
			// Deleting recursively
			if (! $error) {
    			$node = new Node($idNode);
    			if ($node->delete() === false) {
    			    $this->messages->mergeMessages($node->messages);
    			    $error = true;
    			}
    			if ($node->numErr) {
    			    $err = _('An error occurred while deleting:');
    			    $err .= '<br>' . $node->get('IdNode') . ' ' . $node->GetPath() . '<br>' . _('Error message: ') . $node->msgErr . '<br><br>';
    			    $error = true;
    			}
    			if (strlen($err)) {
    			    $this->messages->add($err, MSG_TYPE_ERROR);
    			} elseif (! $error) {
    			    $this->messages->add(_('All nodes were successfully deleted'), MSG_TYPE_NOTICE);
    			}
			}
			
            // Reload the templates include files in the current project
			if (! $error) {
    			if ($node->getNodeType() == NodeTypeConstants::XSL_TEMPLATE or $node->getNodeType() == NodeTypeConstants::TEMPLATES_ROOT_FOLDER 
    			    or $node->getNodeType() == NodeTypeConstants::SERVER or $node->getNodeType() == NodeTypeConstants::SECTION)
    			{
    			    // Do this when the deleted node make a deletion of templates (node types like projects, servers sections, templates)
    			    $xsltNode = new XsltNode($node);
    			    if ($xsltNode->reload_templates_include($project) === false) {
    			        $this->messages->mergeMessages($xsltNode->messages);
    			    }
    			}
			}
		} else {
		    
			// Error: if it has not permit to cascade deletion and node has children and dependencies
		    if (sizeof($children) && count($depList)) {
                $this->messages->add(_('Node is not empty and it has external dependencies.'), MSG_TYPE_ERROR);
		    }
		    elseif (sizeof($children) && ! sizeof($depList)) {
		        
		        // Error: if it has not permit to cascade deletion and node has children but dependencies
			    $this->messages->add(_('Node is not empty.'), MSG_TYPE_ERROR);
		    }
		    elseif (! sizeof($children) && count($depList)) {
		        
		        // Error: If it has not permit to cascade deletion and node has not children but has dependencies
		        $this->messages->add(_('It has external dependencies.'), MSG_TYPE_ERROR);
		    }
			elseif (! sizeof($children) && ! sizeof($depList)) {
			    
			    // If it has not permit to cascade deletion and node has not children and has not dependencies
			    // Here it is allowed atomic deletion
			    if ($node->delete() === false) {
			        $this->messages->mergeMessages($node->messages);
			    } else {
				    $this->messages->add(_('Action successfully performed.'), MSG_TYPE_NOTICE);
			    }
			}
		}
		$values = array(
			'messages' => $this->messages->messages,
			'action_with_no_return' => true,
			'depList' => $depList,
			'parentID' => $parentID
		);
		$this->sendJSON($values);
	}
}
