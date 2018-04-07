<?php namespace XoopsModules\Cssholmes;

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
 * @copyright           XOOPS Project (https://xoops.org)
 * @license             http://www.fsf.org/copyleft/gpl.html GNU public license
 * @package             Cssholmes
 * @since               2.3.0
 * @author              Dugris <http://www.dugris.info>
 **/
class Menu
{
    public $_items = [];

    public function addItem($id, $name = '', $link = '', $icon = null)
    {
        if (isset($this->_items[$id])) {
            return false;
        }
        $value['link'] = $link;
        $value['name'] = $name;
        if (!isset($icon)) {
            $value['icon'] = $id . '.png';
        } else {
            $value['icon'] = $icon;
        }

        $this->_items[$id] = $value;

        return true;
    }

    public function setLink($id, $link)
    {
        if (isset($this->_items[$id])) {
            $this->_items[$id]['link'] = $link;

            return true;
        } else {
            return false;
        }
    }

    public function setIcon($id, $icon)
    {
        if (isset($this->_items[$id])) {
            $this->_items[$id]['icon'] = $icon;

            return true;
        } else {
            return false;
        }
    }

    public function setName($id, $name)
    {
        if (isset($this->_items[$id])) {
            $this->_items[$id]['name'] = $name;

            return true;
        } else {
            return false;
        }
    }
}