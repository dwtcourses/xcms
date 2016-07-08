{**
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
 *  @author Ximdex DevTeam <dev@ximdex.com>
 *  @version $Revision$
 *}

<form method="post" id="formulario" name="formulario" action="{$action_url}">
  <div class="action_header"><h2>{t}Expire section{/t}: {$section_name}</h2>
    <fieldset class="buttons-form">
      {button label="Accept" class='validate btn main_action'}{*message="You will expire this section. Would you like to continue?"*}
    </fieldset>
  </div>

  <div class="message message-warning">
    <p>{t}Publication windows configured for these files will be cancelled.{/t}</p>
  </div>

  <div class="action_content">
    <p>
      {t section_name=$section_name}You have selected to expire the contents of section: {/t}{$section_name} {t}Would you like to expire just this folder or all subsections included in it too?{/t}
    </p>

    <div class="col1-2">

      <label for="no_recursive">
        {t}Expire just this section{/t}
        <input type="radio" name="is_recursive" value="0" id="no_recursive" checked="checked">
      </label>
    </div>

    <div class="col1-2">
      <input type="radio" name="is_recursive" id="recursive" value="1">
      <label for="recursive">{t}Expire section and all subsections included in it{/t}</label>
    </div>
  </div>
</form>
