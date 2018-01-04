<div id="Cssholmes_Title" class="bold shadowlight alignmiddle">
    <div id="Cssholmes_Index"><{$smarty.const._AM_CSSHOLMES_MANAGER_INDEX}></div>
    <div class="Cssholmes_Action"></div>
</div>
<table class="width80 floatcenter0">
    <tr>
        <td class="aligntop width60">
            <{foreachq from=$cssholmes_menu item=menu}>
            <div class="rmmenuicon">
                <a class="tooltip" href="<{$menu.link}>" title="<{$menu.name}>">
                    <img src="<{$smarty.const.XOOPS_URL}>/modules/<{$module_dirname}>/images/admin/<{$menu.icon}>"
                         alt="<{$menu.name}>">
                    <span class="shadowlight bold block"><{$menu.name}></span>
                </a>
            </div>
            <{/foreach}>
            <{foreachq from=$cssholmes_admin item=admin}>
            <div class="rmmenuicon">
                <a class="tooltip" href="<{$admin.link}>" title="<{$admin.name}>">
                    <img src="<{$smarty.const.XOOPS_URL}>/modules/<{$module_dirname}>/images/admin/<{$admin.icon}>"
                         alt="<{$admin.name}>">
                    <span class="shadowlight bold block"><{$admin.name}></span>
                </a>
            </div>
            <{/foreach}>
        </td>
        <td class="aligntop width35">
            <{$smarty.const._AM_CSSHOLMES_MANAGER_INDEX_TXT1}>
        </td>
    </tr>
</table>
