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
use Ximdex\Models\NodeDefaultContents;
use Ximdex\NodeTypes\XsltNode;
use Ximdex\NodeTypes\NodeTypeConstants;
use Ximdex\MVC\ActionAbstract;

class Action_managefolders extends ActionAbstract
{
	/** 
	* Main function
	* Load the manage folders form
	* 
	* Request params:
	* * nodeid
 	*/
    public function index()
    {
		$this->addCss('/actions/addsectionnode/resources/css/style.css');
		$nodeID = $this->request->getParam('nodeid');
		$node = new Node($nodeID);
		$selectedFolders = $this->getChildrenNodeTypes($nodeID);		
		$subfolders = $this->getAvailableSubfolders($node->GetNodeType());
		foreach ($subfolders as $id => & $subfolder) {
			if (! empty($selectedFolders) && in_array($id, $selectedFolders)) {
				$subfolder['selected'] = true;
			} else {
				$subfolder['selected'] = false;
			}
		}
		$this->addJs('/actions/managefolders/resources/js/index.js');
    	$values = array(
    	    'nodeID' => $nodeID,
    	    'subfolder' => $subfolder,
			'sectionName' => $node->get('Name'),
    	    'sectionType' => $node->nodeType->GetName(),
			'subfolders' => $subfolders,
    	    'nodeTypeID' => $node->nodeType->getID(),
    	    'node_Type' => $node->nodeType->GetName(),
			'go_method' => 'configure_section'
    	);
        $this->render($values, null, 'default-3.0.tpl');
    }
    
	/**
    * Data processing function
    * Performs the actions depending on the users choices on the form. 
    *
    * Request params: 
    * * nodeid: section ID
    * * folderlst: list of the selected folders by the user
    */
	public function configure_section()
	{
		$error = false;
		$folderlst = $this->request->getParam('folderlst');
		if (! $folderlst) {
		    $this->messages->add(_('At least one section subfolder is required'), MSG_TYPE_WARNING);
		    $this->sendJSON(['messages' => $this->messages->messages]);
		}
	   	$nodeID = $this->request->getParam('nodeid');
		$parent = new Node($nodeID);
		$existingChildren = $this->getChildrenNodeTypes($nodeID);
		$addfolders = false;
		if (count($folderlst) > count($existingChildren)) {
			$addfolders = true;
			
			// If the user wants to create all the containing folders
			if (empty($existingChildren)) {
				$existingChildren = $folderlst;
			} else {
				$folderlst = array_diff($folderlst, $existingChildren);
			}
		} else {
		    
			// If the user wants to delete all the containing folders
			if (empty($folderlst)) {
				$folderlst = $existingChildren;
			} else {
				$folderlst = array_diff($existingChildren, $folderlst);		
			}
		}

		// Only creating the new folders selected
		if ($addfolders) {
			foreach ($folderlst as $folderNt) {
				$folder = new Node();
				$ndc = new NodeDefaultContents();
				$name = $ndc->getDefaultName($folderNt);
        	    $idFolder = $folder->CreateNode($name, $nodeID, $folderNt, null);
				if (! $idFolder) {
					$error = true;
					break;
				}
			}
		} else {
			foreach ($folderlst as $folderNt) {
                $ndc = new NodeDefaultContents();
                $name = $ndc->getDefaultName($folderNt);
				$nodeid = $parent->GetChildByName($name);
				$deleteFolder = new Node($nodeid);
				$res = $deleteFolder->DeleteNode();
				if (! $res) {
					$error = true;
					break;
				}
			}
		}
		if ($error) {
			$this->messages->add(_('This operation could not be successfully completed.'), MSG_TYPE_ERROR);
		} else {
		    
		    // Get the project node for the current section
		    $project = new Node($parent->getProject());
		    
		    // Reload the templates include files for this new project
		    $xsltNode = new XsltNode($parent);
		    if ($xsltNode->reload_templates_include($project) === false) {
		        $this->messages->mergeMessages($xsltNode->messages);
		    }
		            
	        // Reload the document folders and template folders relations
		    if (! $xsltNode->rel_include_templates_to_documents_folders($project)) {
	            $this->messages->mergeMessages($xsltNode->messages);
		    }
			$this->messages->add(_('This section has been successfully configured.'), MSG_TYPE_NOTICE);
		}
		$values = array(
			'action_with_no_return' => ! $error,
			'messages' => $this->messages->messages,
			'nodeID' => $nodeID
		);
		$this->sendJSON($values);
	}
    
	/** 
    * Getting all the children folders
    * Using the Ximdex\Models\NodeDefaultContents of the data model, returns all the avaliable children folders
	* with a description for a given nodetype
    *
    * Request params:  
    * * nodetype_sec: nodetype ID for the containing folder
    */
	private function getAvailableSubfolders(int $nodetype_sec) : array
	{
		$subfolders = array();
		$res = array();
		$ndc = new NodeDefaultContents();
		$subfolders = $ndc->getDefaultChilds($nodetype_sec);
		foreach ($subfolders as $subfolder){
			$nt = $subfolder['NodeType'];
			$res[$nt]['name'] = $subfolder['Name'];	
			$res[$nt]['description'] = $this->getDescription($nt);	
		}
		return $res;
	}

	/**
	 * Human readable descriptions for subfolders.
     * Returns a proper description for the given nodetype, helping the user to decide if the folder is needed or not.
     * 
	 * @param int $nodetype
	 * @return string
	 */
	private function getDescription(int $nodetype) : string
	{
		switch ($nodetype) {
			case NodeTypeConstants::XML_ROOT_FOLDER:
			    return 'This is the main repository for all your XML contents. It\'s the most important folder in a section.';
			case NodeTypeConstants::IMAGES_ROOT_FOLDER:
			    return 'Inside this folder you can store all the image files you need in several formats (gif, png,jpg, tiff,...)';
			case NodeTypeConstants::IMPORT_ROOT_FOLDER:
			    return 'Into this folder you could store several HTML snippets that you can add directly into your XML documents';
			case NodeTypeConstants::COMMON_ROOT_FOLDER:
			    return 'Use this folder if you need to store JavaScript scripts or text files like PDFs, MS Office documents, etc.';
			case NodeTypeConstants::TEMPLATES_ROOT_FOLDER:
			    return 'Create here your own XSL Templates to redefine some particular appareance in your XML documents.';
			case NodeTypeConstants::XIMLET_ROOT_FOLDER:
			    return 'Create XML snippets that you can import into your XML documents. Typical uses are menus, shared headers, shared footers between all your XML documents.';
			case NodeTypeConstants::HTML_LAYOUT_FOLDER:
			    return 'This folder will storage the layouts, components and HTML views for HTML documents';
			default:
			    return '...';
		}
	}

	/** 
    * Nodetypes for subfolders
    * Returns an array of nodetype for all the children of the given parent node ID
    *
    * Request params:
    * * idParent: Parent node ID
    */
	private function getChildrenNodeTypes(int $idParent)
	{
		$children_nt = array();
		$parentNode = new Node($idParent);
		$children = $parentNode->GetChildren();
		if (! empty($children)) {
			foreach ($children as $child) {
				$ch = new Node ($child);
				$idNodeType = $ch->GetNodeType();
				if (NodeTypeConstants::SECTION != $idNodeType) {
					$children_nt[] = $idNodeType;
				}
			}
		}
		return $children_nt;
	}
}
