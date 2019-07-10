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
use Ximdex\MVC\ActionAbstract;

class Action_modifygroup extends ActionAbstract
{
    /**
     * Main method: shows initial form
     */
    public function index()
    {
    	$idNode = $this->request->getParam('nodeid');
    	$node = new Node($idNode);
    	if (! $node->get('IdNode')) {
    		$this->messages->add(_('Node could not be found'), MSG_TYPE_ERROR);
    		$this->render(array($this->messages), NULL, 'messages.tpl');
    		return;
    	}
    	$values = array(
			'id_node' => $idNode,
			'name' => $node->get('Name'),
	        'nodeTypeID' => $node->nodeType->getID(),
	        'node_Type' => $node->nodeType->GetName(),
			'go_method' => 'modifygroup'
    	);
    	$this->render($values, null, 'default-3.0.tpl');
    }

    public function modifygroup()
    {
    	$idNode = $this->request->getParam('nodeid');
    	$node = new Node($idNode);
		$result = $node->renameNode($this->request->getParam('name'));
		if ($result) {
			$node->messages->add(_('Group has been successfully modified'), MSG_TYPE_NOTICE);
		} else {
		    if ($node->msgErr) {
		        $node->messages->add($node->msgErr, MSG_TYPE_WARNING);
		    } else {
                $node->messages->add(_('An error occurred while modifying group'), MSG_TYPE_ERROR);
		    }
		}
		$values = array('messages' => $node->messages->messages , "parentID" => $node->get('IdParent'));
        $this->sendJSON($values);
    }
}
