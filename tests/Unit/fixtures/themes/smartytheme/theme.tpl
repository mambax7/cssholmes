<{assign var=tname value=$xoTheme->folderName}>
<!DOCTYPE html>
<html>
<head>
    <title><{$xoops_sitename}></title>
</head>
<body>
<{include file="$tname/tpl/header.tpl"}>
<{include file="`$xoTheme->folderName`/tpl/missing.tpl"}>
<{include file='db:system_block_user.tpl'}>
<div class="content"><{$xoops_contents}></div>
</body>
</html>
