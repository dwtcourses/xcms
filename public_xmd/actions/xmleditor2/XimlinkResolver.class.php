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

use Ximdex\Logger;
use Ximdex\Models\Link;
use Ximdex\Models\Node;
use Ximdex\NodeTypes\NodeTypeConstants;
use Ximdex\IO\BaseIO;

class ximlinkResolver
{
	public function resolveximlinkUrl(int $idnode, int $idchannel = null)
	{
		$node = new Link($idnode);
		if (! $node->get('IdLink')) {
			$data = array('error' => _("ximlink with ID $idnode not found"));
			return $data;
		}
		$name = $node->getName();
		$url = $node->get('Url');
		$it = $node->getDescriptions();
		$descArray = array();
		while ($desc = $it->next()) {
			$descArray[] = $desc->get('Description');
		}
		$data = array
		(
			'idnode' => $idnode,
			'name' => $name,
			'url' => $url,
			'text' => $descArray
		);
		return $data;
	}

	/**
	 * Get the available ximlinks from a term or idnode
	 * 
	 * @param int $docid
	 * @param string $term
	 * @return array
	 */
	public function getAvailableximlinks(int $docid, string $term = null)
	{
		$node = new Node($docid);
		if (! $node->get('IdNode')) {
			$data = array('error' => _('Not found'));
			return $data;
		}
		$idprj = $node->getProject();
		if (! empty($term)){
            $query = "select n.IdNode from FastTraverse f inner join Nodes n on f.idchild = n.idnode
				inner join Links l on n.idnode = l.idlink
			    where f.idnode = $idprj and n.idnodetype = " . \Ximdex\NodeTypes\NodeTypeConstants::LINK . " and (n.Name like '%{$term}%' or
				n.Description like '%{$term}%' or l.Url like '%{$term}%' or n.idnode = '{$term}')
				order by n.Name asc limit 0,50 ";
		} else {
			$query = "select n.IdNode from FastTraverse f inner join Nodes n on f.idchild = n.idnode
                inner join Links l on n.idnode = l.idlink
                where f.idnode = $idprj and n.idnodetype = " . \Ximdex\NodeTypes\NodeTypeConstants::LINK . " 
                order by n.Name asc limit 0,50 ";
		}
		Logger::debug($query);
		$data = array();
		$db = new \Ximdex\Runtime\Db();
		$db->query($query);
		while (! $db->EOF) {
			$data[] = $this->resolveximlinkUrl($db->getValue('IdNode'));
			$db->next();
		}
		return $data;
	}

	public function saveximlink(int $iddoc, int $idnode, int $idchannel, string $name, string $url, string $text)
	{
		$idlink = $this->nodeExistsByName($iddoc, $name);
		if ($idlink === false) {
			$ret = $this->createNewximlink($iddoc, $name, $url, $text, $idchannel);
			$idlink = $ret[0];
			$link = new Link($idlink);
			if ($link->get('IdLink') > 0 and $text) {
				$link->addDescription($text);
			}
		} else {
			$link = new Link($idlink);
			if ($link->get('IdLink') > 0) {
				$_url = $link->get('Url');
				if ($url != $_url) {
					$link->set('Url', $url);
					$link->update();
				}
				if ($text) {
				    $link->addDescription($text);
				}
			}
		}
		return array($idlink, $idchannel);
	}

	private function getProjectId(int $idnode)
	{
		$node = new Node($idnode);
		if (! $node->get('IdNode')) {
			return false;
		}
		$idprj = $node->getProject();
		return $idprj;
	}

	private function nodeExistsByName(int $iddoc, string $name)
	{
		$idprj = $this->getProjectId($iddoc);
		if ($idprj === false) {
			return false;
		}
		$query = sprintf("select n.IdNode, n.IdParent, n.Name
			from Nodes n join FastTraverse f on f.idchild = n.idnode
			where n.name = '%s' and
				f.idnode = %s and
				f.depth > 0 and
				n.idnodetype = " . NodeTypeConstants::LINK, $name, $idprj);
		$db = new Ximdex\Runtime\Db();
		$db->query($query);
		$idlink = $db->EOF ? false : $idlink = $db->getValue('IdNode');
		return $idlink;
	}

	private function createNewximlink(int $iddoc, string $name, string $url, string $text, int $idchannel)
	{
		$idprj = $this->getProjectId($iddoc);
		$prj = new Node($idprj);
		$ximlinkFolderId = $prj->GetChildByName('ximlink');
		$data = array(
			'NODETYPENAME' => 'LINK',
			'NAME' => $name,
			'PARENTID' => $ximlinkFolderId,
			'CHILDRENS' => array(
				array('URL' => $url),
				array('DESCRIPTION' => $text)
			)
		);
		$bio = new BaseIO();
		$result = $bio->build($data);
		if ($result < 1) {
			Logger::error(_('A new ximlink could not be created: ') . $url);
			foreach ($bio->messages->messages as $msg) {
				Logger::error(_('ximlink: ') . $msg['message']);
			}
		}
		return array($result, $idchannel);
	}
}
