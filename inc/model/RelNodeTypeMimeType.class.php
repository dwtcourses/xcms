<?php
/**
 *  \details &copy; 2011  Open Ximdex Evolution SL [http://www.ximdex.org]
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
 * @author Ximdex DevTeam <dev@ximdex.com>
 * @version $Revision$
 */



require_once XIMDEX_ROOT_PATH . '/inc/model/orm/RelNodeTypeMimeType_ORM.class.php';

class RelNodeTypeMimeType extends RelNodeTypeMimeType_ORM
{

    function getFileExtension($nodetype)
    {
        $filter = $this->find('filter', 'idnodetype = %s', array($nodetype), MONO);
        if (strcmp($filter[0], 'ptd') == 0) {
            $ext = ($nodetype == \Ximdex\Services\NodeType::TEMPLATE) ? "xml" : "xsl";
        } elseif (strcmp($filter[0], 'pvd') == 0) {
            $ext = "xml";
        } else {
            $ext = $filter[0];
        }
        return $ext;
    }
}