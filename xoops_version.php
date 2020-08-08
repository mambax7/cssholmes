<?php
/**
 * Cssholmes module
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright	The XOOPS Project http://sourceforge.net/projects/xoops/
 * @license             http://www.fsf.org/copyleft/gpl.html GNU public license
 * @package	Cssholmes
 * @since		2.5.x
 * @author 		kris <http://www.xoofoo.org>
 * @version		$Id: xoops_version.php 794 2012-08-06 21:08:50Z kris_fr $
**/

$module_dirname = basename( dirname( __FILE__ ) ) ;

$modversion["name"] 	=  _MI_CSSHOLMES_NAME;
$modversion["version"] 	= 1.2;
$modversion["description"] 	= _MI_CSSHOLMES_DESC;
$modversion["author"] 	= "Kris - http://www.xoofoo.org";
$modversion["credits"]	= "Luke Williams - http://www.red-root.com/";
$modversion["help"] 		= "";
$modversion["dirname"] 	= $module_dirname;
$modversion["image"] 	= "images/" . $module_dirname . "_slogo.png";
$modversion["license"] 	= "GNU General Public License";
$modversion["license_url"]	= "http://www.gnu.org/licenses/gpl.html";
$modversion["official"] 	= 0;
$modversion["author_website_url"]	= "http://labs.xoofoo.org";
$modversion["author_website_name"]	= "Laboratoire XooFoo";

// About
$modversion["demo_site_url"]			= "";
$modversion["demo_site_name"]			= "";
$modversion["module_website_url"]		= "http://labs.xoofoo.org";
$modversion["module_website_name"]	= "Labs XooFoo";
$modversion["module_release"]			= "30/06/2012";
$modversion["module_status"]			= "Final";

// Admin Menu
$modversion["system_menu"] = 1 ;
$modversion["hasAdmin"] 	= 1;
$modversion["adminindex"] = "admin/index.php";
$modversion["adminmenu"] = "admin/menu.php";

// Menu
$modversion["hasMain"] 		= 0;

// Templates
$i = 1;
$modversion["templates"][$i]["file"] 		= "admin/". $module_dirname . "_admin_index.html";
$modversion["templates"][$i]["description"] 	= _MI_CSSHOLMES_MANAGER_INDEX_DESC;
$i++;
$modversion["templates"][$i]["file"] 		= "admin/". $module_dirname . "_admin_about.html";
$modversion["templates"][$i]["description"] 	= _MI_CSSHOLMES_MANAGER_ABOUT_DESC;

?>